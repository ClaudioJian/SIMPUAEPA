<?php

namespace ACEX_project\WEB\Private\Core\Render\html;

require_once __DIR__ . "/../../File_storation_map.php";
require_once __DIR__ . "/../../Security/Rate_limit_handler.php";


require_once __DIR__ . "/../../../Api/database/database_dictionary.php";
require_once __DIR__ . "/../../../Api/database/database_manager.php";

require_once __DIR__ . "/../../../Error/Error_code.php";
require_once __DIR__ . "/../../../Error/Error_manager.php";

require_once __DIR__ . "/../../MIME_type.php";
use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;
use Throwable;

use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Get_img_db_location;
use function ACEX_project\WEB\Private\Auth\session_initialize;
use function ACEX_project\WEB\Private\Auth\User\Is_logged;
use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
use function ACEX_project\WEB\Private\Core\Security\File_upload\Get_old_file_name;
use function ACEX_project\WEB\Private\Core\Send_mime_type;
use function ACEX_project\WEB\Private\Error\Log_internal;

/**
 * @param array $captured_args argument captured in router_class.php
 */
function Send_img(?Rate_limiter $ip_limiter, array $captured_args){
    if(\count($captured_args) !== 2) {
        \http_response_code(404);
        exit();
    }

    $category = strtolower(trim($captured_args[0]));
    $file_name = trim($captured_args[1]);

    if(!is_string($file_name) || $file_name === '') {
        \http_response_code(404);
        exit();
    }

    $extension = \pathinfo($file_name,PATHINFO_EXTENSION);
    $extension = Avaible_extension::Convert($extension);
    $allowed_extension = [Avaible_extension::png, Avaible_extension::webp, Avaible_extension::jpg];

    if(!in_array($extension,$allowed_extension,true)){
        \http_response_code(404);
        exit();
    }

    $mime = Find_MIME_from_extension($extension);
    header("content-type: $mime");

    $target_dir = match($category){
        'profile' => File_storation_path::profiles,
        'maps' => File_storation_path::Maps,
        default => null
    };

    if($target_dir === null) {
        \http_response_code(404);
        exit();
    }

    Is_img_acess_authorized($target_dir, $file_name);

    $full_path = $target_dir->value . "/{$file_name}";

    $fptr = fopen($full_path,'rb');
    if(!is_resource($fptr) || ($content = stream_get_contents($fptr)) === false) {
        \http_response_code(404);
        exit();
    }

    fclose($fptr);

    echo $content;
}

/**
 * exit if not allowed
 */
function Is_img_acess_authorized(File_storation_path $category, string $file_name) :void{
    $db_info = Get_img_db_location($category);

    $is_public = $db_info['public'];
    if($is_public === true) return;

    if($is_public === false){
        $table = $db_info['table'];
        $img_column = $db_info['column'];
        $uid_column = $db_info['user_id'];

        try{
            $status = \session_status();

            if($status===PHP_SESSION_DISABLED){
                Log_internal(
                    new Error_code(Error_domain::configuration, Resource_code::session, Error_condition::unsupported),
                    Level::Error,
                    "Failed to check permission of user: Session is not allowed in current php setting"
                );

                \http_response_code(500);
                exit();
            }

            if($status === PHP_SESSION_NONE) session_initialize();

            if(!Is_logged() || 
                is_int(Get_old_file_name($table, $img_column, $uid_column,[
                        'column'=>$img_column,
                        'value'=>$file_name
                    ]
                ))
            ){
                \http_response_code(401);
                echo json_encode(['success'=>-2]);
                exit();
            }

        }catch(Throwable $e){
            $conn = Connect_database(true);
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
            $uid = $_SESSION['uid'] ?? 'unknown';
            Log_internal(
                new Error_code(Error_domain::database, Resource_code::file, Error_condition::unknown),
                Level::Error,
                "Unexpected error when trying check permission to file \"$file_name\" of current user [uid - $uid] | {$e->getMessage()}"
            );

            \http_response_code(500);
            exit();
        }finally{
            $conn = Connect_database(true);
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->commit();
        }
    }
}