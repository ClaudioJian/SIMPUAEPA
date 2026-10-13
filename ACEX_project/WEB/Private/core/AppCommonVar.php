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

<<<<<<< HEAD
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
        try{
            $dotenv->required([
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
            ])->notEmpty();

            $dotenv->required([
                'DATABASE_PORT',
                'SESSION_OBSOLETE_MAXLIFE',
                'SESSION_ACTIVE_TIME',
                'SESSION_ABSOLUTE_TIME',
                'CSRF_TOKEN_MAXLIFE',
                'REQUEST_MAX_LIFE'
            ])->isInteger();        
=======
    require_once __DIR__ . '/../Error/Error_manager.php';
    require_once __DIR__ . '/../Error/Error_code.php';
    use function ACEX_project\WEB\Private\Error\Handle_error;

use ACEX_project\WEB\Private\Core\Security\File_upload\User_file_storation_path;
use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Dotenv\Dotenv;

    require_once __DIR__ .'/Security/File_upload/File_upload_utils.php';
    
    use function \define;

    if(!\defined('INITIALIZED')) Initialize_value();


    function Initialize_value(){
        //Create directory if not exist
        if(
            !User_file_storation_path::Await_sanitation->Initialize_directory() ||
            !User_file_storation_path::Await_validation->Initialize_directory() ||
            !User_file_storation_path::profiles->Initialize_directory() ||
            !User_file_storation_path::Maps->Initialize_directory()
        ) {
            Handle_error(
                new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                Log_level::fatal,
                "Failed to create directory! Please check permission."
            );
        }
        $required_env = $required_int_env = $required_bool_env = [];


        $dotenv = Dotenv::createImmutable(__DIR__. "/../../",);
        $dotenv->safeLoad();
        try{
            $required_env[] = 'IMG_COMPRESS';
            $required_bool_env[] = 'IMG_COMPRESS';
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

            $dotenv->required($required_env)->notEmpty();

            if($required_int_env !== []) $dotenv->required($required_int_env)->isInteger();    
            if($required_bool_env !== []) $dotenv->required($required_bool_env)->isBoolean();      
>>>>>>> origin/BackEnd
        }catch(\Dotenv\Exception\ValidationException $e){
            Handle_error(
                new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                Log_level::fatal,
                $e->getMessage()
            );
        }


        define('IMG_COMPRESS', $_ENV['IMG_COMPRESS'] === "false" ? false : true);

        if(\defined('FILE_REWRITE') && FILE_REWRITE === true){
            define("IMG_PROFILE_FINAL_RESOLUTION",$_ENV['IMG_PROFILE_FINAL_RESOLUTION']);
            define("IMG_MAP_FINAL_RESOLUTION",$_ENV['IMG_MAP_FINAL_RESOLUTION']);
            define('IMG_FINAL_QUALITY',min(100,$_ENV['IMG_FINAL_QUALITY']));
        }else{
            define('PHP_PATH',$_ENV['PHP_PATH']);
            define('DB_NAME', $_ENV['DATABASE_NAME']);
            define('DB_PASSWORD', $_ENV['DATABASE_PASSWORD']);
            define('HOST', $_ENV['DATABASE_HOST']);
            define('SERVER_USER', $_ENV['DATABASE_USER']);
            define('DB_PORT', (int)$_ENV['DATABASE_PORT']);

<<<<<<< HEAD
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
=======
            define('SESSION_OBSOLETE_MAXLIFE', (int)$_ENV['SESSION_OBSOLETE_MAXLIFE']);
            define('SESSION_ACTIVE_TIME', (int)$_ENV['SESSION_ACTIVE_TIME']);
            define('SESSION_ABSOLUTE_TIME', (int)$_ENV['SESSION_ABSOLUTE_TIME']);

            define('CSRF_TOKEN_MAXLIFE', (int)$_ENV['CSRF_TOKEN_MAXLIFE']);
            define('CSRF_TOKEN_VALIDATE_METHOD', ['POST','PATCH','DELETE','PUT']);
            define('CSRF_TOKEN_HEADER_NAME', $_ENV['CSRF_TOKEN_HEADER_NAME']);
            define('CSRF_TOKEN_GENERATE_SEPARATOR', '__');

            define('REQUEST_MAX_LIFE',$_ENV['REQUEST_MAX_LIFE']);
            

            define('NONCE_SEP','#');

            define('UNSUPPORTED_METHOD',['CONNECT','TRACE']);
            define('VALID_HTTP_METHOD',['POST','GET','HEAD','CONNECT','TRACE','DELETE','PUT','PATCH']);

            //files
            define('FILE_UPLOAD_FIELD_NAME','userfile');
            define("IMG_MAP_MAX_SIZE",$_ENV["IMG_MAP_MAX_SIZE"]);
            define("IMG_PROFILE_MAX_SIZE",$_ENV['IMG_PROFILE_MAX_SIZE']);                
             

            $home_location = ltrim($_ENV['HOME_PAGE_LOCATION'],'/');
            define('HOME_PAGE_LOCATION',$home_location);
            if(!file_exists(__DIR__ . "/../../Public_html/".$home_location)){
                Handle_error(
                    new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                    Log_level::fatal,
                    "Missing home page - doesnt exist or not found[".__DIR__ . "/../../Public_html/".$home_location."]"
                );
            }
        }
        
        

        
>>>>>>> origin/BackEnd

        //avoid define/load env twice to opmization, note this is per request
        define('INITIALIZED',true);
    }