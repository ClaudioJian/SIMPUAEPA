<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;






use Throwable;

require_once __DIR__ . "/../../Api/database/database_manager.php";
use function ACEX_project\WEB\Private\Api\database\Database_simple_insert;

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;

require_once __DIR__ . "/../../Api/database/Authorization_manager.php";
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;

require_once __DIR__ . "/../../Api/NonceRequest.php";
use function ACEX_project\WEB\Private\Api\Require_nonce;

require_once __DIR__ . "/../../Auth/password_handler.php";
use function ACEX_project\WEB\Private\Auth\Secure_hash_password;
use function ACEX_project\WEB\Private\Auth\Strong_password;

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


  /**
   * when redirect involved, may have no body so you should store elsewhere and could put in passed parameter. if no data supplied, it will try to get from request body.
   */
  function Create_account(array $request_body=[]){
    Require_nonce();

    if($request_body===[]) $request_body = Get_request_body();


    $email = $request_body['email'] ?? '';
    $user_name = $request_body['name'] ?? '';
    $password = $request_body['password'] ?? '';

    Validate_enroll_data($user_name,$email,$password);
    Is_enroll_authorized();
    $password = Secure_hash_password($password);

    //check duplicate from database
    //not implement yet

    $sucess = Database_simple_insert(
      [
        [
          'table' => All_tables::user,
          'insert' => [
            [
              'column' => tb_user_columns::email,
              'value' => $email
            ],
            [
              'column' => tb_user_columns::name_user,
              'value' => $user_name
            ],
            [
              'column' => tb_user_columns::password,
              'value' => $password
            ]
          ]
        ]
      ]
    );
    if(!$sucess) http_response_code(500);
    exit();
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

  /**
   * Exit immedially if invalid and Respond with :
   * http code 400
   * $name and $email -> 0 sucess, 1 too long, 2 duplicated, 3 missing, 4 not validated
   * $password -> 0 sucess, 1 too weak, 2 missing, 3 not validated
   */
  function Validate_enroll_data(string $name,string $email,string $password) : void{
    //0 sucess, 1 too long, 2 duplicated, 3 missing, 4 not validated for name and email
    //0 sucess, 1 too weak, 2 missing, 3 not validated for password
    $response = ['name'=>4,'email'=>4,'password'=>3];

    try{
      if(($email === '' || $name === '' || $password === '')) {

        $missing_txt = '';
        if($name === '') {
          $response['name'] = 3;
          $missing_txt .= ' name';
        }
        if($email === '') {
          $response['email'] = 3;
          $missing_txt .= ' email';
        }
        if($password === '') {
          $response['password'] = 2;
          $missing_txt .= ' password';
        }
        
        Handle_error(
          new Error_code(Error_domain::request,Resource_code::credential,Error_condition::missing),
          err_msg: 'Account enrollment failed: '. $missing_txt . ' is/are missing from request data'
        );
      }

      if(!Strong_password($password)) {
        Handle_error(
          new Error_code(Error_domain::request,Resource_code::credential,Error_condition::missing),
          err_msg: 'Account enrollment failed: password is too weak'
        );
      }
    }catch(Throwable $e){
      $json = json_encode($response);
      header('Content-Type: '.Construct_MIME(MIME['application']['json']));
      http_response_code(400);
      
      echo $json;
      
      exit();
    }
  }




?>