<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;





use Exception;
use Throwable;

require_once __DIR__ . "/../../Api/database/database_manager.php";

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\tb_user_columns;

require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;

require_once __DIR__ . "/login.php";


require_once __DIR__ . '/../session_manager.php';
use function ACEX_project\WEB\Private\Auth\New_session;

    require_once __DIR__ ."/Authentication.php";



    function Logout(){
        if(!Is_logged()) return;

        $response = Internal_Logout();
        if($response===null) return;

        header('Content-Type: '.Construct_MIME(MIME['application']['json']));
        http_response_code(200);
        echo json_encode([...$response,'reason'=>0,'success'=>0]);
    }



    /**
     * @return array{
     *   success: -1,
     *   logout_user_name: string,
     *   logout_user_email: string
     * } information of email and name of logout target user
     * | null if not logged
     */
    function Internal_Logout() : array | null{
        if(!Is_logged()) return null;
        $user_name = $email = '';
        $smtm = Get_user_data(false);

        if($smtm === null || $smtm->rowCount() === 0){
            $user_name = $email = 'unknown';
        }else{
            $row = $smtm->fetch(\PDO::FETCH_ASSOC);
            $user_name = $row[tb_user_columns::name_user->name];
            $email = $row[tb_user_columns::email->name];
        }

        $response = [
            'success'=>-1,
            'logout_user_name'=>$user_name,
            'logout_user_email'=>$email,
        ];
        
        New_session();
        unset($_SESSION['uid']);
        return $response;
    }
?>