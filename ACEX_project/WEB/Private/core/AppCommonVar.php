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
  | Sucess_code:enum                                                                                |
  | user_found, db_insert, db_update, db_delete, db_select                                          |
  | Constant per request: const                                                                     |
  | DB_NAME :string, DB_PASSWORD:string, HOST: string, SERVER_USER:string                           |
  | DB_PORT: int, SESSION_OBSOLETE_MAXLIFE:int, SESSION_ACTIVE_TIME: int                            |
  | CSRF_TOKEN_MAXLIFE: int, CSRF_TOKEN_VALIDATE_METHOD : array<string>                             |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core;
    require_once __DIR__ . '/../../vendor/autoload.php';

    use Dotenv\Dotenv;

    enum Sucess_code : int{
        case user_found = 15; 

        case db_insert = 52;
        case db_update = 53;
        case db_delete = 54;
        case db_select = 55;
    };

    if(!defined('INITIALIZED')) Initialize_value();


    function Initialize_value(){
        $dotenv = Dotenv::createImmutable(__DIR__. "/../../");
        $dotenv->load();
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
                'CSRF_TOKEN_HEADER_NAME'
            ])->notEmpty();

            $dotenv->required([
                'DATABASE_PORT',
                'SESSION_OBSOLETE_MAXLIFE',
                'SESSION_ACTIVE_TIME',
                'SESSION_ABSOLUTE_TIME',
                'CSRF_TOKEN_MAXLIFE'
            ])->isInteger();        
        }catch(\Dotenv\Exception\ValidationException $e){
            http_response_code(500);
            exit("Server misconfiguration". $e->getMessage());
        }

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

        define('UNSUPPORTED_METHOD',['CONNECT','TRACE']);
        define('VALID_HTTP_METHOD',['POST','GET','HEAD','CONNECT','TRACE','DELETE','PUT','PATCH']);


        //avoid define/load env twice to opmization, note this is per request
        define('INITIALIZED',true);
    }
?>