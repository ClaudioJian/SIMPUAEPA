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

    require_once __DIR__ ."/../../Error/Error_manager.php";
    use function ACEX_project\WEB\Private\Error\Handle_error;

    require_once __DIR__ ."/../../Error/Error_code.php";

use ACEX_project\WEB\Private\Core\File_storation_path;
use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Monolog\Level;

    enum Query_type{
        case insert;
        case delete;
        case select;
        case update;
    }    

    /** table name(should correspond real table name) => enum name that contain all column */
    enum All_tables : string{
        case user = tb_user_columns::class;
        case complain = tb_complain_columns::class;
    }

    enum tb_user_columns{
        case id;
        case name;
        case email;
        case password;
        case profile_image_name;
        case theme;
        case credibility_score;
        case deleted;
        case access_stricted;
    }

    enum tb_complain_columns{
        case id;
        case title;
        case description;
        case created_at;
        case location_id;
        case user_id;
        case type;
        case intensity_level;
        case image_name;
        case like_count;
        case dislike_count;
        case resolved;
        case priority_level;
    }

    /**
     * be aware when duplicated or have whitelist and blacklist in same column: only last one validated
     *  TODO: redefine table name after model is done
    */
    if(!\defined("DATABASE_PERMISSION")){
        /** array of object permission */
        \define("DATABASE_PERMISSION",
            [
                new Permission(HOST,All_tables::user,[Query_type::select,Query_type::insert,Query_type::update]),
            ]
        );
    }

    /**
     * @return array{
     * table: All_tables,
     * column: \UnitEnum,
     * user_id: \UnitEnum,
     * public: bool|array
     * } user_id is column for user id; public is for authorization layer, if is array(current not supported), will point to table and column for shared file for authorized group;
     * If not found, return empty array
     */
    function Get_img_db_location(File_storation_path $category) : array{
        return match($category){
            File_storation_path::profiles => [
                'table' => All_tables::user,
                'column' => tb_user_columns::profile_image_name,
                'user_id' => tb_user_columns::id,
                'public' => true
            ],
            File_storation_path::Maps => [
                'table' => All_tables::complain,
                'column' => tb_complain_columns::image_name,
                'user_id' => tb_complain_columns::user_id,
                'public' => true
            ],
            default=>[]
        };
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
            $listed = \in_array($c,$origin_columns,true);
            if((!$listed && $whitelist_column) || ($listed && !$whitelist_column)) return false;
        }
        return true;
    }

    /**
     * check if $columns is valid and exist in table, else terminate request early
     * @param \UnitEnum[] $colums
     */
    function Column_belong_to_table(All_tables $table, array $columns, bool $exit_if_error = true) :bool{
        $table_name = $table->value;
        foreach($columns as $c){
                if(!$c instanceof \UnitEnum) {
                    if(!$exit_if_error) return false;
                    Handle_error(
                        new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                        Level::Critical,
                        'Invalid argument in Columns_belong_table:
                        Requested column isnt enum type.'
                    );
                }
            if($c::class !== $table_name){
                if(!$exit_if_error) return false;
                Handle_error(
                    new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                    Level::Critical,
                    "Invalid argument in Columns_belong_table:
                    Requested column dont belong table [$table_name]."
                );
            }
        }
        return true;
    }
    
?>