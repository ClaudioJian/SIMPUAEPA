<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts general useful function for database.                                                 |
  | Also is master file that include everything,so you can require this file to get majority funct  |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Api\database;
    use PDO;
    use PDOException;

    require_once __DIR__ . "/../../Core/AppCommonVar.php";
    require_once __DIR__ . "/../../Error/Error_code.php";
    require_once __DIR__ . "/../../Error/Error_manager.php";
    use ACEX_project\WEB\Private\Error\Error_Code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Exception;
    use Throwable;

    use function ACEX_project\WEB\Private\Error\MyArray_check;
    use function ACEX_project\WEB\Private\Error\MyArray_missing_key;
    use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

    /**
     * connect to database.
     * auto exit database when error occur.
     * @return PDO sucess: object pointer to connection of database
     * @return null and throw error when fail
     */
    function Connect_database() :PDO|null{
        $dns = "mysql: host=" .HOST. "port=".DB_PORT.";dbname=".DB_NAME;

        try{
            $conn = new PDO($dns,SERVER_USER,DB_PASSWORD);
        }catch(PDOException $e){
            $conn = NULL;
            Handle_error(
                new Error_Code(Error_domain::database,Resource_code::connection,Error_condition::unknown),
                Log_level::error,
                $e->getMessage()
            );
            return null;
        }
        return $conn;
    }


    /**
     * WARNING: if user supplied values/table/column is used, make sure they have filtered with allow list and have input validation if possible. generally user supplied value should be avoided from this function. Also the value inserted should have authorizathion and authentication check.
     * @param list<array{
     *     table: All_tables,
     *     insert: list<array{
     *         column: \UnitEnum,
     *         value: mixed
     *     }>
     * }> $query_arr
     */
    function Database_simple_insert(array $query_arr) :bool{
        $conn = null;
        try{            
            $conn = Connect_database();
            $conn->beginTransaction();

            foreach($query_arr as $tb_query) {
                $table = $tb_query['table'] ?? MyArray_missing_key('table','Database_simple_insert','query_arr');
                if(!$table instanceof All_tables){
                    Handle_error(
                        new Error_Code(Error_domain::database,Resource_code::syntax,Error_condition::type),
                        Log_level::fatal,
                        'Invalid argument $query_arr in Database_simple_insert(): '
                        . 'the value of "table" isnt All_tables.'
                    );
                }

                $insert_arr = $tb_query['insert'] ?? MyArray_missing_key('insert','Database_simple_insert','query_arr');
                MyArray_check($insert_arr,'Invalid argument $query_arr in Database_simple_insert(): the value of "insert" isnt array');

                $arr_query = Query_insert_wrapper($table, $insert_arr);
                $query = "INSERT INTO " 
                    . $table->name
                    . $arr_query['column']
                    . ' VALUES '.$arr_query['value'];

                $smtm = $conn->prepare($query);

                foreach($arr_query['binded_value'] as $name=>$values){
                    $flags = Find_php_param_from_value($values);

                    if($flags!==null) $smtm->bindValue($name,$values,$flags);
                    else $smtm->bindValue($name,$values);
                    
                }
                $smtm->execute();
            }
            $conn->commit();
        }catch(Throwable $e){
            if ($conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
            $conn=null;

            Log_internal(
                new Error_Code(Error_domain::database,Resource_code::db_insert,Error_condition::unknown),
                Log_level::error,
                $e->getMessage()
            );
            return false;
        }
        $conn=null;
        return true;
    }

    /**
     * Wraps query parameters into column and named-value strings.
     *
     * @param list<array{
     *     column: \UnitEnum,
     *     value: mixed
     * }> $all_param
     * 
     * @return array{
     *     column: string,
     *     value: string,
     *     binded_value: array<string, mixed>
     * }
     */
    function Query_insert_wrapper(All_tables $table, array $all_param):array{
        $column_names = $value_name = $binded_values = [];

        $counter = 1;
        foreach($all_param as $params){
            MyArray_check($params,'Invalid argument $all_param: the value isnt array');
            //validation
            $column = $params['column'] ?? MyArray_missing_key('column','Query_insert_wrapper','all_param');
            Column_belong_to_table($table,[$column]);
            
            $value = $params['value'] ?? MyArray_missing_key('value','Query_insert_wrapper','all_param');

            $named_value = Generate_value_name($counter,$table->name);
            
            $column_names[] = $column->name;
            $value_name[] = $named_value;
            $binded_values[$named_value] = $value;
            $counter ++;
        }
        
        
        return [
            'column'=> '(' . implode(",",$column_names) . ')',
            'value'=> '(' . implode(",",$value_name) . ')',
            'binded_value'=> $binded_values
        ];
    }

    /**
     * return: table name(if present) + value(prefix) + unique indentifier or order(string)
     */
    function Generate_value_name(int $id,string $table_name = ""):string{
        $tb_name = $table_name === '' ? "" : $table_name."_";
        return ":".$tb_name."value_".$id."_";
    }

    /**if type is null, type should using default type */
    function Find_php_param_from_value(mixed $value) : null|int{
        $type = gettype($value);
        switch($type){
            case 'boolean': case 'bool': 
                return PDO::PARAM_BOOL;
            case 'integer': case 'int':
                return PDO::PARAM_INT;
            case 'string':
                return PDO::PARAM_STR;
            case 'null':
                return PDO::PARAM_NULL;
            default:
                return null;
        }
    }

?>