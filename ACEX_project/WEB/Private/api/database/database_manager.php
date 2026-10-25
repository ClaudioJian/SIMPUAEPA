<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts general useful function for database.                                                 |
  | Also is master file that include everything,so you can require this file to get majority funct  |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Api\database;


    require_once __DIR__ . "/../../Core/AppCommonVar.php";

    require_once __DIR__ . "/../../Error/Error_code.php";
    use ACEX_project\WEB\Private\Error\Error_Code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;

    require_once __DIR__ . "/../../Error/Error_manager.php";
    use function ACEX_project\WEB\Private\Error\Handle_error;
    use function ACEX_project\WEB\Private\Error\Log_internal;
    
    use Exception;
    use Monolog\Level;
    use PDOStatement;
    use Throwable;
    use PDO;
    use PDOException;

    use function ACEX_project\WEB\Private\Error\MyArray_check;
    use function ACEX_project\WEB\Private\Error\MyArray_missing_key;


    /**
     * connect to database.
     * auto exit database when error occur.
     * @param bool $keep_connection if this set to true, same connection is returned, else new connection
     * @return PDO success: object pointer to connection of database
     * @return null and throw error when fail
     */
    function Connect_database(bool $keep_connection = true) :PDO|null{
        $dns = "mysql: host=" .HOST. "port=".DB_PORT.";dbname=".DB_NAME;
        static $conn = null;
        if(!$keep_connection) $conn = null;
        if($conn === null){
            try{
                $conn = new PDO($dns,SERVER_USER,DB_PASSWORD);
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }catch(PDOException $e){
                Handle_error(
                    new Error_Code(Error_domain::database,Resource_code::connection,Error_condition::unknown),
                    Level::Warning,
                    err_msg:$e->getMessage()
                );
                $conn = NULL;
                return null;
            }
        }
        
        return $conn;
    }


    /**
     * WARNING: if user supplied values/table/column is used, make sure they have filtered with allow list and have input validation if possible. generally user supplied value should be avoided from this function. Also the value inserted should have authorizathion and authentication check.
     * @param list<array{
     *     table: All_tables,
     *     insert: list<array{
     *         column: \UnitEnum,
     *         value: list<mixed>
     *     }>
     * }> $query_arr
     * @return bool|list<array{table:All_tables,id:string}> false if failed else return inserted id(multi row return FIRST id)
     */
    function Database_simple_insert(array $query_arr,bool $use_transaction = false) :bool|array{
        $conn = null;
        try{
            $conn = Connect_database();
            $id_list = [];

            if($conn === null) Handle_error(new Error_Code(Error_domain::request,Resource_code::credential,Error_condition::failed),Level::Critical,"Cannot create account because database failed to connect");
            

            if($use_transaction) $conn->beginTransaction();

            foreach($query_arr as $tb_query) {
                $table = $tb_query['table'] ?? MyArray_missing_key('table','Database_simple_insert','query_arr');
                if(!$table instanceof All_tables){
                    Handle_error(
                        new Error_Code(Error_domain::database,Resource_code::invalidArgument,Error_condition::type),
                        Level::Critical,
                        'In Database_simple_insert($query_arr):
                        the value of "table" isnt [enum]All_tables.'
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
                if($smtm===false) throw new Exception('Database server failed to prepare statement');

                PDO_Mybind_params($arr_query['binded_value'],$smtm);
                $last_id = $conn->lastInsertId();
                if($last_id) $id_list[] = ['table'=>$table,'id'=>$last_id];
            }
            if($use_transaction) $conn->commit();
        }catch(Throwable $e){
            Handle_query_fail($e,$conn,Resource_code::db_insert);
            return false;
        }
        $conn=null;
        
        return $id_list;
    }

    function Handle_query_fail(Throwable $e, PDO|null &$conn,Resource_code $query = Resource_code::db_query){
        if ($conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();

        Log_internal(
            new Error_Code(Error_domain::database,$query,Error_condition::unknown),
            Level::Error,
            $e->getMessage()
        );

        $conn=null;
        \http_response_code(500);
        exit();
    }

    /**
     * Bind values and execute. May throw error after execute
     * @param array{string,mixed} $key_val_pair expect: [value_name => value]
     * @throws PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    function PDO_Mybind_params(array $key_val_pair,PDOStatement $smtm) : bool{
        foreach($key_val_pair as $name=>$values){
                $flags = Find_php_param_from_value($values);

                if($flags!==null) $smtm->bindValue($name,$values,$flags);
                else $smtm->bindValue($name,$values);
            }
        return $smtm->execute();
    }

    /**
     * Wraps query parameters into column and named-value strings.
     *
     * @param list<array{
     *     column: \UnitEnum,
     *     value: list<mixed>
     * }> $all_columns
     * 
     * @return array{
     *     column: string,
     *     value: string,
     *     binded_value: array<string, mixed>
     * }
     */
    function Query_insert_wrapper(All_tables $table, array $all_columns):array{
        $column_names = $value_names = $binded_values = $row_names = [];

        $max_row_count = 0;
        $column_idx = 1;
        foreach($all_columns as $column_arr){
            
            MyArray_check($column_arr,'Invalid argument $all_param: the value isnt array');
            //validation
            $column = $column_arr['column'] ?? MyArray_missing_key('column','Query_insert_wrapper','all_param');
            Column_belong_to_table($table,[$column]);
            
            $all_column_value = $column_arr['value'] ?? MyArray_missing_key('value','Query_insert_wrapper','all_param');
            MyArray_check($all_column_value ,'Invalid argument $all_param: the value isnt array');

            $row_count = \count($all_column_value);
            if($max_row_count === 0) $max_row_count = $row_count;
            else if($max_row_count !== $row_count) Handle_error(
                new Error_Code(Error_domain::database,Resource_code::invalidArgument,Error_condition::quantity),
                Level::Critical,
                "In Query_insert_wrapper(): expected $max_row_count values for column $column->name, but only $row_count were provided." 
            );

            //current array:
            //[value1,value2,value3] for column x = $counter
            //should become: $value_names = [
            //   '0'=>[table1_column_1_row_0,table1_column_2_row_0,table1_column_3_row_0],
            //   '1'=>[table1_column_1_row_1,table1_column_2_row_1,table1_column_3_row_1]
            //]
            for($row_idx=0; $row_idx < $row_count ; $row_idx++){
                $value = $all_column_value[$row_idx];
                $named_value = Generate_value_name($column_idx,$row_idx+1,$table->name);

                $value_names[$row_idx][$column_idx] = $named_value;
                $binded_values[$named_value] = $value;
            }


            $column_names[] = $column->name;
            $column_idx ++;
        }
        
        // [[x,y,z],[x,y,z]] => [(x,y,z),(x,y,z)]
        foreach($value_names as $row) {$row_names[] = '(' . \implode(",",$row) . ')';}
        
        //[(x,y,z),(x,y,z)]=>(x,y,z),(x,y,z)
        return [
            'column'=> '(' . \implode(",",$column_names) . ')',
            'value'=> \implode(",",$row_names),
            'binded_value'=> $binded_values
        ];
    }

    /**
     * @param list<array{
     *     column: \UnitEnum,
     *     value: mixed,
     *     table: All_tables|null
     * }> $all_column_value
     * @param bool $and control if all condition is AND or OR
     * @return array{
     * condition: string,
     * binded_value: array
     * } condition: WHERE column=:value_name,...
     * binded_value = [value_name => binded value]
     */
    function Simple_WHERE_wrapper(array $all_column_value, bool $and = true) : array{
        if($all_column_value === []) return [
            'condition' => '',
            'binded_value' => []
        ];

        $binded_values = $all_condition = [];

        $condition_count = \count($all_column_value);

        for($i=0; $i < $condition_count; $i++){
            $curr_condition = $all_column_value[$i];

            $column = $curr_condition['column'] ?? MyArray_missing_key('column','Simple_WHERE_wrapper','all_column_value');

            if(!$column instanceof \UnitEnum) {
                Handle_error(
                    new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                    Level::Critical,
                    'Invalid argument in Simple_WHERE_wrapper:
                    Each condition must contain valid enum type.'
                );
            }

            $value = $curr_condition['value'] ?? MyArray_missing_key('value','Simple_WHERE_wrapper','all_column_value');


            if(isset($curr_condition['table']) && !$curr_condition['table'] instanceof All_tables) {
                Handle_error(
                    new Error_code(Error_domain::database,Resource_code::db_column,Error_condition::type),
                    Level::Critical,
                    'Invalid argument in Simple_WHERE_wrapper:
                    Requested table isnt enum All_table.'
                );
            }

            $table = $table?->name ?? '';

            $named_value = Generate_value_name($i,$i+1,$table);

            $all_condition[] = "{$column->name} = $named_value";
            $binded_values[$named_value] = $value;
        };

        $and = $and ? 'AND' : 'OR';

        return [
            'condition'=> 'WHERE '. \implode(" $and ",$all_condition),
            'binded_value'=> $binded_values
        ];
    }

    /**
     * return: table name(if present) + column(prefix) + unique column indentifier or order(string) + row id, e.g ':user_column_1_row_1'
     */
    function Generate_value_name(int $column_id,int $row_id ,string $table_name = ""):string{
        $tb_name = $table_name === '' ? "" : $table_name."_";
        return ":".$tb_name."column_".$column_id."_row_".$row_id;
    }

    /**if type is null, type should using default type */
    function Find_php_param_from_value(mixed $value) : null|int{
        $type = \gettype($value);
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