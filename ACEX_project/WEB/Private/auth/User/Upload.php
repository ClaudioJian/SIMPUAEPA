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

use ACEX_project\API\Input_ruleset;
use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\Data_type;
use ACEX_project\WEB\Private\Core\File_storation_path;

require_once __DIR__ . "/../../../../API/Field_processor.php";
require_once __DIR__ . "/../../Core/Data_type.php";

    function Profile_upload_ruleset() : array{
        return [
            [
                'key'=>'x',
                'instruction' => new Input_ruleset(Data_type::int, false, 0)
            ],
            [
                'key'=>'y',
                'instruction' => new Input_ruleset(Data_type::int, false, 0)
            ],
            [
                'key'=>'width',
                'instruction' => new Input_ruleset(Data_type::int, false)
            ],
            [
                'key'=>'height',
                'instruction' => new Input_ruleset(Data_type::int, false)
            ],
        ];
    }

    function Upload_profile(mixed $__, array $captured, array $request_body){
        $start_x = $request_body["x"];
        $start_y = $request_body["y"];

        $crop_width = $request_body["width"];
        $crop_height = $request_body["height"];

        $crop_option = [
            "start_x"=>$start_x,
            "start_y"=>$start_y
        ];

        if($crop_width !== null && $crop_width>0) $crop_option['crop_width'] = $crop_width;
        if($crop_height !== null && $crop_height>0) $crop_option['crop_height'] = $crop_height;
        
        Secure_file_upload(File_storation_path::profiles,
            [Avaible_extension::jpg,Avaible_extension::png,Avaible_extension::webp],
            ["crop_img"=>$crop_option]
        );    
    }
