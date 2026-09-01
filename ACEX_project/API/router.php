<?php 
require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
use function ACEX_project\WEB\Core\Security\CSRF_generate_send;
use function ACEX_project\WEB\Core\Security\CSRF_validate;

require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";


use function ACEX_project\WEB\Auth\Filter_session;
use function ACEX_project\WEB\Auth\Session_exists;
use function ACEX_project\WEB\Auth\New_session;
use function ACEX_project\WEB\Auth\Update_session;

ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
$method = strtoupper($_SERVER['REQUEST_METHOD']);

foreach(UNSUPPORTED_METHOD as $unsupport_method) if($method===strtoupper($unsupport_method)){
    http_response_code(405);
    exit();
};
//now log as guest, if alredy have session it will be replaced as new id else start new/load session. since is first time, new/load session and session is also updated
if(!session_start()) {
    http_response_code(500);
    exit;
}
//check if to continue previous session or create new session
if(!Session_exists()) {
    if(Filter_session()) Update_session();
}
else if(!New_session()){
    http_response_code(500);
    exit;
}





$pathi = $_SERVER['PATH_INFO'];
$required_resource = ($pathi===null||$pathi==='') ? null : strtolower($_SERVER['PATH_INFO']);
//handle for csrf get request
if($required_resource=="/csrf" && $_SERVER['REQUEST_METHOD']==='POST'){
    //when request, this mean the client is trying renew, if
    if(!CSRF_validate()) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        CSRF_generate_send($headers); 
    }
    
    http_response_code(304);
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    exit();
}
//CSRF token is still need validated here
require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";

//routing logic
if($method==='POST' && $required_resource=="/test"){
    header('Location: testawawa.php');
    http_response_code(303);
    exit();
}

?>