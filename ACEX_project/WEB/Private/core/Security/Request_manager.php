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
    require_once __DIR__ . "/../AppCommonVar.php";
    require_once __DIR__ . "/../../Auth/session_manager.php";

    
    ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    foreach(UNSUPPORTED_METHOD as $method) if($_SERVER['REQUEST_METHOD']===$method){
        http_response_code(405);
        exit();
    };
    
?>