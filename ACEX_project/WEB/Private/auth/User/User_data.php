<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;

use Throwable;
use Exception;

require_once __DIR__ . "/../../Api/database/database_manager.php";

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;

require_once __DIR__ . "/../../Api/database/Authorization_manager.php";

use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;


require_once __DIR__ . "/../../Error/Error_manager.php";
use function ACEX_project\WEB\Private\Error\Handle_error;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Log_level;
use ACEX_project\WEB\Private\Error\Resource_code;

    /**
     * get user data: email, name, password[optional] from id
     * tb_user_columns::id_user->name, tb_user_columns::email->name, tb_user_columns::password->name
     * @param string $id if not specified, get current user data 
     * 
     */
    function Get_user_data(bool $passwd = false, string $id = "") : \PDOStatement | null{
        try{
        Get_user_data_authorized();

        if($id===''){
            if(session_status()!==PHP_SESSION_ACTIVE) throw new Exception('No active session');
            else $id = $_SESSION['uid'];
        }


        $conn = Connect_database();

        $selected_columns = [tb_user_columns::name_user->name, tb_user_columns::email->name];
        if($passwd) $selected_columns[] = tb_user_columns::password->name;

        $query = "SELECT " .
            implode(", ",$selected_columns).
            " FROM ". All_tables::user->name. 
            " WHERE ". tb_user_columns::id_user->name ." = :uid"
        ;
        $smtm = $conn->prepare($query);
        if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

        $smtm->execute([':uid'=>$id]);
        return $smtm;
        }catch(Throwable $e){
        return null;
        }
    }

      /**
     * Exit immedially if not authorized:
     * http code 500
    */
    function Get_user_data_authorized(){
        $select_auth = Is_query_permitted(HOST,[Query_type::select] ,All_tables::user,
            [tb_user_columns::email,tb_user_columns::id_user,tb_user_columns::password]
        );

        if(!$select_auth){
            $colums_denied = ' | Columns: email, password, id';
        
            Handle_error(
                new Error_code(Error_domain::database,Resource_code::db_select,Error_condition::unauthorized),
                Log_level::fatal,
                'Login failed: ' .All_tables::user->name. ' | user:'. HOST. $colums_denied
            );
        }
    }
?>