<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;

use Throwable;
use Exception;

require_once __DIR__ . "/../../Core/Security/Rate_limit_handler.php";
require_once __DIR__ . "/../../Api/database/database_manager.php";

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;

require_once __DIR__ . "/../../Api/database/Authorization_manager.php";

use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;
use function ACEX_project\WEB\Private\Core\Send_mime_type;

require_once __DIR__ . "/../../Error/Error_manager.php";
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

require_once __DIR__ . "/login.php";

    function Send_user_data(?Rate_limiter $ip_limiter, array $captured_args){
        if(\count($captured_args) !== 1){
            \http_response_code(404);
            exit();
        }

        $category = \strtolower(\trim($captured_args[0]));


        $data = match($category){
            'profile' => Get_user_profile_data(),
            default => null
        };

        if($data === null || $data === []){
            \http_response_code(404);
            exit();
        }

        Send_mime_type(MIME['application']['json']);

        $response = [];
        if(isset($data['email'])) $response['email'] = $data['email'];
        if(isset($data['name'])) $response['name'] = $data['name'];
        if(isset($data['img_url'])) $response['image_url'] = $data['img_url'];
        
        echo json_encode($response);
    }


    /**
     * @return array{
     *      name: string,
     *      email: string,
     *      img_url: string
     * }
     */
    function Get_user_profile_data() : array{
        $data = Get_request_body();
        $requested_uid = $data['id'] ?? null;

        $name = $email = $img_url = '';

        if(!is_int($requested_uid)){
            if(!Is_logged()) {
                \http_response_code(401);
                echo json_encode(['success'=>-2]);
                exit();
            }
            $requested_uid = $_SESSION['uid'];
            //cache
            $name = $_SESSION['user_name'] ?? '';
            $email = $_SESSION['user_email'] ?? '';
            $img_url = $_SESSION['profile_img_url'] ?? '';
        }

        //get from database
        $return_data = Get_user_data(
            [
                'email' => $email === '',
                'name'=> $name === '',
                'profile_img_name'=> $img_url === ''
            ],
            $requested_uid ?? ''
        );

        if($return_data !== null){
            if($name === '') $name = $return_data['name'] ?? '';
            if($email === '')$email = $return_data['email'] ?? '';
            if($img_url === '')$img_url = $return_data['profile_img_url'] ?? '';
        }


        $requested_uid = (int)$requested_uid;

        $request_email = $data['email'] ?? false;
        $request_email = (bool)$request_email;

        $request_name = $data['name'] ?? false;
        $request_name = (bool)$request_name;

        $request_img = $data['image_url'] ?? false;
        $request_img = (bool)$request_img;

        $data = [];
        if($request_name && $name !== '') $data['name'] = $name;
        if($request_email && $email !== '') $data['email'] = $email;
        if($request_img && $img_url !== '') $data['img_url'] = $img_url;

        if($data === []){
            \http_response_code(404);
            exit();
        }

        return $data;
    }

    /**
     * get user data: email, name, password[optional] from id
     * tb_user_columns::id_user->name, tb_user_columns::email->name, tb_user_columns::password->name
     * @param string $id if not specified, get current user data 
     * @param array{
     *      name?:bool,
     *      password?: bool,
     *      email?: bool,
     *      profile_img_name?:bool,
     * } $option decide if to include query to select
     * @return null|array{
     *      name?:string,
     *      password?: string,
     *      email?: string,
     *      profile_img_url?: string
     * }
     */
    function Get_user_data(array $option = [], string $id = "") : null | array{
        if($option === []) return null;
        $conn = null;
        try{
            $passwd = $option['password'] ?? false;
            $include_name = $option['name'] ?? false;
            $include_email = $option['email'] ?? false;
            $include_img = $option['profile_img_name'] ?? false;

            $selected_columns = [];
            if($passwd) $selected_columns[] = tb_user_columns::password->name;
            if($include_name) $selected_columns[] = tb_user_columns::name->name;
            if($include_email) $selected_columns[] = tb_user_columns::email->name;
            if($include_img) $selected_columns[] = tb_user_columns::profile_image_name->name;
            if($selected_columns===[]) return null;

            Get_user_data_authorized($passwd,$include_name,$include_email,$include_img);

            if($id===''){
                if(\session_status()!==PHP_SESSION_ACTIVE) throw new Exception('No active session');
                else {
                    if(!Is_logged()) return null;
                    $id = $_SESSION['uid'];
                }
            }




            $conn = Connect_database();
            if($conn === null) return null;    

            $conn->beginTransaction();

            $query = "SELECT " .
                \implode(", ",$selected_columns).
                " FROM ". All_tables::user->name. 
                " WHERE ". tb_user_columns::id->name ." = :uid"
            ;
            $smtm = $conn->prepare($query);
            if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

            $smtm->execute([':uid'=>$id]);

            if($smtm->rowCount() === 0){
                return null;
            }

            $row = $smtm->fetch(\PDO::FETCH_ASSOC);
            $result = [];

            if($passwd) $result['password'] = $row[tb_user_columns::password->name];
            if($include_name) $result['name'] = $row[tb_user_columns::name->name];
            if($include_email) $result['email'] = $row[tb_user_columns::email->name];
            if($include_img) $result['profile_img_url'] = '/' .URI_PREFIX .'/image/profile/' . $row[tb_user_columns::profile_image_name->name];

            $conn->commit();

            return $result;
        }catch(Throwable $e){
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
            Log_internal(
                new Error_code(Error_domain::database, Resource_code::db_query, Error_condition::unknown),
                Level::Error,
                'In Get_user_data failed: ' .$e->getMessage()
            );
            return null;
        }
    }



      /**
     * Exit immedially if not authorized:
     * http code 500
    */
    function Get_user_data_authorized(bool $password = false, bool $name = false, bool $email = false, bool $profile_img = false){
        $columns = [];

        if($password) $columns[] = tb_user_columns::password;
        if($name)  $columns[] = tb_user_columns::name;
        if($email)  $columns[] = tb_user_columns::email;
        if($profile_img)  $columns[] = tb_user_columns::profile_image_name;

        $select_auth = Is_query_permitted(HOST,[Query_type::select] ,All_tables::user, $columns);

        if(!$select_auth){
            $colums_denied = ' | Columns: '. \implode(', ', \array_map(fn($c)=>$c->name, $columns));
        
            Handle_error(
                new Error_code(Error_domain::database,Resource_code::db_select,Error_condition::unauthorized),
                Level::Critical,
                'Login failed: ' .All_tables::user->name. ' | user:'. HOST. $colums_denied
            );
        }
    }