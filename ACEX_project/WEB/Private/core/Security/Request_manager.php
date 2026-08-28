<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Central place to handle request                                                                 |
  | WARNING: every script in backend should have pass this before processing                        |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core\Security;
    define('ENTRY_POINT_CHECKED',true);
    
    use Exception;

use function ACEX_project\WEB\Auth\Filter_session;
use function ACEX_project\WEB\Auth\New_session;


    require_once __DIR__ . "/../AppCommonVar.php";
    require_once __DIR__ . "/../../Auth/session_manager.php";
    require_once __DIR__ . "/../../Core/Security";
    
    ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    foreach(UNSUPPORTED_METHOD as $method) if($_SERVER['REQUEST_METHOD']===$method){
        http_response_code(405);
        exit();
    };
    //now log as guest, if alredy have session it will be replaced as new id else start new/load session. since is first time, new/load session and session is also updated
    New_session();
    //before do any action, check if current id is valid else exit
    Filter_session();

    //CSRF protection
    $headers = getallheaders();
    //if CSRF is invalid instead don't exist, exit immedially else generate CSRF
    // if fetch used, no need CSRF token
    CSRF_handle_fetch_API();
    if(!CSRF_validate()) CSRF_generate();
    
?>