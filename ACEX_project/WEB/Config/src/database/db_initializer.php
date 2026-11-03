<?php

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\ForeignKey_constraint;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Api\database\tb_location_columns;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

use function ACEX_project\WEB\Private\Api\database\ForeignKey_reference_wrapper;
use function ACEX_project\WEB\Private\Api\database\SQL_get_current_time;
use function ACEX_project\WEB\Private\Error\Handle_error;

if(\php_sapi_name() !== "cli") {
    \http_response_code(403);
    exit();
}

define('DB_SCHEMA',true);

require_once __DIR__ . "/../../../Private/Api/database/database_manager.php";
require_once __DIR__ . "/../../../Private/Api/database/database_dictionary.php";
require_once __DIR__ . "/../../../Private/Error/Error_manager.php";
require_once __DIR__ . "/../../../Private/Error/Error_code.php";

require_once __DIR__ ."/schema_class.php";

define("SCHEMA_VERSION",1);




$all_schema = [
    new Schema(1, 
    'DROP DATABASE IF EXISTS '. DB_NAME .";\n".
    'CREATE DATABASE '. DB_NAME.";\n".
    'use '. DB_NAME .";\n\n".
    Create_TABLE_SQL_Wrapper(All_tables::user, [
                ['name'=>tb_user_columns::id, 'type'=>'INT UNSIGNED PRIMARY KEY AUTO_INCREMENT'],
                ['name'=>tb_user_columns::email, 'type'=> 'VARCHAR(255)'],
                ['name'=>tb_user_columns::name,'type'=>'VARCHAR('. MAX_USER_NAME_LENGTH .')'],
                ['name'=>tb_user_columns::password, 'type'=>'VARCHAR(255)'],
                ['name'=>tb_user_columns::profile_image_name, 'type'=>'CHAR(18)'],
                ['name'=>tb_user_columns::deleted, 'type'=>'BOOL DEFAULT 0'],
                ['name'=>tb_user_columns::theme, 'type'=>'BOOL DEFAULT 0'],
                ['name'=>tb_user_columns::credibility_score, 'type'=>'CHAR(18)'],
                ['name'=>tb_user_columns::access_stricted, 'type'=>'BOOL DEFAULT 0'],
            ],
            'UNIQUE('.tb_user_columns::email->name.','. tb_user_columns::name->name.')'
        ). "\n\n".
    Create_TABLE_SQL_Wrapper(All_tables::location, [
                ['name'=>tb_location_columns::id, 'type'=>'INT UNSIGNED PRIMARY KEY AUTO_INCREMENT'],
                ['name'=>tb_location_columns::ang_x, 'type'=> 'DECIMAL(20,6)'],
                ['name'=>tb_location_columns::ang_y, 'type'=> 'DECIMAL(20,6)'],
            ],
            'UNIQUE('.tb_location_columns::ang_x->name .','. tb_location_columns::ang_y->name.')'
        ) . "\n\n" .
    Create_TABLE_SQL_Wrapper(All_tables::complain, [
                ['name'=>tb_complain_columns::id, 'type'=>'INT UNSIGNED PRIMARY KEY AUTO_INCREMENT'],
                ['name'=>tb_complain_columns::location_id, 'type'=> 'INT UNSIGNED'],
                ['name'=>tb_complain_columns::user_id,'type'=>'INT UNSIGNED'],
                ['name'=>tb_complain_columns::title, 'type'=>'VARCHAR('. MAX_TITLE_LENGTH .')'],
                ['name'=>tb_complain_columns::description, 'type'=>'VARCHAR('. MAX_DESCRIPTION_LENGTH .')'],
                ['name'=>tb_complain_columns::intensity_level, 'type'=>'DECIMAL(3,2) DEFAULT 0'],
                ['name'=>tb_complain_columns::image_name, 'type'=>'CHAR(18)'],
                ['name'=>tb_complain_columns::like_count, 'type'=>'INT UNSIGNED DEFAULT 0'],
                ['name'=>tb_complain_columns::dislike_count, 'type'=>'INT UNSIGNED DEFAULT 0'],
                ['name'=>tb_complain_columns::resolved, 'type'=>'BOOL DEFAULT 0'],
                ['name'=>tb_complain_columns::type, 'type'=>'INT UNSIGNED DEFAULT 1'],
                ['name'=>tb_complain_columns::priority_level, 'type'=>'INT UNSIGNED DEFAULT 0'],
                ['name'=>tb_complain_columns::created_at, 'type'=>'DATETIME DEFAULT ' . SQL_get_current_time()],
                ['name'=>tb_complain_columns::etag, 'type'=>'CHAR(32)'],
                ['name'=>tb_complain_columns::location_reference, 'type'=>'VARCHAR('. MAX_LOCATION_REFERENCE_LENGTH .')']
            ],
            ForeignKey_reference_wrapper([
                [   
                    'table_origin'=>All_tables::complain,
                    'column_origin'=>tb_complain_columns::location_id,
                    'table_ref'=>All_tables::location,
                    'column_ref'=>tb_location_columns::id,
                    'constraint'=>[
                        'args'=>[
                            [
                                'action'=>ForeignKey_constraint::on_delete,
                                'reference_option'=>ForeignKey_constraint::restrict
                            ]
                        ]
                    ]
                ],
                [
                    'table_origin'=>All_tables::complain,
                    'column_origin'=>tb_complain_columns::user_id,
                    'table_ref'=>All_tables::user,
                    'column_ref'=>tb_user_columns::id,
                    'constraint'=>[
                        'args'=>[
                            [
                                'action'=>ForeignKey_constraint::on_delete,
                                'reference_option'=>ForeignKey_constraint::cascade
                            ]
                        ]
                    ]
                ],
            ], ($complain_foreignKey_bind = []))
        ) 
      
    ,
    [...$complain_foreignKey_bind],
    "v0.1.3", db_created:false)
];



usort($all_schema, function($a, $b){
    if(!$a instanceof Schema || !$b instanceof Schema){
        Handle_error(
            new Error_code(Error_domain::configuration,Resource_code::invalidArgument,Error_condition::type),
            Level::Critical,
            "When trying to execute schema, one of element (array)\$all_schema isn't class Schema"
        );
    }
    return $a->version <=> $b->version;
});

foreach($all_schema as $schema) if(!$schema->execute()) break;