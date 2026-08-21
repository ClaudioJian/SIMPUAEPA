<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core Configuration & Global Constants                                               |
  |                                                                                                 |
  | Initializes environment variables, defines database connection constants, and provides          |
  | global success status enums for operations.                                                     |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core;
    //here put some commun const can be used like database name
    // . PHP_EOL is a constant in PHP that represents the end of a line.
    require_once __DIR__ . '/../vendor/autoload.php';
    use Dotenv\Dotenv;
    
    $dotenv = Dotenv::createImmutable(__DIR__. "/../");
    $dotenv->load();

    define('DB_NAME',$_ENV['DATABASE_NAME']);
    define('DB_PASSWORD',$_ENV['DATABASE_PASSWORD']);
    define('HOST',$_ENV['DATABASE_HOST']);
    define('SERVER_USER','root');
    define('DB_PORT',$_ENV['DATABASE_PORT']);

    enum Sucess_code : int{
        case user_found = 15; 

        case db_insert = 52;
        case db_update = 53;
        case db_delete = 54;
        case db_select = 55;


    };
?>