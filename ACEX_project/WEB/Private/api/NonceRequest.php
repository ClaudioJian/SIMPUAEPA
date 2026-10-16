<?php 
namespace ACEX_project\WEB\Private\Api;

    require_once __DIR__ ."/../Error/Error_manager.php";
    require_once __DIR__ ."/../Error/Error_code.php";
    use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

    use function ACEX_project\WEB\Private\Error\Log_internal;

    /**
     * Make the current request nonce. if the client doesn't provide cnonce, then server will send 401 unathourized and send snonce, then client should resend same request but with cnonce, snonce, time token
     * If client send request with nonce, the validation will be executed.
     * 
     * Client should send in header the cnonce and hashed version of token hmac(sha256,snonce+cgen_time+nc,random_val) and x-cgen-time MUST be send to make server able compare
     * To store information, use Nonce_request_create()
     */
    function Require_nonce(){
        \header('cache-control: no-store');
        if(!isset($_SESSION['nonce_request']) || !\is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];

        $headers = \array_change_key_case(\getallheaders(), CASE_LOWER);
        if($headers===false) $headers=[];

        $requested_snonce = $headers['x-snonce'] ?? '';
        Validate_request_snonce($requested_snonce);        

        $cgen_time = $headers['x-cgen-time'] ?? 0;


        Validate_time($cgen_time,$requested_snonce);


        $client_response = $headers['x-nonce-response'] ?? '';
        $cnonce = $headers['x-cnonce'] ?? '';
        
        Validate_client_response($requested_snonce,$cgen_time,$client_response,$cnonce);

        
        $_SESSION['nonce_request'][$requested_snonce]['used'] = true;
    }

    /**
     * MUST called in every request
     */
    function Nonce_request_clean(){
        if(\session_status() === PHP_SESSION_DISABLED) return;
        $is_none = \session_status() === PHP_SESSION_NONE;
        if($is_none) \session_start();



        if(!isset($_SESSION['nonce_request']) || !\is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];

        foreach($_SESSION['nonce_request'] as $snonce=>$request) {
            Clean_expired_snonce($request['sgen_time'],$snonce);
        }
        if($is_none) \session_commit();
    }

    function Validate_client_response(string $snonce, int $cgen_time, string $client_response,string $cnonce){
        if($client_response==='') Nonce_token_invalid(false,true);

        $request_uri = $_SERVER['REQUEST_URI'];
        $method = \strtoupper($_SERVER['REQUEST_METHOD']);
        $path = \parse_url($request_uri,PHP_URL_PATH);
        
        $A2 = \hash('SHA256', "$method:$path");

        $message = $cnonce . NONCE_SEP . $snonce . NONCE_SEP . $cgen_time . NONCE_SEP . $A2;
        $expected = hash('SHA256',$message);
        if(!\hash_equals($expected,$client_response)) {
            Nonce_token_invalid(false,false,"Received nonce doesn't match the value calculated by the server");
        }
    }
    /**
     * Create one time use request that is fixed to current request.
     * foreach request, should only be created one time.
     * for regular public data, store directly in session using array($_SESSION['data1'] = array({'x'=>$data})) or $_SESSION['data1'] = $data
     */
    function Nonce_request_create(mixed $data){
        if(\session_status()===PHP_SESSION_NONE) \session_start();

        if(!isset($_SESSION['nonce_request']) || !\is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];


        $headers = \array_change_key_case(getallheaders(), CASE_LOWER);
        if($headers===false) $headers=[];

        $id = $headers['x-snonce'];
        if ($id === '' || !isset($_SESSION['nonce_request'][$id])) return false;

        $_SESSION['nonce_request'][$id]['data'] = $data;
    }



    function Validate_time(string $raw_cgen_time,string $request_snonce){
        $cgen_time = (int)$raw_cgen_time;
        if($cgen_time<0 || !ctype_digit($raw_cgen_time)){
            \http_response_code(400);
            \header('vary: x-cgen-time');
            exit();
        }
        

        $expected_time = $_SESSION['nonce_request'][$request_snonce]['cgen_time'];
        $now = (int) \floor(microtime(true) * 1000);
        $age =  $now - $cgen_time;
        if($age <0 || $age > REQUEST_MAX_LIFE*1000 || $expected_time != $cgen_time) {
            \header('vary: x-cgen-time');
            Nonce_token_invalid(true,false,"Invalid client request timestamp: is outside the permitted lifetime(greater than REQUEST_MAX_LIFE), negative client time or does not match the original request timestamp");
        }
    }

    /**
     * generate the expected snonce and check if is valid, if not find, the challenge is send
     * exit when: 1. snonce don't match expectation; 2. request exist but used
    */
    function Validate_request_snonce(string $snonce){
        if($snonce === '' || $snonce === null) Nonce_token_invalid(false,true);
        //ensure exist
        if(!isset($_SESSION['nonce_request']) || !\is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];
        if(!isset($_SESSION['nonce_request'][$snonce])) Nonce_token_invalid(false,false,"Requested challenge doesn't exist in server");

        $random = $_SESSION['nonce_request'][$snonce]['random_val'];
        $snonce_gen_time = $_SESSION['nonce_request'][$snonce]['sgen_time'];
        $expected_snonce = Snonce_generate($random,$snonce_gen_time);


        if(Clean_expired_snonce($snonce_gen_time,$snonce) ||
            !\hash_equals($snonce,$expected_snonce)
        ) Nonce_token_invalid(false, false, "requested snonce token has expired or does not match expected value");
        if($_SESSION['nonce_request'][$snonce]['used'] === true) Nonce_token_invalid(true,false,"Request was alredy used");
    }

    /**
     * produced hash = algo:256, H(<Session><random><time>)"#"<random>"#"<time>
     * WARNING: $_SESSION['SECRET_KEY'] is not secure, used for simplicity 
     */
    function Snonce_generate(string $random_val, int $stime){
        if(!isset($_SESSION['SECRET_KEY'])) $_SESSION['SECRET_KEY'] = \bin2hex(random_bytes(32));
        $server_secret = $_SESSION['SECRET_KEY'];


        $message = \strlen($random_val) . NONCE_SEP . $random_val . $stime;
        $token = \hash_hmac('SHA256',$message,$server_secret);
        return $token;
    }

    /**
     * Clean expired snonce
     * to snonce be expired: has longer time than request life time
     * @return bool true if data cleaned
     */
    function Clean_expired_snonce(int $generated_time,string $target_snonce) :bool{
        $now = (int) \floor(microtime(true) * 1000);
        $age = $now - $generated_time;
        if($age<0 || $age <= REQUEST_MAX_LIFE * 1000) return false;
        $r = REQUEST_MAX_LIFE;

        if(isset($_SESSION['nonce_request'][$target_snonce])) unset($_SESSION['nonce_request'][$target_snonce]);      
        
        return true;
    }

    /**
     * @param bool $stale true mean the request is invalid because is expired
     * @param bool $challenge if set true, then the new generated snonce is returned and stored in server
     */
    function Nonce_token_invalid(bool $stale,bool $challenge = false, string $msg = ""){
        $condition = Error_condition::denied;
        $lvl = Level::Warning;
        if($challenge){ 
            if(\session_status() === PHP_SESSION_NONE) \session_start();
            $snonce = Store_nonce();
            $cgen_time = $_SESSION['nonce_request'][$snonce]['cgen_time'];
            $sgen_time = $_SESSION['nonce_request'][$snonce]['sgen_time'];

            $condition = Error_condition::attempt;
            $lvl = Level::Info;
            $msg = "Returning to client challenge[snonce-\"$snonce\"|client time-\"$cgen_time\"|server time-$sgen_time]";
        }
        \header("x-request-stale: $stale");

        Log_internal(
            new Error_code(Error_domain::request,Resource_code::general,$condition),
            $lvl,
            $msg
        );

        \http_response_code(401);
        exit();
    }

    function Store_nonce() : string{
        $headers = \array_change_key_case(getallheaders(), CASE_LOWER);
        if($headers===false) $headers=[];

        $cgen_time = $headers['x-cgen-time'] ?? 0;

        $random_val = \bin2hex(random_bytes(64));
        $stime = (int) \floor(microtime(true) * 1000);
        $snonce = Snonce_generate($random_val,$stime);
        $_SESSION['nonce_request'][$snonce] = [
            'cgen_time'=>$cgen_time,
            'data'=>null,
            'used'=>false,
            'random_val'=>$random_val,
            'sgen_time'=>$stime,
        ];

        \header("x-snonce: $snonce");
        return $snonce;
    }
