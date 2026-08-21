<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts general useful function for database.                                                 |
  | Also is master file that include everything,so you can require this file to get majority funct  |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/


require_once "const.php";

require_once "DB_calculator_financeiro/TB_data.php";
require_once "DB_calculator_financeiro/TB_historico.php";
require_once "AUTH_helper.php";

require_once "/../../Core/AppCommonVar";

require_once "/../../Error/Error_code";
use ACEX_project\WEB\Private\Error\Error_Code;

/**
 * connect to database.
 * auto exit database when error occur.
 * @return PDO sucess: object pointer to connection of database
 * @return array error: ['sucess'=>DB_ERR_CONNECTION,'description'=>string]
 */
function connect_database(){
    $dns = "mysql: host=" .HOST. "port=".DB_PORT.";dbname=".DB_NAME;

    try{
        $conn = new PDO($dns,SERVER_USER,DB_PASSWORD);
    }catch(PDOException $e){
        $conn = NULL;
        return ['sucess'=>Error_Code::db_connection,'description'=>$e->getMessage()];
    }
    return $conn;
}




/**
 *  get value posted in json from web in array form.
 *  to use: returned_array['key']
 * @return array ['key'=>'value'] from fetch(url,{...,body:JSON.stringfy(key:value)})
 */
function get_post_values(){
    $request_raw = file_get_contents('php://input');
    $assoc_arr = json_decode($request_raw,true);
    
    return $assoc_arr;
}

?>