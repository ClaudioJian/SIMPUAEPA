<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Central place to handle request                                                                 |
  | WARNING: every script in backend should have pass this before processing                        |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Core\Security;
    use Exception;


    //CSRF protection
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if($headers===false) $headers=[];
    
    //if CSRF is invalid instead don't exist, exit immedially else generate CSRF
    CSRF_handle_fetch_API();
    
    //renew csrf token
    CSRF_validate();
?>