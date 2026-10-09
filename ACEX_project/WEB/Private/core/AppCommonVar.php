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

    require_once __DIR__ . '/../Error/Error_manager.php';
    require_once __DIR__ . '/../Error/Error_code.php';
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
                'PHP_PATH',
                'FILE_UPLOAD_VALIDATION_MAX_LIFE'
            ])->notEmpty();

            $dotenv->required([
                'DATABASE_PORT',
                'SESSION_OBSOLETE_MAXLIFE',
                'SESSION_ACTIVE_TIME',
                'SESSION_ABSOLUTE_TIME',
                'CSRF_TOKEN_MAXLIFE',
                'REQUEST_MAX_LIFE',
                'FILE_UPLOAD_VALIDATION_MAX_LIFE'
            ])->isInteger();        
        }catch(\Dotenv\Exception\ValidationException $e){
            Handle_error(
                new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                Log_level::fatal,
                $e->getMessage()
            );
        }
        define('PHP_PATH',$_ENV['PHP_PATH']);

        define('DB_NAME', $_ENV['DATABASE_NAME']);
        define('DB_PASSWORD', $_ENV['DATABASE_PASSWORD']);
        define('HOST', $_ENV['DATABASE_HOST']);
        define('SERVER_USER', $_ENV['DATABASE_USER']);
        define('DB_PORT', (int)$_ENV['DATABASE_PORT']);

        define('SESSION_OBSOLETE_MAXLIFE', (int)$_ENV['SESSION_OBSOLETE_MAXLIFE']);
        define('SESSION_ACTIVE_TIME', (int)$_ENV['SESSION_ACTIVE_TIME']);
        define('SESSION_ABSOLUTE_TIME', (int)$_ENV['SESSION_ABSOLUTE_TIME']);

        define('CSRF_TOKEN_MAXLIFE', (int)$_ENV['CSRF_TOKEN_MAXLIFE']);
        define('CSRF_TOKEN_VALIDATE_METHOD', ['POST','PATCH','DELETE','PUT']);
        define('CSRF_TOKEN_HEADER_NAME', $_ENV['CSRF_TOKEN_HEADER_NAME']);
        define('CSRF_TOKEN_GENERATE_SEPARATOR', '__');

        define('REQUEST_MAX_LIFE',$_ENV['REQUEST_MAX_LIFE']);
        define('FILE_UPLOAD_VALIDATION_MAX_LIFE', $_ENV['FILE_UPLOAD_VALIDATION_MAX_LIFE']);
        

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