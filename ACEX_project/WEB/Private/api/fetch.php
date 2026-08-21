<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | This file are meant to be fetched by fetch api in javascript                                    |
  | Return in json format                                                                           |
  |                                                                                                 |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

require_once "./helper/AUTH_main.php";
session_start();

// que tipo vai ser retornado?
header("content-type:application/json");



$method = $_SERVER['REQUEST_METHOD'];
//echo to return json_encode response from database


?>