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
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core\Security;
    if (!defined('ENTRY_POINT_CHECKED')) {
        http_response_code(403);
        exit('Direct access not permitted');
    }
    
    use Exception;
    use function ACEX_project\WEB\Auth\Filter_session;

    require_once __DIR__ . "/../AppCommonVar.php";
    require_once __DIR__ . "/../../Auth/session_manager.php";

    //https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Sec-Fetch-Dest
    //TODO: change to deticated place to handle this
    // this where response data will be used
    $SecFetchDest_allow_list = array('audio','audioworklet','document','embed','empty','fencedframe','font','frame','iframe','image','json','manifest','object','paintworklet','report','style','track','video','webidentity','worker','xslt');
    /**
     * handle fetch api for csrf if used
     * @return bool false if isn't fetch api else true, exit if invalid request
     */
    function CSRF_handle_fetch_API(){
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if(!CSRF_allowed_method() || !isset($headers['Sec-Fetch-Site'])) return false;
        if($headers['Sec-Fetch-Site']==='cross-site' || $headers['Sec-Fetch-Mode']==='cors') CSRF_invalidation("Same origin policy violation, current site don't support CORS");

        return true;
    }

    function CSRF_isexpired():bool{
        if(defined($_SESSION['CSRF_TOKEN_MAXLIFE']) && time() - $_SESSION['CSRF_TOKEN_MAXLIFE']>CSRF_TOKEN_MAXLIFE) return true;
        return false;
    }
    /**
     * generate(can replace old) unique token for CSRF, store it in session(for usability) with expiration time.
     * when acessing obsolete/anabdoned, the request will be ignored.
     * IMPORTANT: this should be called every time session is changed such as login, forgot password and session must be active
     * this code is modified from @link https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
     */
    function CSRF_generate() : void{
        if(session_status()!==PHP_SESSION_ACTIVE) throw new Exception("No active session for generate CSRF token");      

        // will always have session because guest, when alredy have old session abandoned, the old will automatically be invalid since id is different(precondition made for this function: must be called when session change)
        if(!Filter_session()) return;
        
        $CSRF_token  = CSRF_create_hmac();

        $_SESSION['CSRF_TOKEN'] = $CSRF_token;
        $_SESSION['CSRF_TOKEN_MAXLIFE'] = time();
    }
    /**
     * validate token for CSRF.
     * IMPORTANT: must have session active
     * @return bool false for invalid token and suggest to regenerate it. else for invalidation, request is abandoned
     */
    function CSRF_validate() : bool{
        if(!CSRF_allowed_method()) return true;
        if(session_status()!==PHP_SESSION_ACTIVE) throw new Exception("No active session for generate CSRF token");

        if(!isset($_SESSION['CSRF_TOKEN'])) return false;
        if(empty($_SESSION['CSRF_TOKEN']) || empty($_SESSION['CSRF_TOKEN_MAXLIFE'])) CSRF_invalidation("No valid CSRF token provided");
        if(CSRF_isexpired()) return false;
        
        $client_token = CSRF_get_client_token();
        if(empty($client_token)) CSRF_invalidation("no CSRF token provided");
        $current_token = $_SESSION['CSRF_TOKEN'];

        if(!hash_equals($client_token,$current_token)) CSRF_invalidation("CSRF token provided don't match");

        return true;
    }

    /**
     * create completly new hashed message for CSRF token
     * WARNING: CSRF_SECRET_KEY is not configured correct
     */
    function CSRF_create_hmac() : string{
        /*
        (WARNING: not secury) too hard/no time to implement - to have safe key generation:
            1. need implement database to track time for rotate this
            2. when key expire, need change all hmac(hashed message) to make validation possible
            3. this is not session based, however, need implant database to do it
        */ 
        $_SESSION['CSRF_SECRET_KEY'] ??= bin2hex(random_bytes(64)); 
       

        $session_id = session_id();

        $random_val = bin2hex(random_bytes(64));
        $message = strlen($session_id) . CSRF_TOKEN_GENERATE_SEPARATOR . $session_id . CSRF_TOKEN_GENERATE_SEPARATOR . strlen($random_val) . CSRF_TOKEN_GENERATE_SEPARATOR . $random_val;
        return hash_hmac("SHA256",$message,$_SESSION['CSRF_SECRET_KEY']);
    }

    function CSRF_allowed_method():bool{
        foreach(CSRF_TOKEN_VALIDATE_METHOD as $method) if($_SERVER['REQUEST_METHOD'] === $method) return true;
        return false;
    }

    function CSRF_invalidation(string $msg){
        http_response_code(403);
        exit($msg);
    }
    
    function CSRF_get_client_token():string{
        //TODO: expand possible client send token
        $token = "";
        switch($_SERVER['REQUEST_METHOD']){
            case "POST":{
                break;
            }
            case "PUT":{
                break;
            }
            case "DELETE":{
                break;
            }
        };
        $headers = getallheaders();
        if($headers === false) return $token;
        $token ??= $headers[CSRF_TOKEN_HEADER_NAME];
        return $token;
    }
?>