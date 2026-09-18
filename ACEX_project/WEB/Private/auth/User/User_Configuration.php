<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for user's Prefered_Setting                                                    |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Auth\User;

if (!defined('ENTRY_POINT_CHECKED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

class User_Prefered_Setting{
    public function __construct()
    {
        throw new \Exception('Not implemented');
    }
};
?>