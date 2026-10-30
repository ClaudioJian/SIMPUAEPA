<?php

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;


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
                ['name'=>tb_user_columns::name,'type'=>'VARCHAR(255)'],
                ['name'=>tb_user_columns::password, 'type'=>'VARCHAR(255)'],
                ['name'=>tb_user_columns::profile_image_name, 'type'=>'CHAR(18)'],
                ['name'=>tb_user_columns::deleted, 'type'=>'BOOL'],
                ['name'=>tb_user_columns::theme, 'type'=>'BOOL'],
                ['name'=>tb_user_columns::credibility_score, 'type'=>'CHAR(18)'],
                ['name'=>tb_user_columns::access_stricted, 'type'=>'BOOL'],
            ],
            'UNIQUE('.tb_user_columns::email->name.','. tb_user_columns::name->name.')'
        ). "\n\n".
    Create_TABLE_SQL_Wrapper(All_tables::complain, [
                ['name'=>tb_complain_columns::id, 'type'=>'INT UNSIGNED PRIMARY KEY AUTO_INCREMENT'],
                ['name'=>tb_complain_columns::location_id, 'type'=> 'INT UNSIGNED FOREIGN KEY ON DELETE CASCADE'],
                ['name'=>tb_complain_columns::user_id,'type'=>'INT UNSIGNED FOREIGN KEY ON DELETE CASCADE'],
                ['name'=>tb_complain_columns::title, 'type'=>'VARCHAR(60)'],
                ['name'=>tb_complain_columns::description, 'type'=>'VARCHAR(500)'],
                ['name'=>tb_complain_columns::intensity_level, 'type'=>'DIGIT(3,2)'],
                ['name'=>tb_complain_columns::image_name, 'type'=>'CHAR(18)'],
                ['name'=>tb_complain_columns::like_count, 'type'=>'INT UNSIGNED DEFAULT 0'],
                ['name'=>tb_complain_columns::dislike_count, 'type'=>'INT UNSIGNED DEFAULT 0'],
                ['name'=>tb_complain_columns::resolved, 'type'=>'BOOL'],
                ['name'=>tb_complain_columns::type, 'type'=>'INT UNSIGNED DEFAULT 1'],
                ['name'=>tb_complain_columns::priority_level, 'type'=>'INT UNSIGNED'],
                ['name'=>tb_complain_columns::created_at, 'type'=>'DATETIME'],
                ['name'=>tb_complain_columns::etag, 'type'=>'CHAR(32)'],
                ['name'=>tb_complain_columns::location_reference, 'type'=>'VARCHAR(60)']
            ]
        )    
        
    ,
    [],
    "v0.1.3")
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