<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;





use ACEX_project\WEB\Private\Error\Error_code;
use Exception;
use Throwable;

require_once __DIR__ . "/../../Api/database/database_manager.php";
use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Handle_query_fail;

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Core\Security\Rate_bucket;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;

require_once __DIR__ . "/../../Core/Security/Request_manager.php";
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;

require_once __DIR__ . '/../session_manager.php';
use function ACEX_project\WEB\Private\Auth\New_session;

require_once __DIR__ . '/../password_handler.php';
use function ACEX_project\WEB\Private\Auth\Secure_password_verify;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;

require_once __DIR__ .'/../../Error/Error_manager.php';

require_once __DIR__ .'/../../Error/Error_code.php';
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

require_once __DIR__ . "/User_data.php";


    function Login(?Rate_limiter $IP_rate_limiter,array $request_body = []){
        if(Is_logged()) {
            \header('Content-type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode(['success'=>1,'uid'=>$_SESSION['uid']]);
            exit();
        }

        $ip_txt = Get_IP_address_txt();
        
        $conn = null;
        try{
            if($request_body===[]) $request_body = Get_request_body();

            $email = $request_body['email'] ?? '';
            $password = $request_body['password'] ?? '';

            Log_internal(
                new Error_code(Error_domain::request,Resource_code::user,Error_condition::attempt),
                Level::Notice,
                "[IP-\"$ip_txt\"] Login attempt: email=[$email]"
            );

            $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
            if($ip === null){
                \http_response_code(400);
                Log_internal(
                    new Error_code(Error_domain::request,Resource_code::user,Error_condition::attempt),
                    Level::Notice,
                    "Invalid [IP-\"$ip\"]"
                );
                exit();
            }
            
            $first_fail = true;
            $key = hexdec(substr(hash('xxh32', $ip), 0, 8));
            $result = IP_login_at_limit($IP_rate_limiter, $key,$first_fail);

            Validate_enroll_data($email,$password);
            Get_user_data_authorized();

            $msg = "";
            $uid = Validate_login_credential($email,$password,$msg);
            if($uid === false){
                Log_internal(
                    new Error_code(Error_domain::authentication, Resource_code::request, Error_condition::failed),
                    Level::Info,
                    "[IP-\"$ip_txt\"] Login attemption failed: Reason - [$msg], Email=[$email]"
                );

                if($IP_rate_limiter !== null) IP_failed_login_update($IP_rate_limiter, $key, $result, $first_fail);
                Account_rate_limiter($email);
                exit();
            }

            Internal_login($uid);
        }catch(Throwable $e){
            Handle_query_fail($e,$conn);
        }

        $uid = $_SESSION['uid'];
        \header('Content-type: '.Construct_MIME(MIME['application']['json']));
        echo json_encode(['success'=>0,'uid'=>$uid]);

        Log_internal(
            new Error_code(Error_domain::request,Resource_code::user,Error_condition::success),
            Level::Notice,
            "[IP-\"$ip_txt\"] Logged sucessfully: user_id=[$uid]"
        );

        exit();
    }

    /**
     * If failed, return false with all data send(just need exit and do other thing)
     * @param string $email
     * @param string $raw_passwd
     * @throws Exception
     * @return int|false
     */
    function Validate_login_credential(string $email, string $raw_passwd, string &$msg):int|false{
        $response = ['success'=>0,'email'=>4,'password'=>4];
        $conn = Connect_database();
        if($conn === null) {
            Handle_error(new Error_code(Error_domain::credential,Resource_code::db_select,Error_condition::failed),
                Level::Critical,
                "Validatiation failed because cannot connect to database"
            );
        }

        $query = "SELECT " .
            \implode(", ",[tb_user_columns::id_user->name, tb_user_columns::email->name, tb_user_columns::password->name]).
            " FROM ". All_tables::user->name. 
            " WHERE ". tb_user_columns::email->name ." = :email"
        ;

        $smtm = $conn->prepare($query);
        if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

        $smtm->execute([':email'=>$email]);

        if($smtm->rowCount()<1) {
            \http_response_code(400);
            \header('Content-Type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode([...$response,'email'=>2,'success'=>-1]);
            $msg = "Email not registered in database";
            return false;
        }

        $row = $smtm->fetch(\PDO::FETCH_ASSOC);
        $stored_passwd = $row[tb_user_columns::password->name];

        if(!Secure_password_verify($raw_passwd,$stored_passwd)){
            \http_response_code(400);
            \header('Content-Type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode([...$response,'success'=>-1,'password'=>2]);
            $msg = "Invalid password";
            return false;
        }

        return $row[tb_user_columns::id_user->name];
    }

    /**
     * Check whether singular ip login failed attempt exceed limit or is still penalized, if true, return 429 to client else return false or null.
     * The cleanup for record won't triggered if still in penalitation(unless record itself is expired).
     * @param Rate_limiter $IP_rate_limiter
     * @param int $key
     * @return bool|null false when not hit limit else null when is completly new record. True when error happen
     */
    function IP_login_at_limit(Rate_limiter $IP_rate_limiter, int $key, bool &$first_fail) : bool|null{
        $first_fail = true;
        try{        
            $result = $IP_rate_limiter->At_limit($key,
                function(array &$data) use (&$first_fail){
                    $cleanup_time = RATE_IP_LOGIN_CLEANUP_TIME;
                    $current_t = (int)(\microtime(true) * 1000);

                    $failed_login_at = $data['failed_login_at'] ?? null;
                    if($failed_login_at === null) return false;
                    

                    //null mean it never penalized at all
                    $penality_at = $data['penality_at'] ?? null;
                    $ip = Get_IP_address_txt();

                    $penality_time = RATE_IP_LOGIN_PENALITY;
                    if($penality_at !== null && ($current_t - $penality_at) < $penality_time) {
                        $msg = "Current IP Login rate limit exceeded: penalty active [duration: {$penality_time} ms](start at: {$penality_at})";
                        Log_internal(
                            new Error_code(Error_domain::authentication, Resource_code::rate, Error_condition::surpass_limit),
                            Level::Info,
                            "[IP-\"$ip\"] $msg"
                        );
                        return true;
                    }


                    if(($current_t - $failed_login_at) >= $cleanup_time
                        || $penality_at !== null
                    ) {
                        unset(
                            $data['failed_login'],
                            $data['failed_login_at'],
                            $data['penality_at']
                        );
                        return false;
                    }    
                    
                    $first_fail = false;

                    $failed_login_count = $data['failed_login'] ?? 0;

                    $limit = RATE_IP_LOGIN_MAX_ATTEMPT;
                    if($failed_login_count >= $limit) {
                        $data['penality_at'] = $current_t;
                        $msg = $data['failed_login']." failed login attempt exceeded within cleanup time $cleanup_time ms(limit: $limit)";

                        Log_internal(
                            new Error_code(Error_domain::authentication, Resource_code::rate, Error_condition::surpass_limit),
                            Level::Info,
                            "[IP-\"$ip\"] $msg"
                        );
                        return true;
                    }
                
                    return false;
                }
            );

            //new record or not expired
            if($result !== true) return $result;

            \http_response_code(429);
            $IP_rate_limiter->Push();
            exit();
        }catch(Throwable $e){
            $IP_rate_limiter->Release();
            return true;
        }
    }

    /**
     * Update record of current ip. If record not exist, create new record else update it.
     * @param Rate_limiter $IP_rate_limiter
     * @param null|false $status false if record exist else if null then record don't exist at all
     * @return void
     */
    function IP_failed_login_update(Rate_limiter $IP_rate_limiter, int $key, ?bool $status, bool $first_fail) : void{
        try{
            if($status === null || $status === false) {
                $life_time = RATE_RECORD_EXPIRATION;
                $curr_t = (int)(\microtime(true) * 1000);

                $modify_target = $first_fail ? ['failed_login'=>1, 'failed_login_at'=>$curr_t] : ['failed_login'=>1];

                if($status === false) $IP_rate_limiter->Modify_counter($key,$modify_target);
                else $IP_rate_limiter->Append_new_record($key,$curr_t + $life_time,[
                    'last_request_at'=>$curr_t,
                    'start_at'=>$curr_t,
                    'failed_login'=>1,
                    'failed_login_at'=>$curr_t,
                ]);
                $IP_rate_limiter->Push();
            }
            else{
                \http_response_code(429);
                $IP_rate_limiter->Push();
                exit();
            }
        }finally{
            $IP_rate_limiter->Release();
        }
    }

    /**
     * Limit access of login against single account. Return 429 to client if too many login attempt
     */
    function Account_rate_limiter(string $email) : void{
        //1 second for test
        $ACC_rate_limiter = new Rate_limiter(Rate_bucket::Account,10000);

        try{
            $key = hexdec(substr(hash('xxh32', \strtolower(trim($email))), 0, 8));

            $result = $ACC_rate_limiter->At_limit($key,
                function(array &$data){
                    $limit = RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL['max_request'];
                    $interval = RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL['interval'];
                    $min_interval = RATE_ACCOUNT_LOGIN_MINIUM_INTERVAL;

                    $current_t = (int)(\microtime(true) * 1000);
                    $last_login_at = $data['last_login_at'];
                    $data['last_login_at'] = $current_t;

                    $start_at = $data['start_at'];
                    $time = $current_t - $start_at;

                    if($time>=$interval) {
                        $data['count'] = 0;
                        $data['start_at'] = $current_t;
                    }
                    

                    $current_interval = $current_t - $last_login_at;

                    if($current_interval < $min_interval || $data['count'] >= $limit){
                        $ip = Get_IP_address_txt();
                        $msg = $data['count'] >= $limit 
                        ? "Login rejected - " .$data['count']." failed login attempts exceeded the limit whithin: interval=[{$interval}ms] (limit: $limit)" 
                        : "Login rejected - Interval since last failed login is too short  (current:{$current_interval}ms | minium:{$min_interval}ms)";
                        
                        Log_internal(
                            new Error_code(Error_domain::authentication, Resource_code::rate, Error_condition::surpass_limit),
                            Level::Info,
                            "[IP-\"$ip\"] $msg"
                        );
                        return true;
                    }
                    

                    return false;
                }
            );

            if($result === null || $result === false) {
                $life_time = RATE_RECORD_EXPIRATION;
                
                $curr_t = (int)(\microtime(true) * 1000);
                if($result === false) $ACC_rate_limiter->Modify_counter($key,['count'=>1]);
                else $ACC_rate_limiter->Append_new_record($key,$curr_t + $life_time,['last_login_at'=>$curr_t,'start_at'=>$curr_t]);
                $ACC_rate_limiter->Push();
            }
            else{
                \http_response_code(429);
                $ACC_rate_limiter->Push();
                exit();
            }
        }finally{
            $ACC_rate_limiter->Release();
        }
    }


    function Internal_login(int $uid){
        New_session();
        $_SESSION['uid'] = $uid;
    }

    function Is_logged():bool{
        return isset($_SESSION['uid']);
    }