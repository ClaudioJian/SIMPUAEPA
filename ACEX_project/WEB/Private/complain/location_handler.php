<?php

namespace ACEX_project\WEB\Private\complain;


require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_location_columns;
use ACEX_project\WEB\Private\Core\File_storation_path;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;

require_once __DIR__ . "/../Error/Error_code.php";
require_once __DIR__ . "/../Error/Error_manager.php";
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Exception;
use Monolog\Level;

use function ACEX_project\WEB\Private\Api\database\Column_belong_to_table;
use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;
use function ACEX_project\WEB\Private\Api\database\Query_insert_wrapper;
use function ACEX_project\WEB\Private\Error\Log_internal;

require_once __DIR__ . "/../Core/Security/Request_manager.php";
require_once __DIR__ . "/../Core/MIME_type.php";

require_once __DIR__ . "/../Core/Security/File_upload/File_upload.php";

require_once __DIR__ . "/../Core/File_storation_map.php";

require_once __DIR__ . "/../api/database/database_manager.php";
require_once __DIR__ . "/../api/database/database_dictionary.php";
require_once __DIR__ . "/../api/database/Authorization_manager.php";


/**
 * @param null|string $invalid_reason if x or y is invalid, the message will be written
 */
function Validate_coordinate(float $coordinate, ?string &$invalid_reason) : true|int{
    //NO validation so always true
    return true;
}

function Selection_area_txt(float $x1, float $y1, float $x2, float $y2) : string{
    return "($x1,$y1),($x2,$y2)";
}

/**
 * insert if don't exist. Return the id of location whenether previous exist or no.
 * IMPORTANT: this function will not validate point x and y;
 * @param bool $auto_commit control if automatically commit when upsert is sucess
 * @param bool $auto_destroy rool back transaction automatically ignoring previous transaction in case error happen in this function
 */
function Upsert_location(float $x, float $y, bool $auto_commit = false, bool $auto_destroy = true) : int|null{
    if(!Is_query_permitted(HOST,[Query_type::insert, Query_type::update],All_tables::location)) {
        Log_internal(
            new Error_code(Error_domain::database,Resource_code::db_query,Error_condition::unauthorized),
            Level::Error,
            'insert/update failed: [table = ' .All_tables::user->name. ' | user ='. HOST. ' | column denied = "'. Query_type::insert->name . '", "'.Query_type::update->name.'"]'
        );

        return null;
    }

    $conn = null;
    try{
        $conn = Connect_database(true);
        if($conn === null) {
            Log_internal(
                new Error_code(Error_domain::database,Resource_code::connection,Error_condition::failed),
                Level::Error,
                'Connection to database failed when trying to insert/get location id: table=' .All_tables::location->name. ' | user ='. HOST
            );
            return null;
        }
        
        
        if(!$conn->inTransaction()) $conn->beginTransaction();

        $query_wrapper = Query_insert_wrapper(All_tables::location,[
            [
                'column'=>tb_location_columns::ang_x,
                'value'=>[$x]
            ],
            [
                'column'=>tb_location_columns::ang_y,
                'value'=>[$y]
            ]
        ]);

        $get_last_id_query = Upsert_return_id_query(All_tables::location,[tb_location_columns::ang_x, tb_location_columns::ang_y]);
        if($get_last_id_query === '') {
            if($auto_destroy && $conn->inTransaction()) $conn->rollBack();
            return null;
        }

        $query = "INSERT INTO " . All_tables::location->name.
            $query_wrapper['column'].
            ' VALUES '. $query_wrapper['value'].
            " {$get_last_id_query}"
        ;

        $smtm = $conn->prepare($query);
        if($smtm===false) throw new Exception('in Upsert_location(): Database server failed to prepare statement for select query');

        $smtm->execute($query_wrapper['binded_value']);
        

        $id = match(DB_TYPE){
            'mysql'=> $conn->lastInsertId(),
            'postgre'=> $smtm->fetchColumn(),
            default=>false
        };

        if($auto_commit) $conn->commit();
        if($id!==false) return (int)$id;
        return null;
    }catch(Exception $e){
        if($auto_destroy &&$conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
        Log_internal(
            new Error_code(Error_domain::server,Resource_code::unknown,Error_condition::unknown),
            Level::Error,
            'Unexpected error when trying to insert/get location id : ['. $e->getMessage().']'
        );
        return null;
    }
}


function Upsert_return_id_query(All_tables $table, array $conflict_columns) : string{
    $id_column = $table->Get_primaryKey();

    $conflicted_col = [];

    foreach($conflict_columns as $col){
        if(!Column_belong_to_table($table,$conflict_columns,false)) return '';
        $conflicted_col[] = $col->name;
    }
    $conflicted_cols = \implode(', ', $conflicted_col);

    return match(DB_TYPE){
        'mysql'=>"ON DUPLICATE KEY UPDATE {$id_column} = LAST_INSERT_ID({$id_column})",
        'postgre'=>"ON CONFLICT($conflicted_cols) DO UPDATE SET ".$conflicted_col[0]." = EXCLUDED.". $conflicted_col[0] ." RETURNING {$id_column}",
        default=>''
    };
}