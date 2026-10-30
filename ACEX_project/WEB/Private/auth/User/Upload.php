<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for user's Prefered_Setting                                                    |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Auth\User;

    require_once __DIR__ . "/../../Core/Security/Request_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Get_request_body;

    require_once __DIR__ ."/../../Core/File_storation_map.php";

    require_once __DIR__ . "/../../Core/Security/File_upload/File_upload.php";
    use function ACEX_project\WEB\Private\Core\Security\File_upload\Secure_file_upload;

    require_once __DIR__ . "/../../Core/MIME_type.php";
    use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;

    function Upload_profile(){
        $request_body = Get_request_body();
        $start_x = $request_body["x"] ?? 0;
        $start_x = (int)$start_x;

        $start_y = $request_body["y"] ?? 0;
        $start_y = (int)$request_body["y"];


        $crop_width = $request_body["width"] ?? null;
        $crop_height = $request_body["height"] ?? null;

        $crop_option = [
            "start_x"=>$start_x,
            "start_y"=>$start_y
        ];

        if($crop_width !== null && (int)$crop_width>0) $crop_option['crop_width'] = (int)$crop_width;
        if($crop_height !== null && (int)$crop_height>0) $crop_option['crop_height'] = (int)$crop_height;
        
        Secure_file_upload(File_storation_path::profiles,
            [Avaible_extension::jpg,Avaible_extension::png,Avaible_extension::webp],
            ["crop_img"=>$crop_option]
        );    
    }

    function Upload_map_img(){
        Secure_file_upload(File_storation_path::Maps,[Avaible_extension::jpg,Avaible_extension::png,Avaible_extension::webp]);
    }

