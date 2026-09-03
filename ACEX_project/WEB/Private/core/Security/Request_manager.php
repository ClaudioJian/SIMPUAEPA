<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Central place to handle request                                                                 |
  | WARNING: every script in backend should have pass this before processing                        |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core\Security;
  use function ACEX_project\WEB\Auth\session_startup;

  $method = strtoupper($_SERVER['REQUEST_METHOD']);

  if(!in_array($method,VALID_HTTP_METHOD,true)) {
    http_response_code(400);
    exit();
  }

  foreach(UNSUPPORTED_METHOD as $unsupport_method) if($method===strtoupper($unsupport_method)){
    http_response_code(405);
    exit();
  };
  

  function Handle_CSRF_GET(){
    $pathi = $_SERVER['PATH_INFO'];
    $required_resource = ($pathi===null||$pathi==='') ? null : strtolower($_SERVER['PATH_INFO']);
    
    if($required_resource=="/csrf" && $_SERVER['REQUEST_METHOD']==='POST'){
      //when request, this mean the client is trying renew
      if(!CSRF_validate()) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        CSRF_generate_send($headers); 
      }

      http_response_code(304);
      exit();
    }
  }

    //CSRF protection
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if($headers===false) $headers=[];
    
    CSRF_handle_fetch_API();

    //check if is state change request, if so, start session no matter what
    if(CSRF_required_method()){
      header('Cache-Control: no-cache, no-store, must-revalidate, private');
      //continue previous session or create new session
      session_startup();
      Handle_CSRF_GET();

      CSRF_validate();
    }


?>