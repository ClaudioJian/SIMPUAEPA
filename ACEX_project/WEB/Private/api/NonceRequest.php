<?php 
namespace ACEX_project\WEB\Private\Api;

    /**
     * Make the current request nonce. if the client doesn't provide cnonce, then server will send 401 unathourized and send snonce, then client should resend same request but with cnonce, snonce, time token
     * If client send request with nonce, the validation will be executed.
     * 
     * Client should send in header the cnonce and hashed version of token hmac(sha256,snonce+cgen_time+nc,random_val) and x-cgen-time MUST be send to make server able compare
     * To store information, use Nonce_request_create()
     */
    function Require_nonce(){
        header('cache-control: no-store');
        if(!isset($_SESSION['nonce_request']) || !is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];

        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
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
        if(session_status() === PHP_SESSION_DISABLED) return;
        $is_none = session_status() === PHP_SESSION_NONE;
        if($is_none) session_start();

        if(!isset($_SESSION['nonce_request']) || !is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];

        foreach($_SESSION['nonce_request'] as $snonce=>$request) {
            Clean_expired_snonce($request['sgen_time'],$snonce);
        }
        if($is_none) session_commit();
    }

    function Validate_client_response(string $snonce, int $cgen_time, string $client_response,string $cnonce){
        if($client_response==='') Nonce_token_invalid(false,true);

        $request_uri = $_SERVER['REQUEST_URI'];
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $path = parse_url($request_uri,PHP_URL_PATH);
        
        $A2 = hash('SHA256',$method . ":" . $path);

        $message = $cnonce . NONCE_SEP . $snonce . NONCE_SEP . $cgen_time . NONCE_SEP . $A2;
        $expected = hash('SHA256',$message);
        if(!hash_equals($expected,$client_response)) {
            Nonce_token_invalid(false);
        }
    }
    /**
     * Create one time use request that is fixed to current request.
     * foreach request, should only be created one time.
     * for regular public data, store directly in session using array($_SESSION['data1'] = array({'x'=>$data})) or $_SESSION['data1'] = $data
     */
    function Nonce_request_create(mixed $data){
        if(session_status()===PHP_SESSION_NONE) session_start();

        if(!isset($_SESSION['nonce_request']) || !is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];


        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if($headers===false) $headers=[];

        $id = $headers['x-snonce'];
        if ($id === '' || !isset($_SESSION['nonce_request'][$id])) return false;

        $_SESSION['nonce_request'][$id]['data'] = $data;
    }



    function Validate_time(string $raw_cgen_time,string $request_snonce){
        $cgen_time = (int)$raw_cgen_time;
        if($cgen_time<0 || !ctype_digit($raw_cgen_time)){
            http_response_code(400);
            header('vary:x-cgen-time');
            exit();
        }
        

        $expected_time = $_SESSION['nonce_request'][$request_snonce]['cgen_time'];
        $now = (int) floor(microtime(true) * 1000);
        $age =  $now - $cgen_time;
        if($age <0 || $age > REQUEST_MAX_LIFE || $expected_time != $cgen_time) {
            header('vary:x-cgen-time');
            Nonce_token_invalid(true);
        }
    }

    /**
     * generate the expected snonce and check if is valid, if not find, the challenge is send
     * exit when: 1. snonce don't match expectation; 2. request exist but used
    */
    function Validate_request_snonce(string $snonce){
        if($snonce === '' || $snonce === null) Nonce_token_invalid(false,true);
        //ensure exist
        if(!isset($_SESSION['nonce_request']) || !is_array($_SESSION['nonce_request'])) $_SESSION['nonce_request'] = [];
        if(!isset($_SESSION['nonce_request'][$snonce])) Nonce_token_invalid(false);

        $random = $_SESSION['nonce_request'][$snonce]['random_val'];
        $snonce_gen_time = $_SESSION['nonce_request'][$snonce]['sgen_time'];
        $expected_snonce = Snonce_generate($random,$snonce_gen_time);


        if(Clean_expired_snonce($snonce_gen_time,$snonce) ||
            !hash_equals($snonce,$expected_snonce)
        ) Nonce_token_invalid(false);
        if($_SESSION['nonce_request'][$snonce]['used'] === true) Nonce_token_invalid(true);
    }

    /**
     * produced hash = algo:256, H(<Session><random><time>)"#"<random>"#"<time>
     * WARNING: $_SESSION['SECRET_KEY'] is not secure, used for simplicity 
     */
    function Snonce_generate(string $random_val, int $stime){
        if(!isset($_SESSION['SECRET_KEY'])) $_SESSION['SECRET_KEY'] = bin2hex(random_bytes(32));
        $server_secret = $_SESSION['SECRET_KEY'];


        $message = strlen($random_val) . NONCE_SEP . $random_val . $stime;
        $token = hash_hmac('SHA256',$message,$server_secret);
        return $token;
    }

    /**
     * Clean expired snonce
     * to snonce be expired: has longer time than request life time
     * @return bool true if data cleaned
     */
    function Clean_expired_snonce(int $generated_time,string $target_snonce) :bool{
        $now = (int) floor(microtime(true) * 1000);
        $age = $now - $generated_time;
        if($age<0 || $age <= REQUEST_MAX_LIFE) return false;
        
        if(isset($_SESSION['nonce_request'][$target_snonce])) unset($_SESSION['nonce_request'][$target_snonce]);      
        
        return true;
    }

    /**
     * @param bool $stale true mean the request is invalid because is expired
     * @param bool $challenge if set true, then the new generated snonce is returned and stored in server
     */
    function Nonce_token_invalid(bool $stale,bool $challenge = false){
        if($challenge){ 
            if(session_status() === PHP_SESSION_NONE) session_start();
            Store_nonce();
        }
        header('x-request-stale:'.$stale);

        http_response_code(401);
        exit();
    }

    function Store_nonce(){
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if($headers===false) $headers=[];

        $cgen_time = $headers['x-cgen-time'] ?? 0;

        $random_val = bin2hex(random_bytes(64));
        $stime = (int) floor(microtime(true) * 1000);
        $snonce = Snonce_generate($random_val,$stime);
        $_SESSION['nonce_request'][$snonce] = [
            'cgen_time'=>$cgen_time,
            'data'=>null,
            'used'=>false,
            'random_val'=>$random_val,
            'sgen_time'=>$stime,
        ];

        header('x-snonce:'.$snonce);
    }

?>