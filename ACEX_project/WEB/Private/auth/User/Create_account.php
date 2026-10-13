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

use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Database_simple_insert;
use function ACEX_project\WEB\Private\Api\database\Handle_query_fail;

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;

require_once __DIR__ . "/../../Api/database/Authorization_manager.php";
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;

require_once __DIR__ . "/../../Api/RedirectUtils.php";
use function ACEX_project\WEB\Private\Api\Redirect;

require_once __DIR__ . "/../../Api/NonceRequest.php";
use function ACEX_project\WEB\Private\Api\Require_nonce;

require_once __DIR__ . "/../password_handler.php";
use function ACEX_project\WEB\Private\Auth\Secure_hash_password;

require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;

require_once __DIR__ . "/../../Core/Security/Request_manager.php";
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;

require_once __DIR__ . "/../../Error/Error_manager.php";
use function ACEX_project\WEB\Private\Error\Handle_error;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Log_level;
use ACEX_project\WEB\Private\Error\Resource_code;

require_once __DIR__ ."/login.php";
require_once __DIR__ ."/Authentication.php";

  /**
   * when redirect involved, may have no body so you should store elsewhere and could put in passed parameter. if no data supplied, it will try to get from request body.
   */
  function Create_account(array $request_body=[]){
    $conn = null;
    $id = null;
    try{
      if(Is_logged()) Redirect();
      Require_nonce();

      if($request_body===[]) $request_body = Get_request_body();


      $email = $request_body['email'] ?? '';
      $user_name = $request_body['name'] ?? '';
      $password = $request_body['password'] ?? '';

      Validate_enroll_data($email,$password,$user_name);
      Is_enroll_authorized();
      $password = Secure_hash_password($password);


      Exit_if_duplicated($email,$user_name);

      $id_list = Database_simple_insert(
        [[
          'table' => All_tables::user,
          'insert' => [
              [
                'column' => tb_user_columns::email,
                'value' => [$email]
              ],
              [
                'column' => tb_user_columns::name_user,
                'value' => [$user_name]
              ],
              [
                'column' => tb_user_columns::password,
                'value' => [$password]
              ]
            ]
          ]]);

      if($id_list===false) http_response_code(500);
      else
      foreach($id_list as $uid){
        if($uid['table'] === All_tables::user) {
          $id = (int)$uid['id'];
          break;
        }
      }
      if($id===null) throw new Exception('in Create_account(): Cannot find last id inserted');
    }catch(Throwable $e){
      Handle_query_fail($e,$conn);
    }

    Internal_login($id);

    header('Content-Type: '.Construct_MIME(MIME['application']['json']));
    echo json_encode(['success'=>0,'uid'=>$id]);
    http_response_code(201);
    exit();
  }

  /**
   * Exit immedially if find duplicate and return json response, not may still have race condition so be sure insert is upsert.
   */
  function Exit_if_duplicated(string $email,string $name) : void{
    $query = "SELECT
        EXISTS (
            SELECT 1
            FROM " . All_tables::user->name . "
            WHERE " . tb_user_columns::email->name . " = :email
        ) AS email_exist,
        EXISTS (
            SELECT 1
            FROM " . All_tables::user->name . "
            WHERE " . tb_user_columns::name_user->name . " = :user_name
        ) AS uname_exist
      ";
      $conn = Connect_database();
      if($conn === null) {
        http_response_code(500);
        echo json_encode(['success'=>-3]);
      }
      
      $smtm = $conn->prepare($query);
      if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

      $smtm->execute([
        ':email'=>$email,
        ':user_name'=>$name]);

      $row = $smtm->fetch(\PDO::FETCH_ASSOC);

      $email_exist = $row['email_exist'];
      $name_exist = $row['uname_exist'];
      if($email_exist || $name_exist){
        http_response_code(400);
        header('Content-Type: '.Construct_MIME(MIME['application']['json']));
        echo json_encode(['success'=>-1,'name'=> $name_exist? 2 : 4,'email'=> $email_exist? 2 : 4,'password'=>4]);
        exit();
      }
  }

  /**
   * Exit immedially if not authorized:
   * http code 500
   */
  function Is_enroll_authorized(){
    $query = Resource_code::db_select;
    $colums_denied = '';

    $select_auth = Is_query_permitted(HOST,[Query_type::select] , All_tables::user);
    $insert_auth = Is_query_permitted(HOST, [Query_type::insert] ,All_tables::user, [tb_user_columns::email,tb_user_columns::name_user,tb_user_columns::password]);

    if(!$select_auth || !$insert_auth){
      if(!$insert_auth) {
        $query = Resource_code::db_insert;
        $colums_denied = ' | Columns: email, name, password';
      }

      Handle_error(
        new Error_code(Error_domain::database,$query,Error_condition::unauthorized),
        Log_level::fatal,
        'Account enrollment failed: table: ' .All_tables::user->name. ' | user:'. HOST. $colums_denied
      );
    }
  }
?>