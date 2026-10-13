<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts general useful function for database.                                                 |
  | Also is master file that include everything,so you can require this file to get majority funct  |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Api\database;
require_once __DIR__ . "/Authorization_manager.php";
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Log_level;
use ACEX_project\WEB\Private\Error\Resource_code;

use function ACEX_project\WEB\Private\Error\Handle_error;

    enum Query_type{
        case insert;
        case delete;
        case select;
        case update;
    }    

    /** table name(should correspond real table name) => enum name that contain all column */
    enum All_tables : string{
        case user = tb_user_columns::class;
    }

    enum tb_user_columns{
        case id_user;
        case name_user;
        case password;
        case email;
    }

    /**
     * be aware when duplicated or have whitelist and blacklist in same column: only last one validated
     *  TODO: redefine table name after model is done
    */
    if(!defined("DATABASE_PERMISSION")){
        /** array of object permission */
        define("DATABASE_PERMISSION",
            [
                new Permission(HOST,All_tables::user,[Query_type::select,Query_type::insert]),
            ]
        );
    }

    /**
     * check if input column is in origin column
     * @throws never if $origin_columns or $input_columns isn't enum or the request columns don't exist in table
     * @param \UnitEnum[] $origin_columns
     * @param \UnitEnum[] $input_columns
     */
    function Columns_allowed_inTable(All_tables $table, Array $origin_columns, Array $input_columns,bool $whitelist_column = true) : bool{
        Column_belong_to_table($table,$origin_columns);
        Column_belong_to_table($table,$input_columns);
        
        foreach($input_columns as $c) {
            $listed = in_array($c,$origin_columns,true);
            if((!$listed && $whitelist_column) || ($listed && !$whitelist_column)) return false;
        }
        return true;
    }

    /**
     * check if $columns is valid and exist in table, else terminate request early
     */
    function Column_belong_to_table(All_tables $table, array $columns) :void{
        foreach($columns as $c){
            $table_name = $table->value;
                if(!$c instanceof \UnitEnum) {
                    Handle_error(
                        new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                        Log_level::fatal,
                        'Invalid argument in Columns_belong_table:
                        Requested column isnt enum type.'
                    );
                }
            if($c::class !== $table_name){
                Handle_error(
                    new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                    Log_level::fatal,
                    'Invalid argument in Columns_belong_table:
                    Requested column dont belong table['.$table_name.'].'
                );
            }
        }
    }
    
?>