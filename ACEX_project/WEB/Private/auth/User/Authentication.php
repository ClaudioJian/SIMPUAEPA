<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;


use Throwable;


require_once __DIR__ . "/../../Auth/password_handler.php";
use function ACEX_project\WEB\Private\Auth\Strong_password;

require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;


require_once __DIR__ . "/../../Error/Error_manager.php";
use function ACEX_project\WEB\Private\Error\Handle_error;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;

require_once __DIR__ . '/../session_manager.php';

  /**
   * Exit immedially if invalid and Respond with :
   * http code 400
   * $name and $email -> 0 success, 1 too long, 2 duplicated, 3 missing, 4 not validated
   * $password -> 0 success, 1 too weak, 2 missing, 3 not validated
   * @param string|null $name if not specified, is considered as login and not create, to avoid enroll mistaken, you can pass as empty string
   */
  function Validate_enroll_data(string $email,string $password,string|null $name = null) : void{
    //0 success, 1 too long, 2 duplicated, 3 missing, 4 not validated for name and email
    //0 success, 1 too weak, 2 missing, 3 not validated for password
    $response = ['name'=>4,'email'=>4,'password'=>4];
    $err_txt = "Account enrollment";
    if($name !== null){
        $response['name'] = 4;
    }else $err_txt = "Login";

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
          $response['password'] = 3;
          $missing_txt .= ' password';
        }
        
        Handle_error(
          new Error_code(Error_domain::request,Resource_code::credential,Error_condition::missing),
          err_msg: $err_txt . ' failed: '. $missing_txt . ' is/are missing from request data'
        );
      }

      if(!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        $response['email'] = 1;
        Handle_error(
          new Error_code(Error_domain::request,Resource_code::credential,Error_condition::missing),
          err_msg: $err_txt .' failed: invalid email'
        );
      }

      if(!Strong_password($password)) {
        $response['password'] = 1;
        Handle_error(
          new Error_code(Error_domain::request,Resource_code::credential,Error_condition::missing),
          err_msg: $err_txt . ' failed: password is too weak'
        );
      }
    }catch(Throwable $e){
      $response['success'] = -1;
      $json = json_encode($response);
      header('Content-Type: '.Construct_MIME(MIME['application']['json']));
      http_response_code(400);
      
      echo $json;
      
      exit();
    }
  }


?>