<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core Configuration & Global Constants                                               |
  |                                                                                                 |
  | Initializes environment variables, defines database connection constants, and provides          |
  | global success status enums for operations.                                                     |
  | you should require this file if need these constraint                                           |
  | WARNING: when require this, if env file is malconfigured, return http code 500                  |
  |                                                                                                 |
  |                                        Table of content                                         |                                                                               
  | user_found, db_insert, db_update, db_delete, db_select                                          |
  | Constant per request: const                                                                     |
  | DB_NAME :string, DB_PASSWORD:string, HOST: string, SERVER_USER:string                           |
  | DB_PORT: int, SESSION_OBSOLETE_MAXLIFE:int, SESSION_ACTIVE_TIME: int                            |
  | CSRF_TOKEN_MAXLIFE: int, CSRF_TOKEN_VALIDATE_METHOD : array<string>                             |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core;
    require_once __DIR__ . '/../../vendor/autoload.php';

use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Log_level;
use ACEX_project\WEB\Private\Error\Resource_code;
use Dotenv\Dotenv;

use function ACEX_project\WEB\Private\Error\Handle_error;


    if(!defined('INITIALIZED')) Initialize_value();

    function Initialize_value(){
        $dotenv = Dotenv::createImmutable(__DIR__. "/../../",[".env",'.env.development','.env.production']);
        $dotenv->safeLoad();

        $app_log_fname = $_ENV["LOG_FILE_NAME"] ?? "SIUMP_log";
        define("LOG_FILE_NAME", $app_log_fname);

        define("LOG_DB_NAME", $_ENV["LOG_DB_NAME"] ?? LOG_FILE_NAME);
        define("LOG_REQUEST_NAME", $_ENV["LOG_REQUEST_NAME"] ?? LOG_FILE_NAME);

        $php_log_name = $_ENV['LOG_PHP_ERROR'] ?? '';
        define("LOG_PHP_ERROR",empty($php_log_name) ? "php_errors": $php_log_name);

        if($php_log_name !== '') ini_set("error_log",File_storation_path::Error_logs->value . DIRECTORY_SEPARATOR . LOG_PHP_ERROR . ".log");

        try{
            if(\defined('FILE_REWRITE') && FILE_REWRITE === true){
                $required_env =  
                [
                    "IMG_PROFILE_FINAL_RESOLUTION",
                    'IMG_MAP_FINAL_RESOLUTION',
                    'IMG_FINAL_QUALITY',
                ];
                $required_int_env = [
                    'IMG_FINAL_QUALITY'
                ];
            }else{
                $required_env = [
                    'DATABASE_NAME',
                    'DATABASE_PASSWORD',
                    'DATABASE_HOST',
                    'DATABASE_USER',
                    'DATABASE_PORT',
                    'SESSION_OBSOLETE_MAXLIFE',
                    'SESSION_ACTIVE_TIME',
                    'SESSION_ABSOLUTE_TIME',
                    'CSRF_TOKEN_MAXLIFE',
                    'CSRF_TOKEN_HEADER_NAME',
                    'REQUEST_MAX_LIFE',
                    'HOME_PAGE_LOCATION',
                    'PHP_PATH',
                    'IMG_PROFILE_MAX_SIZE',
                    'IMG_MAP_MAX_SIZE',
                ];
                $required_int_env = [
                    'DATABASE_PORT',
                    'SESSION_OBSOLETE_MAXLIFE',
                    'SESSION_ACTIVE_TIME',
                    'SESSION_ABSOLUTE_TIME',
                    'CSRF_TOKEN_MAXLIFE',
                    'REQUEST_MAX_LIFE',
                ];
            }

            //global
            $required_env = [...$required_env,'IMG_COMPRESS','LOG_FILE_NAME','LOG_PHP_ERROR'];
            $required_bool_env = [...$required_bool_env,'IMG_COMPRESS'];

            $dotenv->required($required_env)->notEmpty();

            if($required_int_env !== []) $dotenv->required($required_int_env)->isInteger();    
            if($required_bool_env !== []) $dotenv->required($required_bool_env)->isBoolean();      
        }catch(\Dotenv\Exception\ValidationException $e){
            Handle_error(
                new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                Log_level::fatal,
                $e->getMessage()
            );
        }

        //global
        define('IMG_COMPRESS', $_ENV['IMG_COMPRESS'] === "false" ? false : true);


        define('CSRF_TOKEN_MAXLIFE', (int)$_ENV['CSRF_TOKEN_MAXLIFE']);
        define('CSRF_TOKEN_VALIDATE_METHOD', ['POST','PATCH','DELETE','PUT']);
        define('CSRF_TOKEN_HEADER_NAME', $_ENV['CSRF_TOKEN_HEADER_NAME']);
        define('CSRF_TOKEN_GENERATE_SEPARATOR', '__');

        define('REQUEST_MAX_LIFE',$_ENV['REQUEST_MAX_LIFE']);
        define('NONCE_SEP','#');

        define('UNSUPPORTED_METHOD',['CONNECT','TRACE']);
        define('VALID_HTTP_METHOD',['POST','GET','HEAD','CONNECT','TRACE','DELETE','PUT','PATCH']);

        define('FILE_UPLOAD_FIELD_NAME','userfile');

        $home_location = ltrim($_ENV['HOME_PAGE_LOCATION'],'/');
        

        define('HOME_PAGE_LOCATION',$home_location);
        if(!file_exists(__DIR__ . "/../../Public_html/".$home_location)){
            Handle_error(
                new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                Log_level::fatal,
                "Missing home page - doesnt exist or not found[".__DIR__ . "/../../Public_html/".$home_location."]"
            );
        }

        //avoid define/load env twice to opmization, note this is per request
        define('INITIALIZED',true);
    }
?>