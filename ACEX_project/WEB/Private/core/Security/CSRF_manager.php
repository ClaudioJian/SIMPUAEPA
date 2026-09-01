<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Central place to manage CSRF attack                                                             |
  |                                                                                                 |
  |                                        Table of content                                         |
  |                                                                                                 |
  | CSRF_generate(): must have session active, can be used to create and store token in session     |
  | CSRF_isexpired():bool                                                                           |
  | CSRF_validate():bool -> must have session active. check if CSRF token still valid else exit     |    
  | CSRF_allowed_method():bool->return if this request need be protected                            |
  | CSRF_handle_fetch_API()-> check if is fetch api and reject for cross site                       |
  | CSRF_generate_send()-> send cookie to front end and exit immedially                             |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core\Security;
    require_once __DIR__ . "/../AppCommonVar.php";
    require_once __DIR__ . "/../../Auth/session_manager.php";

    
    /**
     * handle fetch api for csrf used as deep defence
     */
    function CSRF_handle_fetch_API(){
        $headers = getallheaders();
        if($headers===false) return;
        $headers = array_change_key_case($headers, CASE_LOWER);

        
        if(!isset($headers['sec-fetch-site'])) return;
        $sec_fetch_site = $headers['sec-fetch-site'];
        if($sec_fetch_site==='' || $sec_fetch_site==='cross-site') CSRF_invalidation("Same origin policy violation, current site don't support CORS");
    }

    function CSRF_isexpired():bool{
        return !isset($_SESSION['CSRF_TOKEN_CREATED']) || time() - $_SESSION['CSRF_TOKEN_CREATED']>CSRF_TOKEN_MAXLIFE;
    }

    /**
     * send token to client via cookie. should check if is actually request to get before call.
     */
    function CSRF_generate_send(array|bool $headers){
        if($headers === false) return;
        CSRF_generate();
        
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Allow: POST');
        http_response_code(204);
        exit();
    }
    /**
     * generate(can replace old) unique token for CSRF, store it in session(for usability) with expiration time.
     * the token is ready to be send to client.
     * session should be filtered before.
     * IMPORTANT: this should be called every time session is changed such as login, forgot password and session must be active
     * this code is modified from @link https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
     */
    function CSRF_generate() : void{
        if(session_status()!==PHP_SESSION_ACTIVE) CSRF_invalidation("No active session for generate CSRF token in generation");      

        $_SESSION['CSRF_TOKEN'] = CSRF_create_hmac();
        $_SESSION['CSRF_TOKEN_CREATED'] = time();
        header(CSRF_TOKEN_HEADER_NAME. ': ' .$_SESSION['CSRF_TOKEN']);
        setcookie(CSRF_TOKEN_HEADER_NAME,$_SESSION['CSRF_TOKEN'],['expires'=>(time() + CSRF_TOKEN_MAXLIFE),'path'=>'/','domain'=>'','secure'=>true,'httponly'=>false,'samesite'=>"Strict"]);
    }

    /**
     * create completly new hashed message for CSRF token
     * WARNING: CSRF_SECRET_KEY is not configured correct
     */
    function CSRF_create_hmac(string $random_val='') : string{
        /*
        (WARNING: not secury) too hard/no time to implement - to have safe key generation:
            1. need implement database to track time for rotate this
            2. when key expire, need change all hmac(hashed message) to make validation possible
            3. this is not session based, however, need implant database to do it
        */ 
        $_SESSION['CSRF_SECRET_KEY'] ??= bin2hex(random_bytes(64)); 
       

        $session_id = session_id();

        if($random_val==='') $random_val = bin2hex(random_bytes(64));
        $message = strlen($session_id) . CSRF_TOKEN_GENERATE_SEPARATOR . $session_id . CSRF_TOKEN_GENERATE_SEPARATOR . strlen($random_val) . CSRF_TOKEN_GENERATE_SEPARATOR . $random_val;
        $CSRF_Token = hash_hmac("SHA256",$message,$_SESSION['CSRF_SECRET_KEY']) . CSRF_TOKEN_GENERATE_SEPARATOR . $random_val;
        return $CSRF_Token;
    }

    /**
     * validate token for CSRF.
     * IMPORTANT: must have session active and filtered
     * @return bool
     *  *   true  - request does not require CSRF or token is valid
     *  *   false - request requires CSRF but no token exists/has expired
     *  *   never returns - invalid supplied token/session; sends 403
     */
    function CSRF_validate() : bool{
        if(!CSRF_required_method()) return true;
        if(session_status()!==PHP_SESSION_ACTIVE) CSRF_invalidation("No active session for generate CSRF token in validation"); 
        
        $current_token = $_SESSION['CSRF_TOKEN'] ?? '';
        if($current_token==='' || CSRF_isexpired()) return false;   
        $client_token = CSRF_get_client_token();
        if(empty($client_token)) {
            if($_SERVER['REQUEST_METHOD']!=='POST') CSRF_invalidation("forbidden");
            else return false;
        }

        $raw_data_array = explode(CSRF_TOKEN_GENERATE_SEPARATOR,$client_token);

        if (count($raw_data_array) !== 2) {
            CSRF_invalidation("Malformed CSRF token");
        }

        $token = $raw_data_array[0];
        $random_val = $raw_data_array[1];
        
        $expected_full_token = CSRF_create_hmac($random_val);
        $expected_token = explode(CSRF_TOKEN_GENERATE_SEPARATOR,$expected_full_token)[0];


        if(!hash_equals($token,$expected_token)) CSRF_invalidation("CSRF token mismatch");

        return true;
    }

    function CSRF_required_method():bool{
        foreach(CSRF_TOKEN_VALIDATE_METHOD as $method) if($_SERVER['REQUEST_METHOD'] === $method) return true;
        return false;
    }

    function CSRF_invalidation(string $msg){
        //immedially expire front end cookie
        setcookie(CSRF_TOKEN_HEADER_NAME,"",1,"/","",true,false);
        header("X-ERR-DESCRIPTION:".$msg);
        http_response_code(403);
        exit($msg);
    }
    
    function CSRF_get_client_token():string{     
        if (isset($_POST[CSRF_TOKEN_HEADER_NAME])) return $_POST[CSRF_TOKEN_HEADER_NAME];
    
        $headers = getallheaders();
        if($headers===false) return '';
        $headers = array_change_key_case($headers, CASE_LOWER);


        return $headers[strtolower(CSRF_TOKEN_HEADER_NAME)] ?? '';
    }
?>