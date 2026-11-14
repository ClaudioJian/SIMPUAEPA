<?php
namespace ACEX_project\WEB\Private\Core\Security\File_upload; 
    define("FILE_REWRITE",true);

    use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;

    require_once __DIR__ . "/File_upload_utils.php";

    require_once __DIR__ . "/../../File_storation_map.php";

    require_once __DIR__ . "/../../../Core/AppCommonVar.php";

    use \GDImage;

    if(\php_sapi_name() !== "cli") {
        \http_response_code(403);
        exit();
    }


    if(!\extension_loaded("gd")) exit(1);
    $argv;
    if($argc !== 2) exit(2);

    $json_data = \json_decode(\base64_decode($argv[1]),true);
    if(!\is_array($json_data)) exit(2);

    $requested_file = $json_data['fname'] ?? exit(2);
    $final_path = $json_data['final_dest'] ?? exit(2);
    $extension = $json_data['extension'] ?? exit(2);
    $options = $json_data['options'] ?? [];

    $basename = Strip_all_extension(\basename($requested_file));
    $storation_path = \realpath(File_storation_path::Await_sanitation->value);
    

    if($storation_path ===false || !\is_dir($storation_path)) exit(3);

    $file = $storation_path .DIRECTORY_SEPARATOR. $basename . '.' .$extension;
    if(!\file_exists($file) ||!\is_file($file)) exit(4);
    \clearstatcache(true,$file);

    Rewrite_file($file,$extension,$final_path,$options);

    exit(0);

    /**
     * Rewrite file, compress into webep and redimension
     * @param string $from target to rewrite
     * @param string $extension
     * @param string $final_path final location will be moved after ai validation, not moved in this function
     * @return void
     */
    function Rewrite_file(string $from,string $extension,string $final_path,array $options){
        $target_path = File_storation_path::Await_validation;

        //start rewrite
        $ext = Avaible_extension::Convert($extension);

        $im = Create_gdObj_from_ext($ext->name,$from);
        if($im === false) exit(6);

        $resolution_str = match($final_path) {
            File_storation_path::Maps->value => IMG_MAP_FINAL_RESOLUTION,
            File_storation_path::profiles->value => IMG_PROFILE_FINAL_RESOLUTION,
            default => exit(2)
        };
        $resolution_str = \strtolower(trim($resolution_str));
        $resolution = \explode("x",$resolution_str);

        if(\count($resolution) !== 2 ||
            !\ctype_digit($resolution[0]) ||
            !\ctype_digit($resolution[1])
        ) exit(6);

        $width = (int)$resolution[0];
        $height = (int)$resolution[1];
        $crop_option = [];

        if($final_path === File_storation_path::profiles->value) {
            $width = $height = \min($width,$height);
            if($options !== []) $crop_option = $options['crop_img'] ?? [];
        }


        if(!Resize($im,$width,$height,$crop_option)) exit(6);

        $compress_ext = IMG_COMPRESS ? Avaible_extension::webp->name : $extension;

        $full_name = "$target_path->value/".Strip_all_extension(\basename($from)) . "." .$compress_ext;
        $success = save_rewrited_file($im,$full_name,$extension);

        if(!$success) exit(6);
        if(\is_file($from)) \unlink($from);
    }

    function save_rewrited_file(GDImage $im,string $from,string $extension) : bool{
        if(!\defined("IMG_COMPRESS") || IMG_COMPRESS === false){
            return match($extension){
                "png"=>imagepng($im,$from,min(9,round(IMG_FINAL_QUALITY/10))),
                "webp"=>imagewebp($im,$from,IMG_FINAL_QUALITY),
                "jpg"=>imagejpeg($im,$from,IMG_FINAL_QUALITY),
                default => exit(6)
            };
        }
        $img_compress = IMG_COMPRESS;
        return imagewebp($im,$from,IMG_FINAL_QUALITY);
    }


    /**
     * Summary of ACEX_project\WEB\Private\Core\Security\File_upload\Resize
     * @param int $width final width will be image to fit
     * @param int $height final height will be image to fit
     * @param array{
     *      start_x : int,
     *      start_y : int,
     *      crop_width : int,
     *      crop_height : int
     * } $crop_option
     * @return bool
     */
    function Resize(GDImage &$im,int $width, int $height, array $crop_option = []) : bool{
        $src_width = \imagesx($im);
        $src_height = \imagesy($im);
        if($src_width === false || $src_height === false || $src_height <=0 || $src_width <=0) return false;
        if($width <=0 || $height <= 0) exit(2);


        $dest_width = $dest_height = $ratio = 1;
        $crop_width = $src_width;
        $crop_height = $src_height;

        $canva_width = $width;
        $canva_height = $height;

        $x = $y = $src_x = $src_y = 0;
        

        
        if($crop_option !== []){
            $src_x = \max(0,$crop_option['start_x'] ?? 0);
            $src_y = \max(0,$crop_option['start_y'] ?? 0);

            $crop_width = $crop_option['crop_width'] ?? $src_width;
            $crop_height = $crop_option['crop_height'] ?? $src_height;

            //limit crop to original image
            $crop_width = \min($src_width,$crop_width);
            $crop_height = \min($src_height,$crop_height);
            if($crop_height <= 0 || $crop_height <= 0) return false;

            //if final x/y(right/bottom) is too much, move to left/top 
            $src_x = \min($src_x,$src_width - $crop_width);
            $src_y = \min($src_y,$src_height - $crop_height);


            //copy need to have scaled to final size
            $ratio = \max($crop_width/$width , $crop_height/$height);
            $dest_width = \max(1, (int) floor($crop_width / $ratio));
            $dest_height = \max(1, (int) floor($crop_height / $ratio));

            //move to middle in final image
            $x = (int)\floor(($width - $dest_width)/2);
            $y = (int)\floor(($height - $dest_height)/2);
        }else{
            $ratio = \min($width/$src_width , $height/$src_height);
            $dest_width = \max(1,(int)floor($src_width * $ratio));
            $dest_height = \max(1,(int)floor($src_height * $ratio));

            //alredy small, no need to resize
            if($ratio <= 0) return true;
            $canva_width = $dest_width;
            $canva_height = $dest_height;
        }

        $new_img = \imagecreatetruecolor($canva_width, $canva_height);
        if($new_img === false) return false;
        //copy alpha
        \imagealphablending($new_img, false);
        //preserve transparence for webep, png, jpeg
        \imagesavealpha($new_img, true);
        $color = \imagecolorallocatealpha($new_img,0,0,0,127);

        if($color !== false) \imagefill($new_img,0,0, $color);

        \imagecopyresampled($new_img,$im,
            $x,$y,
            $src_x,$src_y,
            $dest_width,$dest_height,
            $crop_width,$crop_height
        );
        //don't abandon alpha information in an image
        \imagesavealpha($new_img, true);

        
        $im = $new_img;
        return true;
    }

    function Create_gdObj_from_ext(string $ext,string $fpath) : GDImage|bool{
        return match($ext){
            "png"=>\imagecreatefrompng($fpath),
            "webp"=>\imagecreatefromwebp($fpath),
            "jpg"=>\imagecreatefromjpeg($fpath),
            default => exit(6)
        };
    }
