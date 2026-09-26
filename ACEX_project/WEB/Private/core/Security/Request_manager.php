<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Central place to handle request                                                                 |
  | WARNING: every script in backend should have pass this before processing                        |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security;
  require_once __DIR__ . '/../../Api/NonceRequest.php';
  require_once __DIR__ . '/../../Auth/session_manager.php';

  use function ACEX_project\WEB\Private\Api\Nonce_request_clean;

  require_once __DIR__ ."/../../Core/MIME_type.php";
  use function ACEX_project\WEB\Private\Core\Construct_MIME;

  function Validate_request(){
    Nonce_request_clean();
    
    //Force TLS
    /*
    //not sure will work or no
    if($_SERVER['SERVER_NAME'] !== 'localhost'){
      if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' || $_SERVER['SERVER_PORT'] != 443) {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains',true,400);
        header('Location: ' . $redirect);
        exit();
      }
    } 
    */
    

    $method = strtoupper($_SERVER['REQUEST_METHOD']);

    if(!in_array($method,VALID_HTTP_METHOD,true)) {
      http_response_code(400);
      exit();
    }

    foreach(UNSUPPORTED_METHOD as $unsupport_method) if($method===strtoupper($unsupport_method)){
      $allowed_methods = array_diff(VALID_HTTP_METHOD,UNSUPPORTED_METHOD);
      header('Allow:'.implode(" ,",$allowed_methods));
      http_response_code(405);
      exit();
    };    

    //CSRF protection
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if($headers===false) $headers=[];
    
    CSRF_handle_fetch_API();
    CSRF_validate();
  }


  function Handle_CSRF_GET(){
    if(!CSRF_required_method()) return;
    header('Cache-Control: no-cache, no-store, must-revalidate, private');

    //when request, this mean the client is trying renew
    if(!CSRF_validate()) {
      $headers = array_change_key_case(getallheaders(), CASE_LOWER);
      CSRF_generate_send($headers); 
    }

    http_response_code(304);
    exit();
    
  }



    /**
     *  get value posted in json from web in array form. don't use when is multi-part
     *  to use: returned_array['key']
     * @return array ['key'=>'value'] from fetch(url,{...,body:JSON.stringfy(key:value)})
     */
    function Get_request_body(){
        $request_raw = file_get_contents('php://input');
        $assoc_arr = json_decode($request_raw,true);

        if($assoc_arr===false && json_last_error() === JSON_ERROR_NONE){
          http_response_code(415);
          header('accept: ' . Construct_MIME(MIME['application']['json']));
          exit();
        }

        return $assoc_arr;
    }

?>