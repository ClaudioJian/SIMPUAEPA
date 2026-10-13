<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts general useful function for database.                                                 |
  | Also is master file that include everything,so you can require this file to get majority funct  |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Api\database;

    require_once __DIR__ . "/../../Error/Error_code.php";
    require_once __DIR__ . "/../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_Code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use InvalidArgumentException;

    use function ACEX_project\WEB\Private\Error\Handle_error;

    /**
     * find if permitted from query type to table and column
     * @param string $user_name account for connect to database
     * @param All_tables $table 
     * @param \UnitEnum[]  $column, should use same name as $table's value
     * @param Query_type[] $query_type if multiple query type, mean the column and table permission is shared amoung query, for example, table user can have permission to select and insert in column date
     * @return bool false: no permission or table not found, true: permitted
     */
    function Is_query_permitted(string $user_name,array $query_type, All_tables $table, array $column = []) : bool{
        foreach(DATABASE_PERMISSION as $permission){
            if($permission->Is_permitted($user_name,$table,$query_type,$column)) return true;
        }
        return false;
    }
    
    /**
     * TODO: make colmn,user,table into enum
     */
    class Permission{
        public readonly string $user_name;
        public readonly All_tables $table;
        public readonly array $column;
        public readonly array $query_type;
        public readonly bool $whitelist_column;

        /**
         * @param Query_type[] $query_type
         * @param \UnitEnum[]    $column, should use same name as $table's value
         *
         * Empty $column means all columns.
         */
        public function __construct(string $user_name, All_tables $table,array $query_type, array $column=[], bool $whitelist_column= false)
        {
            if($query_type === []) throw new InvalidArgumentException('At least one query type is required');
            foreach($query_type as $type){
                if (!$type instanceof Query_type) Throw_invalid_query_type("Permission::__construct()");
            }

            $table_enum_name = $table->value;

            foreach ($column as $c) {
                if (!$c instanceof \UnitEnum || $c::class !== $table_enum_name) {
                    Handle_error(
                        new Error_Code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                        Log_level::fatal,
                        'Invalid argument in Permission::__construct(): Requested table does not have one of the specified columns.'
                    );
                }
            }

            $this->user_name = strtolower($user_name);
            $this->table = $table;
            $this->column = $column;
            $this->query_type = $query_type;
            $this->whitelist_column = $whitelist_column;
        }


        /**
         * @param \UnitEnum[] $column if not specified, rejected if registered column permission
         */
        public function Is_permitted(string $user_name, All_tables $table,array $query_type, array $column = []) : bool{
            if($table !== $this->table || $query_type ===[]) return false;
            foreach($query_type as $type){
                if(!$type instanceof Query_type) Throw_invalid_query_type("Permission::Is_permitted");
                if(!in_array($type,$this->query_type,true)) {
                    return false;
                }
            }

            $user_name = strtolower($user_name);
            if($user_name !== $this->user_name) return false;
            

            // Empty permission list means all columns are permitted.            
            if($this->column===[]) return true;
            // A restricted permission requires requested columns.
            if($column===[]) return false;

            return Columns_allowed_inTable($table,$this->column,$column,$this->whitelist_column);
        }
    }

    function Throw_invalid_query_type(string $func_name){
        Handle_error(
            new Error_Code(Error_domain::database,Resource_code::syntax,Error_condition::type),
            Log_level::fatal,
            'Invalid argument in '. $func_name .': $query_type must contain only Query_type enum values.'
        );
    }




?>