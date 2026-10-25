<?php

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

use function ACEX_project\WEB\Private\Api\database\Column_belong_to_table;
use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\PDO_Mybind_params;
use function ACEX_project\WEB\Private\Error\Log_internal;

if(\php_sapi_name() !== "cli") {
    \http_response_code(403);
    exit();
}


require_once __DIR__ . "/../../../Private/Api/database/database_manager.php";
require_once __DIR__ . "/../../../Private/Api/database/database_dictionary.php";
require_once __DIR__ . "/../../../Private/Error/Error_manager.php";
require_once __DIR__ . "/../../../Private/Error/Error_code.php";


class Schema{
    /**
     * @param array{string,mixed} $params expect: [value_name => value]
     * @param string $version_name if not specified, then equal: v<`$version`>.0 else
     * remove "_", ".", "-" in after "v" or "version"
     * if not started with v or version, add in start "v" and add in end original `version_name`
     */
    public function __construct(
        public readonly int $version,
        private string $query,
        private array $params = [],
        public string $version_name = ""
    ){
        $version_name = trim($version_name);


        if($version_name === "") $version_name = "v$version.0"; 
        elseif(preg_match("/^(v|version)?[_.-]*(.+)/i",$version_name)){
            $version_name = preg_replace(
                "/^(?:v|version)?[_.-]*|^/i",
                "v",
                $version_name
            );
        }else $version_name = "v$version_name";

        $this->version_name = $version_name;
    }

    public function execute() : bool{
        if($this->version < SCHEMA_VERSION) return true;

        $msg = "Cannot execute database schema [$this->version_name]:";
        $conn = null;
        try{
            $conn = Connect_database();
            if($conn === null) {
                Log_internal(new Error_code(Error_domain::database,Resource_code::connection,Error_condition::failed),
                    Level::Error,
                    "Cannot start execute database schema [{$this->version_name}]"
                );
                return false;
            }

            $conn->beginTransaction();
            $smtm = $conn->prepare($this->query);
            if($smtm===false) {
                $conn->rollBack();
                Log_internal(new Error_code(Error_domain::database,Resource_code::db_query,Error_condition::failed),
                    Level::Error,
                    "{$msg} failed to prepare statement\n[Query]: \"\n$this->query\n\""
                );
                return false;
            }

            $success = $this->params !== [] ? PDO_Mybind_params($this->params,$smtm) : $smtm->execute();
            
            if(!$success) {
                if($conn->inTransaction()) $conn->rollBack();
                Log_internal(new Error_code(Error_domain::database,Resource_code::db_query,Error_condition::failed),
                    Level::Error,
                    "{$msg} Failed to execute query\n[Query]: \"\n$this->query\n\""
                );
                return false;
            }

            if($conn->inTransaction()) $conn->commit();

            Log_internal(new Error_code(Error_domain::database,Resource_code::db_query,Error_condition::success),
                    Level::Notice,
                    "Database schema [$this->version_name] executed sucessfully! \n[Query]: \"\n{$this->query}\n\""
                );
            return true;
        }catch(Throwable $e){
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
            Log_internal(new Error_code(Error_domain::database,Resource_code::db_query,Error_condition::unknown),
                Level::Error,
                "Cannot execute database schema[version - $this->version_name]: ". $e->getMessage(). "\n[Query]: \"\n$this->query\n\""
            );
            return false;
        }
    }
}

/**
 * @param list<
 *  array{
 *      name: \UnitEnum,
 *      type: string
 * }
 * > $columns where name should enums of respective colums definied in enum All_tables and type is like UNSIGNED INT
 * @param string $extra_info for example, UNIQUE(a,b)
 * @return string never if column not belong to table
 */
function Create_TABLE_SQL_Wrapper(All_tables $table, array $columns, string $extra_info = "") : string{
    $all_columns = [];

    foreach($all_columns as $c) $all_columns[] = $c['name'];
    Column_belong_to_table($table,$all_columns);

    $create_sql = "CREATE TABLE $table->name(" . 
            implode(", ", 
                array_map(
                    static fn(array $column) => $column['name']->name .' '. $column['type'],
                    $columns
                )
            ).
        ", $extra_info".
        ');'
    ;

    return $create_sql;
}

