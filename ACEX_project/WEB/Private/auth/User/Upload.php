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

    require_once __DIR__ . "/../../Core/Security/File_upload/File_upload_utils.php";
    use ACEX_project\WEB\Private\Core\Security\File_upload\File_storation_path;

    require_once __DIR__ . "/../../Core/Security/File_upload/File_upload.php";
    use function ACEX_project\WEB\Private\Core\Security\File_upload\Secure_file_upload;

    require_once __DIR__ . "/../../Core/MIME_type.php";
    use ACEX_project\WEB\Private\Core\Avaible_extension;

    function Upload_profile(){
        $request_body = Get_request_body();
        $start_x = (int)$request_body["x"];
        $start_y = (int)$request_body["y"];
        $crop_width =  (int)$request_body["width"];
        $crop_height =  (int)$request_body["height"];
        
        Secure_file_upload(File_storation_path::profiles,
            [Avaible_extension::jpg,Avaible_extension::png,Avaible_extension::webp],
            ["crop_img"=>[
                    "start_x"=>$start_x,
                    "start_y"=>$start_y,
                    "crop_width"=>$crop_width,
                    "crop_height"=>$crop_height,
                ]
            ]
        );    
    }

    function Upload_map_img(){
        Secure_file_upload(File_storation_path::Maps,[Avaible_extension::jpg,Avaible_extension::png,Avaible_extension::webp]);
    }

