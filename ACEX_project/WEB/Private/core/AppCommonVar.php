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

    require_once __DIR__ .'/File_storation_map.php';

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Dotenv\Dotenv;
    use Monolog\Level;
    use function ACEX_project\WEB\Private\Error\Handle_error;

    require_once __DIR__ . '/../Error/Error_manager.php';
    require_once __DIR__ . '/../Error/Error_code.php';

    require_once __DIR__ .'/Security/File_upload/File_upload_utils.php';
    require_once __DIR__ . '/Parse_time.php';

    
    use function \define;
    if(!\defined('INITIALIZED')) Initialize_value();

    function Initialize_value(){
        //Create directory if not exist
        if(
            !File_storation_path::Await_sanitation->Initialize_directory() ||
            !File_storation_path::Await_validation->Initialize_directory() ||
            !File_storation_path::profiles->Initialize_directory() ||
            !File_storation_path::Maps->Initialize_directory() ||
            !File_storation_path::Error_logs->Initialize_directory() ||
            !File_storation_path::Rate_track_ip->Initialize_directory() ||
            !File_storation_path::Rate_track_account->Initialize_directory()
        ) {
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::directory,Error_condition::missing),
                Level::Critical,
                "Failed to create directory! Please check permission."
            );
        }
        $required_env = $required_int_env = $required_bool_env = [];


        $dotenv = Dotenv::createImmutable(__DIR__. "/../../",);
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
                    'RATE_FILE_MAX_QUANT',
                    'RATE_IP_MINIUM_INTERVAL',
                    'RATE_IP_MAX_REQUEST_PER_INTERVAL',
                    'RATE_RECORD_EXPIRATION',
                    'RATE_IP_LOGIN_MAX_ATTEMPT',
                    'RATE_IP_LOGIN_PENALITY',
                    'RATE_IP_LOGIN_CLEANUP_TIME',
                    'RATE_ACCOUNT_LOGIN_MINIUM_INTERVAL',
                    'RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL',
                ];
                $required_int_env = [
                    'DATABASE_PORT',
                    'SESSION_OBSOLETE_MAXLIFE',
                    'SESSION_ACTIVE_TIME',
                    'SESSION_ABSOLUTE_TIME',
                    'CSRF_TOKEN_MAXLIFE',
                    'REQUEST_MAX_LIFE',
                    'RATE_FILE_MAX_QUANT',
                    'RATE_IP_LOGIN_MAX_ATTEMPT',
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
                Level::Critical,
                $e->getMessage()
            );
        }

        //global
        define('IMG_COMPRESS', $_ENV['IMG_COMPRESS'] === "false" ? false : true);



        //child rewriter
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
             
            //rate limit
            $rate_fquant = (int)$_ENV['RATE_FILE_MAX_QUANT'];
            if($rate_fquant <=0) $rate_fquant = 1;
            define('RATE_FILE_MAX_QUANT',$rate_fquant);

            /*** In ms*/
            define('RATE_IP_MINIUM_INTERVAL',Parse_time($_ENV['RATE_IP_MINIUM_INTERVAL']));
            /*** In ms*/
            define('RATE_RECORD_EXPIRATION',Parse_time($_ENV['RATE_RECORD_EXPIRATION']));

            Define_RATE_IP_MAX_REQUEST_PER_INTERVAL();

            /*** maxium number of failed login attempt one ip can do */
            define('RATE_IP_LOGIN_MAX_ATTEMPT',(int)$_ENV['RATE_IP_LOGIN_MAX_ATTEMPT']);
            /*** time of penality when hit login rate limit in ms */
            define('RATE_IP_LOGIN_PENALITY',Parse_time($_ENV['RATE_IP_LOGIN_PENALITY']));
            /*** time for clean failed login attempt on single ip in ms */
            define('RATE_IP_LOGIN_CLEANUP_TIME',Parse_time($_ENV['RATE_IP_LOGIN_CLEANUP_TIME']));
            /*** minium interval between failed login from same account */
            define('RATE_ACCOUNT_LOGIN_MINIUM_INTERVAL',Parse_time($_ENV['RATE_ACCOUNT_LOGIN_MINIUM_INTERVAL']));
            /** maxium number of login can be done in one interval for same account */
            Define_RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL();




            $home_location = ltrim($_ENV['HOME_PAGE_LOCATION'],'/');
            define('HOME_PAGE_LOCATION',$home_location);
            if(!file_exists(__DIR__ . "/../../Public_html/".$home_location)){
                Handle_error(
                    new Error_code(Error_domain::configuration,Resource_code::env,Error_condition::missing),
                    Level::Critical,
                    "Missing home page - doesnt exist or not found[".__DIR__ . "/../../Public_html/".$home_location."]"
                );
            }
        }
        
        

        

        //avoid define/load env twice to opmization, note this is per request
        define('INITIALIZED',true);
    }

    function Define_RATE_IP_MAX_REQUEST_PER_INTERVAL(){
        $raw_text = $_ENV['RATE_IP_MAX_REQUEST_PER_INTERVAL'];
        $ip_rate_arr = explode(':',$raw_text);
        if(\count($ip_rate_arr)!==2) {
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::format),
                Level::Critical,
                "format of RATE_IP_MAX_REQUEST_PER_INTERVAL from .env is invalid, should be number:time but $raw_text found"
            );
        }

        $max_request = \intval($ip_rate_arr[0] <=0 ? 1 : $ip_rate_arr[0]);
        $interval = Parse_time($ip_rate_arr[1]);

        /**
         * @var array{max_request:int,interval:int} in ms
         */
        define('RATE_IP_MAX_REQUEST_PER_INTERVAL',['max_request'=>$max_request,'interval'=>$interval]);
    }

    function Define_RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL(){
        $raw_text = $_ENV['RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL'];
        $acc_rate_arr = explode(':',$raw_text);
        if(\count($acc_rate_arr)!==2) {
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::format),
                Level::Critical,
                "format of RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL from .env is invalid, should be number:time but $raw_text found"
            );
        }

        $max_request = \intval($acc_rate_arr[0] <=0 ? 1 : $acc_rate_arr[0]);
        $interval = Parse_time($acc_rate_arr[1]);

        /**
         * @var array{max_request:int,interval:int} in ms
         */
        define('RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL',['max_request'=>$max_request,'interval'=>$interval]);
    }

    function Get_IP_address_txt() : string{
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $extra_info = [];
        if($ip !== '' && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) $extra_info[] = 'IPV6';
        else if($ip === '' || !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) $ip = 'invalid'; 

        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){ 
            $extra_info[] = 'Proxy involved';
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        $extra_txt = $extra_info === [] ? '' : '('.implode(' | ',$extra_info).')';
        return $extra_txt.$ip;
    }