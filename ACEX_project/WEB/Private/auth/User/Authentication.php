<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;

use ACEX_project\API\Input_ruleset;
use ACEX_project\WEB\Private\Core\Data_type;
use Throwable;


require_once __DIR__ . "/../../Auth/password_handler.php";
use function ACEX_project\WEB\Private\Auth\Strong_password;

require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;
use function ACEX_project\WEB\Private\Core\Send_mime_type;

require_once __DIR__ . "/../../Error/Error_manager.php";
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

require_once __DIR__ . "/../../../../API/Field_processor.php";
require_once __DIR__ . "/../../Core/Data_type.php";

require_once __DIR__ . '/../session_manager.php';


  /**
   * @param bool $login true for create account, else login
   */
  function Enroll_ruleset(bool $login = true){
    /* $name and $email -> 0 success, 1 too long, 2 duplicated, 3 missing, 4 not validated
    $password -> 0 success, 1 too weak, 2 missing, 3 not validated*/

    $type_txt = $login ? 'Login' : 'Account enrollment';
    $ruleset = 
    [
      [
        'key' => 'email',
        'instruction'=> new Input_ruleset(
            Data_type::string,
            validation_fn: function(?string $val, ?string& $err_msg, ?int &$custom_err){
              if(!filter_var($val,FILTER_VALIDATE_EMAIL)) {
                $custom_err = 1;
                $err_msg = "invalid";
                return false;
              }

              return true;
            }
        )
      ],
      [
        'key' => 'password',
        'instruction'=> new Input_ruleset(
            Data_type::string,
            validation_fn: function(?string $val, ?string& $err_msg, ?int &$custom_err) {
              if(Strong_password($val)) return true;

              $custom_err = 1;
              $err_msg = "too weak";
              return false;
            }
        )
      ]
    ];

    if(!$login) $ruleset[] = [
      'key' => 'name',
      'instruction'=> new Input_ruleset(
        Data_type::string,
        validation_fn: function(?string $val, ?string& $err_msg, ?int &$custom_err){
          $lenght = \mb_strlen($val,ENCODING_CHARSET);
          if($lenght <= MAX_USER_NAME_LENGTH) return true;

          $custom_err = 1;
          $err_msg = "exceed limit of lenght[input: {$lenght} | limit: {". MAX_USER_NAME_LENGTH .'}]';
          return false;
        }
      )
    ];


    return $ruleset;
  }

  function Enroll_err_handler(int $code, string $key, string $err_msg, int $custom_err, bool $login = false){
    /* $name and $email -> 0 success, 1 too long/invalid, 2 duplicated, 3 missing, 4 not validated
    $password -> 0 success, 1 too weak, 2 missing, 3 not validated*/
    $response = ['success'=>-1, 'email'=>4,'password'=>4];
    if(!$login) $response['name'] = 4;

    $type = ($login ? 'Login' : 'Account enrollment') . " failed -";

    $msg = '';
    switch($code){
      case Input_ruleset::EMPTY_VALUE:
        $response[$key] = 3;
        $msg = "$type because: [$key missing from request data]";
        break;
      case Input_ruleset::INVALID_TYPE:
        $msg = "$type bacause: [$key has no required data type]";
        $response[$key] = 1;
        break;
      case Input_ruleset::SANITIZATION_FAILED:
      case Input_ruleset::INVALID_VALUE:
        $response[$key] = $custom_err;
        $msg = $err_msg;
        break;
    };

    $ip = Get_IP_address_txt();

    Log_internal(new Error_code(Error_domain::request, Resource_code::credential, Error_condition::failed),
      Level::Info,
      "[IP - \"$ip\"] $msg"
    );

    $json = \json_encode($response);
    Send_mime_type(MIME['application']['json']);
    \http_response_code(400);
    
    echo $json;
    exit();
  }