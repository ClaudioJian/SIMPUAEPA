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
    use InvalidArgumentException;
    use Monolog\Level;
    use PDOStatement;
    use Throwable;
    use PDO;
    use PDOException;

    use function ACEX_project\WEB\Private\Error\MyArray_check;
    use function ACEX_project\WEB\Private\Error\MyArray_missing_key;

    require_once __DIR__ .'/database_dictionary.php';


    /**
     * connect to database.
     * auto exit database when error occur.
     * @param bool $keep_connection if this set to true, same connection is returned, else new connection
     * @return PDO success: object pointer to connection of database
     * @return null and throw error when fail
     */
    function Connect_database(bool $keep_connection = true, bool $db_exist = true) :PDO|null{
        $dns = "mysql: host=" .HOST. "port=".DB_PORT;
        if($db_exist) $dns .= ";dbname=".DB_NAME;

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
     * Invalid will exit application. Output: FOREIGN KEY(column_origin) REFERENCES table_ref(column_ref)
     * @param list<array{
     *      'table_origin':All_tables,
     *      'column_origin':\UnitEnum,
     *      'table_ref':All_tables,
     *      'column_ref':\UnitEnum,
     *      'constraint': array{
     *          'name':string,
     *          'args': list<array{
     *              'action': ForeignKey_constraint,
     *              'reference_option':ForeignKey_constraint
     *          }>
     *      }
     * }   
     * > $references. ref is primary key, origin is foreign key
     */
    function ForeignKey_reference_wrapper(array $references, ?array $bind_value = []) : string{
        $table_origin = null;
        $table_ref = null;
        $column_origin = null;
        $column_ref = null;        

        try{
            $attributes = [];
            $bind_value = [];

            foreach($references as $ref){
                if(!\is_array($ref)) throw new InvalidArgumentException('Argument is not list of array');

                $table_origin = $ref['table_origin'] ?? throw new InvalidArgumentException('Table origin is missing');
                if(!$table_origin instanceof All_tables) throw new InvalidArgumentException('Table origin must be enum All_tables');
                
                $table_ref = $ref['table_ref'] ?? throw new InvalidArgumentException('Referenced Table for primary key is missing');
                if(!$table_ref instanceof All_tables) throw new InvalidArgumentException('Referenced Table for primary key must be enum All_tables');

                $column_origin = $ref['column_origin'] ?? throw new InvalidArgumentException('column origin is missing');
                if(!Column_belong_to_table($table_origin, [$column_origin],false)) throw new InvalidArgumentException('column origin does not belong to referenced table');

                $column_ref = $ref['column_ref'] ?? throw new InvalidArgumentException('Referenced column for primary key is missing');
                if(!Column_belong_to_table($table_ref, [$column_ref],false)) throw new InvalidArgumentException('Referenced column does not belong to referenced table');

                
                $constraints = $ref['constraint']?? [];
                if(!\is_array($constraints)) throw new InvalidArgumentException('Constraint must be array');
                
                $foreignKey_clause = "FOREIGN KEY($column_origin->name) REFERENCES {$table_ref->name}($column_ref->name)";
                
                $constraint_parts = $sql_parts = [];

                if($constraints !== []) {
                    $name = $constraints['name']??null;

                    if($name !== null) {
                        if(!\is_string($constraints['name']) && trim($constraints['name']) === '') throw new InvalidArgumentException();
                        $constraint_bind_name = uniqid('constraint_name_');
                        $sql_parts[] = "CONSTRAINT :{$constraint_bind_name}";
                        $bind_value[$constraint_bind_name] = $constraints['name'];
                    }

                    $constraint_args = $constraints['args'] ?? [];
                    if(!\is_array($constraint_args)) throw new InvalidArgumentException('Constraint arguments must be array with action and reference_option');

                    //avoid duplication
                    $used_action = [];
                    foreach($constraint_args as $c_args){
                        if(!\is_array($c_args)) throw new InvalidArgumentException('Constraint arguments must be array');
                        
                        $action = $c_args['action']?? throw new InvalidArgumentException('Constraint action for foreign key is missing');
                        if(\in_array($action,$used_action,true)) throw new InvalidArgumentException('Duplicated constraint action: "'. $action->name.'"');

                        $const_ref = $c_args['reference_option']?? throw new InvalidArgumentException('Constraint reference option for foreign key is missing');
                        
                        if(!$action instanceof ForeignKey_constraint || !$const_ref instanceof ForeignKey_constraint) throw new InvalidArgumentException('Constraint for foreign key must be enum ForeignKey_constraint');
                        if($action->is_reference_option()) throw new InvalidArgumentException('Invalid constrant action: "'. $action->value. '" provided');
                        if(!$const_ref->is_reference_option()) throw new InvalidArgumentException('Invalid constrant reference option: "'. $const_ref->value. '" provided');
                        
            
                        $constraint_parts[] = $action->value;
                        $used_action[] = $action;
                        $constraint_parts[] = $const_ref->value;
                    }
                }

                $sql_parts[] = $foreignKey_clause;
                if($constraint_parts !== []) $sql_parts = [...$sql_parts, ...$constraint_parts];
                

                $attributes[] = trim(\implode(' ', $sql_parts));
            }

            if(count($attributes)===1) return $attributes[0];
            return implode(', ', $attributes);
        }catch(InvalidArgumentException $e){
            $query_info = Query_info($table_origin,$column_origin,$table_ref,$column_ref);

            Handle_error(
                new Error_Code(Error_domain::server, Resource_code::invalidArgument, Error_condition::type),
                Level::Critical,
                'Error in function ForeignKey_reference_wrapper(): '.$e->getMessage() . " $query_info"
            );
            exit();
        }catch(Exception $e){
            $query_info = Query_info($table_origin,$column_origin,$table_ref,$column_ref);
            Handle_error(
                new Error_Code(Error_domain::server, Resource_code::unknown, Error_condition::unknown),
                Level::Critical,
                'Unexpected error in function ForeignKey_reference_wrapper(): '.$e->getMessage() . " $query_info"
            );
            exit();
        }
    }


    function Query_info(?All_tables $table, ?\UnitEnum $origin_column, ?All_tables $ref_table, ?\UnitEnum $ref_column) : string{
        $query_info = [];
        if($table !== null) $query_info[] = "foreign key table  - \"{$table->name}\"";
        if($origin_column !== null) $query_info[] = "foreign key column  - \"{$origin_column->name}\"";
        if($ref_table !== null) $query_info[] = "primary key table  - \"{$ref_table->name}\"";
        if($ref_column !== null) $query_info[] = "primary key column \"{$ref_column->name}\"";

        return '['.\implode(' | ', $query_info).']';
    }

    /**
     * WARNING: if user supplied values/table/column is used, make sure they have filtered with allow list and have input validation if possible. generally user supplied value should be avoided from this function. Also the value inserted should have authorizathion and authentication check.
     * @param bool $continue control if to use same db connection and transaction
     * @param bool $auto_commit cintrol if to commit once finish
     *  @param list<array{
     *     table: All_tables,
     *     insert: list<array{
     *         column: \UnitEnum,
     *         value: list<mixed>,
     *     }>
     * }> $query_arr, on_duplicate set to null mean IGNORE the duplicate. If is array, will do update. Other form to handle conflit is not supported
     * @return bool|list<array{table:All_tables,id:string}> false if failed else return inserted id(multi row return FIRST id)
     */
    function Database_simple_insert(array $query_arr,bool $use_transaction = false, bool $continue=false, bool $auto_commit = true) :bool|array{
        $conn = null;
        try{
            $conn = Connect_database($continue);
            $id_list = [];

            if($conn === null) Handle_error(new Error_Code(Error_domain::request,Resource_code::credential,Error_condition::failed),Level::Critical,"Cannot create account because database failed to connect");
            
            //if use transaction and don't want continue previous transaction, cancel previous transaction
            if($use_transaction && !$continue) {
                if($conn->inTransaction()) $conn->rollBack();
                $conn->beginTransaction();
            }
            

            foreach($query_arr as $tb_query) {
                MyArray_check($tb_query,'Values of list $query_arr','Database_simple_insert');

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
                MyArray_check($insert_arr,'inside of list $query_arr, one of array with key "insert"','Database_simple_insert');

                $arr_query = Query_insert_wrapper($table, $insert_arr);
                $query = "INSERT INTO " 
                    . $table->name
                    . $arr_query['column']
                    . ' VALUES '.$arr_query['value'];

                $smtm = $conn->prepare($query);
                if($smtm===false) throw new Exception('Database server failed to prepare statement');

                PDO_Mybind_params($arr_query['binded_value'],$smtm);
                $last_id = $conn->lastInsertId();
                if($last_id !== false) $id_list[] = ['table'=>$table,'id'=>$last_id];
            }
            if($use_transaction && $auto_commit) $conn->commit();
        }catch(Throwable $e){
            if($conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
            Handle_query_fail($e,$conn,Resource_code::db_insert);
            return false;
        }
        if($auto_commit) $conn=null;
        
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
     * 
     * @return array{
     *     column: string,
     *     value: string,
     *     binded_value: array<string, mixed>,
     * }
     */
    function Query_insert_wrapper(All_tables $table, array $all_columns):array{
        $column_names = $value_names = $binded_values = $row_names = [];

        $max_row_count = 0;
        $column_idx = 1;
        foreach($all_columns as $column_arr){
            MyArray_check($column_arr,'One of array inside list "$all_columns"','Query_insert_wrapper');
            //validation
            $column = $column_arr['column'] ?? MyArray_missing_key('column','Query_insert_wrapper','all_param');
            Column_belong_to_table($table,[$column]);
            
            $all_column_value = $column_arr['value'] ?? MyArray_missing_key('value','Query_insert_wrapper','all_param');
            MyArray_check($all_column_value ,'One of array with key "value" inside "$all_columns"','Query_insert_wrapper');

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
            'binded_value'=> $binded_values,
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
     *      condition: string,
     *      binded_value: array
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
            MyArray_check($curr_condition,'values of $all_column_value','Simple_WHERE_wrapper');

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
            $table = $curr_condition['table'] ?? null;

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
    function Generate_value_name(int $column_id,int $row_id ,string $table_name = "", string $prefix = ""):string{
        $tb_name = $table_name === '' ? "" : $table_name."_";
        if($prefix !== "") $tb_name .= "{$prefix}_";
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