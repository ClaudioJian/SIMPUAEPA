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
use ACEX_project\WEB\Private\Core\File_storation_path;
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

require_once __DIR__ .'/../../Core/Security/Rate_limit_handler.php';
use function ACEX_project\WEB\Private\Core\Security\Bucket_info_from_fixed_window;
use function ACEX_project\WEB\Private\Core\Security\Fixed_window_to_bucket_algo;
use function ACEX_project\WEB\Private\Core\Send_mime_type;

require_once __DIR__ .'/../../Error/Error_manager.php';

require_once __DIR__ .'/../../Error/Error_code.php';
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

require_once __DIR__ . "/User_data.php";


    function Login(?Rate_limiter $IP_rate_limiter, array $captured_args, array $request_body = []){
        if(Is_logged()) {
            Send_mime_type(MIME['application']['json']);
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
            
            $key = hexdec(substr(hash('xxh32', $ip), 0, 8));
            $result = IP_login_at_limit($IP_rate_limiter, $key);

            Validate_enroll_data($email,$password);
            Get_user_data_authorized(true,false,true);

            $msg = "";
            $uid = Validate_login_credential($email,$password,$msg);
            if($uid === false){
                Log_internal(
                    new Error_code(Error_domain::authentication, Resource_code::request, Error_condition::failed),
                    Level::Info,
                    "[IP-\"$ip_txt\"] Login attemption failed: Reason - [$msg], Email=[$email]"
                );

                if($IP_rate_limiter !== null) IP_failed_login_update($IP_rate_limiter, $key, $result);
                Account_rate_limiter($email);
                exit();
            }

            Internal_login($uid['uid'], $email, $uid['name']);
        }catch(Throwable $e){
            Handle_query_fail($e,$conn);
        }

        $uid = $_SESSION['uid'];
        Send_mime_type(MIME['application']['json']);
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
     * @return array{
     * uid: int,
     * name:string,
     * img_url: string
     * }|false
     */
    function Validate_login_credential(string $email, string $raw_passwd, string &$msg):array|false{
        $response = ['success'=>0,'email'=>4,'password'=>4];
        $conn = Connect_database();
        if($conn === null) {
            Handle_error(new Error_code(Error_domain::credential,Resource_code::db_select,Error_condition::failed),
                Level::Critical,
                "Validatiation failed because cannot connect to database"
            );
        }

        $query = "SELECT " .
            \implode(", ",[tb_user_columns::id->name, tb_user_columns::name->name , tb_user_columns::email->name, tb_user_columns::password->name, tb_user_columns::profile_image_name->name]).
            " FROM ". All_tables::user->name. 
            " WHERE ". tb_user_columns::email->name ." = :email"
        ;

        $smtm = $conn->prepare($query);
        if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

        $smtm->execute([':email'=>$email]);

        if($smtm->rowCount()<1) {
            \http_response_code(400);
            Send_mime_type(MIME['application']['json']);
            echo json_encode([...$response,'email'=>2,'success'=>-1]);
            $msg = "Email not registered in database";
            return false;
        }

        $row = $smtm->fetch(\PDO::FETCH_ASSOC);
        $stored_passwd = $row[tb_user_columns::password->name];

        if(!Secure_password_verify($raw_passwd,$stored_passwd)){
            \http_response_code(400);
            Send_mime_type(MIME['application']['json']);
            echo json_encode([...$response,'success'=>-1,'password'=>2]);
            $msg = "Invalid password";
            return false;
        }

        return [
            'uid' => $row[tb_user_columns::id->name],
            'name' => $row[tb_user_columns::name->name],
            'img_url' => $row[tb_user_columns::profile_image_name->name]
        ];
    }

    /**
     * Check whether singular ip login failed attempt exceed limit or is still penalized, if true, return 429 to client else return false or null.
     * The cleanup for record won't triggered if still in penalitation(unless record itself is expired).
     * @param Rate_limiter $IP_rate_limiter
     * @param int $key
     * @return bool|null false when not hit limit else null when is completly new record. True when error happen
     */
    function IP_login_at_limit(Rate_limiter $IP_rate_limiter, int $key) : bool|null{
        try{        
            $result = $IP_rate_limiter->At_limit($key,
                function(array &$data){
                    $current_t = \intdiv(\hrtime(true),1000000);
                    $ip = Get_IP_address_txt();

                    $limit = RATE_IP_LOGIN_PER_INTERVAL['max_request'];
                    $interval = RATE_IP_LOGIN_PER_INTERVAL['interval'];

                    if(!isset($data['last_refill_login'])) $data['last_refill_login'] = $current_t;
                    if(!isset($data['login_token'])){
                        $b_info_login = Bucket_info_from_fixed_window($limit, $interval);
                        $data['login_token'] = $b_info_login['capacity'];
                    }

                    $penality_time = RATE_IP_LOGIN_PENALTY;
                    $result = Fixed_window_to_bucket_algo($limit, $interval, $data['last_refill_login'], $data['login_token'],$penality_time);

                    
                    if($result) {
                        $b_info = Bucket_info_from_fixed_window($limit, $interval);
                        $blocked_until = ($b_info['consume'] - $data['login_token']) / $b_info['refill_speed'];
                        $msg = $data['login_token'] . " rate-limit tokens remained for current IP Login: penalty active [duration: {$blocked_until} ms](limit: $limit requests)";
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
    function IP_failed_login_update(Rate_limiter $IP_rate_limiter, int $key, ?bool $status) : void{
        try{
            if($status === null || $status === false) {
                $life_time = RATE_RECORD_EXPIRATION;
                $curr_t = \intdiv(\hrtime(true),1000000);

                $b_info_login = Bucket_info_from_fixed_window(RATE_IP_LOGIN_PER_INTERVAL['max_request'], RATE_IP_LOGIN_PER_INTERVAL['interval']);
                $b_info_ip = Bucket_info_from_fixed_window(RATE_IP_MAX_REQUEST_PER_INTERVAL['max_request'], RATE_IP_MAX_REQUEST_PER_INTERVAL['interval']);

                if($status === null) $IP_rate_limiter->Append_new_record($key, $curr_t + $life_time,
                    [
                        'login_token'=>$b_info_login['capacity'],
                        'count'=> $b_info_ip['capacity'],
                        'last_request_at'=>$curr_t,
                        'last_refill'=>$curr_t,
                        'last_refill_login'=>$curr_t
                    ]
                );
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
            $limit = RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL['max_request'];
            $interval = RATE_ACCOUNT_LOGIN_ATTEMPT_INTERVAL['interval'];

            $result = $ACC_rate_limiter->At_limit($key,
                function(array &$data) use($limit,$interval){
                    $min_interval = RATE_ACCOUNT_LOGIN_MINIMUM_INTERVAL;

                    $current_t = \intdiv(\hrtime(true),1000000);
                    $last_login_at = $data['last_login_at'];
                    $data['last_login_at'] = $current_t;

                    if(!isset($data['last_refill'])) $data['last_refill'] = $current_t;
                    $result = Fixed_window_to_bucket_algo($limit, $interval, $data['last_refill'], $data['count']);

                    $current_interval = $current_t - $last_login_at;

                    if($current_interval < $min_interval || $result){
                        $ip = Get_IP_address_txt();
                        $msg = $current_interval < $min_interval
                        ? "Login rejected - Interval since last failed login is too short  (current:{$current_interval}ms | minimum:{$min_interval}ms)"
                        : "Login rejected - " .$data['count']." rate-limit tokens remained for failed login attempts whithin: window=[{$interval}ms] (limit: $limit requests)";
                        
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
                
                $curr_t = \intdiv(\hrtime(true),1000000);

                $b_info = Bucket_info_from_fixed_window($limit,$interval);
                if($result === null) $ACC_rate_limiter->Append_new_record($key,$curr_t + $life_time,['last_login_at'=>$curr_t,'count'=>$b_info['capacity'],'last_refill'=>$curr_t]);
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


    function Internal_login(int $uid, string $email = '', string $name = '', string $profile_img_url = ''){
        New_session();
        $_SESSION['uid'] = $uid;

        if($email !== '') $_SESSION['user_email'] = \trim($email);
        if($name !== '') $_SESSION['user_name'] = \trim($name);
        if($profile_img_url !== '') $_SESSION['profile_img_url'] = '/'. URI_PREFIX .'/image/profile/' . \strtolower(\trim($profile_img_url));
    }

    function Is_logged():bool{
        return isset($_SESSION['uid']);
    }