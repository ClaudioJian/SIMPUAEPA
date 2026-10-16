<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security\File_upload; 
    require_once __DIR__ . "/File_upload_utils.php";
    require_once __DIR__ . "/../../File_storation_map.php";

    use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;

    require_once __DIR__ . "/../../AppCommonVar.php";

    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Monolog\Level;

    require_once __DIR__ . "/../../MIME_type.php";

    use function ACEX_project\WEB\Private\Core\Construct_MIME;
    use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
    use function ACEX_project\WEB\Private\Error\Handle_error;
    use function ACEX_project\WEB\Private\Error\Log_internal;

    require_once __DIR__ . "/File_upload_utils.php";
    require_once __DIR__ . "/../../../Core/AppCommonVar.php";



    /**
     * Protocol:
     * When file is received, then client receive:
     * json(
     *      success: 0,
     *      stage: 0,
     *      file: filename,
     *      uid : user id in current session
     * ) 
     * In Middle of validation, in any point can return:
     * json(
     *      success: 0,
     *      stage : 1 | 2,
     *      file: filename,
     *      uid : user id in current session 
     * )
     * or when failed:
     * json(
     *      success: -1,
     *      stage: 1 | 2,
     *      code: UPLOAD_ERR_* from native php or FrontEnd_upload_err from enum in File_upload_utils.php,
     *      file: filename,
     *      uid : user id in current session
     * )
     * if failed, the error will be logged, the possible result can be find in File_upload_utils.php from Get_descriptive_ERRupload()
     * 
     * stage mean stage of validation. 1 mean in validation and 2 mean in ai validation
     * For front end, success -1 then stage 1 mean the validation failed else if success is 0, then mean the validation success and is moving to next stage
     * For exemple stage 2 success 0 mean the operation is finished else -1 mean ai validation didn't pass
     * 
     * @param array $options array contain extra information about file. for example, crop image from (x,y)
     * @return bool sucess or no for file upload, the user of this funtion should handle error response(all error ids logged automatcally once have error)
     */
    function Secure_file_upload(File_storation_path $target_location, array $allowed_ext,array $options = []) : bool{
        //start buffering the output
        //send to client that is sucessfully received
        \ob_start();
        \header('Content-type: '.Construct_MIME(MIME['application']['json']));
        echo json_encode(['success'=>0,'stage'=>0]);
        \ob_flush();
        
        $finfo_txt = Get_file_log_format();
        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[Type - $target_location->name]Upload file $finfo_txt");

        $og_name = \basename(\mb_strtolower(\trim($_FILES[FILE_UPLOAD_FIELD_NAME]['name'])));

        Validate_uploaded_file($target_location,$allowed_ext,$og_name);

        
        
        $extension = Avaible_extension::Convert(\pathinfo($og_name,PATHINFO_EXTENSION));
        $new_name = \uniqid();
        $full_name = Safe_file_store(File_storation_path::Await_sanitation,$extension,$new_name);
        

        if($full_name === false){
            Exit_upload_err(Custom_Upload_Err::cannot_move_file,1,$allowed_ext,$og_name,'',$new_name);
            return false;
        }


  
        $php_exe = PHP_PATH . "/" . "php.exe ";
        $flags = "-d extension=php_gd.dll ";
        $open = __DIR__ . "/File_rewriter.php ";

        $data_arr = [
                'fname'=>$new_name,
                'extension'=>$extension->name,
                'final_dest'=>$target_location->value
            ];
        if($options !== []) $data_arr['options'] = $options;
        $datas = \json_encode($data_arr);
        
        
        $argvs = \escapeshellarg(base64_encode($datas));

        $command = "$php_exe $flags $open $argvs";

        $internal_fname = $new_name. '.'.$extension->name;
        $finfo_txt = Get_file_log_format($internal_fname);
        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[Type - $target_location->name]Rewriting and compressing file $finfo_txt");
        \exec($command,$output, $return_code);

        switch($return_code){
            case 0:  break;
            case 1:  Exit_upload_err(Custom_Upload_Err::no_gd_support,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            case 2:  Exit_upload_err(Custom_Upload_Err::invalid_argument,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            case 3:  Exit_upload_err(Custom_Upload_Err::directory_not_exist,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            case 4:  Exit_upload_err(Custom_Upload_Err::cannot_find_requested_file,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            case 5:  Exit_upload_err(Custom_Upload_Err::cannot_move_file,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            case 6:  Exit_upload_err(Custom_Upload_Err::rewrite_failed,1,$allowed_ext,$og_name,$full_name,$internal_fname);
            default: Exit_upload_err(Custom_Upload_Err::cannot_start,1,$allowed_ext,$og_name,$full_name,$internal_fname); break;
        }




        Send_response(0,1,$og_name);

        //rewrite sucessfully, the file is now in .../Await_validation/! move to ai validation
        //$success = AI_validation(User_file_storation_path::Await_validation->value.$new_name);
        //if(!$success) ...

        //ai succesfully validate file! the file will be moved and compressed to webp
        $compressed_ext = IMG_COMPRESS ? Avaible_extension::webp : $extension;
        $moved_path = File_storation_path::Await_validation->value . "/$new_name.".$compressed_ext->name;

        $full_name = Safe_file_store($target_location,$compressed_ext,$new_name,$moved_path,true);


        if($full_name === false){
            Exit_upload_err(Custom_Upload_Err::cannot_move_file,1,$allowed_ext,$og_name,'',$new_name);
            return false;
        }

        Send_response(0,2,$og_name);
        \ob_end_clean();

        $internal_fname = $new_name. '.'.$compressed_ext->name;
        $finfo_txt = Get_file_log_format($internal_fname);

        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[Type - $target_location->name]Sucessfully store file $finfo_txt");
        return true;
    }


       /**
     * Validate if current uploaded file is valid or no by checking $_FILES
     * @param File_storation_path $file_type category of file will be upload(e.g profile image, map image)
     * @param bool $exit control whether exit or no when validation failed, the automatic header is send in this case
     * @param array $allowed_ext only last extension is checked, e.g: jpeg, png; the returned Accept-post may be innacuracy
     * @return int 0 for sucess, positive for php's UPLOAD_ERR_* and negative is custom error code
     */
    function Validate_uploaded_file(File_storation_path $file_type,array $allowed_ext, string $og_name,bool $exit = true) : int{
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            if($exit) Exit_upload_err(Custom_Upload_Err::invalid_upload->value,1,[],$og_name);
            return Custom_Upload_Err::invalid_upload->value;
        }

        if(!isset($_FILES[FILE_UPLOAD_FIELD_NAME])){
            if($exit) Exit_upload_err(UPLOAD_ERR_NO_FILE,1,[],$og_name);
            return UPLOAD_ERR_NO_FILE;
        }

        $err_code = $_FILES[FILE_UPLOAD_FIELD_NAME]['error'];
        if($err_code !== UPLOAD_ERR_OK) {
            if($exit) Exit_upload_err($err_code,1,$allowed_ext,$og_name);
            return $err_code;
        }

        //TODO: size check, may need in future if need support other type of extension that require diferent size
        if(!Validate_fsize($file_type)) {
            if($exit) Exit_upload_err(Custom_Upload_Err::size_too_big,1,$allowed_ext,$og_name);
            return Custom_Upload_Err::size_too_big->value;
        }

        $client_provided_mime = $_FILES[FILE_UPLOAD_FIELD_NAME]['type'];

        //fast mime check
        $allowed_mimes = [];
        foreach($allowed_ext as $ext){
            $allowed_mime = Find_MIME_from_extension($ext);
            if($allowed_mime!==null) $allowed_mimes[] = $allowed_mime;
        }

        if(!\in_array($client_provided_mime,$allowed_mimes,true)) {
            if($exit) Exit_upload_err(Custom_Upload_Err::invalid_mime,1,$allowed_ext,$og_name);
            return Custom_Upload_Err::invalid_mime->value;
        }


        //fast check(extension)
        $str_allowed_ext = \array_map(fn($item) => $item->name,$allowed_ext);

        $file_name = \mb_strtolower(trim($_FILES[FILE_UPLOAD_FIELD_NAME]['name']));
        $extension = \pathinfo($file_name,PATHINFO_EXTENSION);

        if(!\in_array($extension,$str_allowed_ext,true)){
            if($exit) Exit_upload_err(Custom_Upload_Err::invalid_mime,1,$allowed_ext,$og_name);
            
            return Custom_Upload_Err::invalid_mime->value;
        }

        //validate real extension by content
        $temp_file = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        if(!\is_uploaded_file($temp_file)) {
            if($exit) Exit_upload_err(Custom_Upload_Err::invalid_upload,1,$allowed_ext,$og_name);
            
            return Custom_Upload_Err::invalid_upload->value;
        }

        $finfo = \finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            if ($exit) Exit_upload_err(Custom_Upload_Err::unable_spot_file_type,1,$allowed_ext,$og_name);
            return Custom_Upload_Err::unable_spot_file_type->value;
        }

        $expected_mime = \finfo_file($finfo,$temp_file);
        if($expected_mime===false || 
            !\in_array($expected_mime,$allowed_mimes,true)
        ) {
            if($exit) Exit_upload_err(Custom_Upload_Err::invalid_extension,1,$allowed_ext,$og_name);
            return Custom_Upload_Err::invalid_extension->value;
        }
                
        return 0;
    }

    function Validate_fsize(File_storation_path $file_type) : bool {
        $byte_size = $_FILES[FILE_UPLOAD_FIELD_NAME]['size'];
        
        //type double
        $allowed_byte = match($file_type){
            File_storation_path::Maps => Parse_byte(IMG_MAP_MAX_SIZE),
            File_storation_path::profiles => Parse_byte(IMG_PROFILE_MAX_SIZE),
        };


        return $byte_size <= $allowed_byte;
    }

    function Exit_upload_err(Custom_Upload_Err|int $code, int $stage,array $allowed_ext,string $og_name,string $moved_fpath = "", string $internal_name = "") : never
    {
        $desc = Get_descriptive_ERRupload($code,$allowed_ext,$internal_name);
        $headers = $desc['headers'];
        $frontend_code = $desc['client_code'];
        $http_code = $desc['http_code'];
        $err_desc = $desc['description'];

        foreach($headers as $header){
            if(!\is_string($header)) {
                \http_response_code(500);
                Handle_error(
                    new Error_Code(Error_domain::server,Resource_code::invalidArgument,Error_condition::type),
                    Level::Critical,
                    'In Exit_upload_err([array]$headers): value isnt string'
                );
            }
            \header($header);
        }

        $code_args = $code instanceof Custom_Upload_Err ? $code->value : $code;

        Log_internal(
            new Error_code(Error_domain::request,Resource_code::file,Error_condition::failed),
            Level::Warning,
            "(code: $code_args) $err_desc"
        );
        if($moved_fpath !== "") \unlink($moved_fpath);
        
        
        Send_response(-1,$stage,$og_name,$http_code,$frontend_code);
        
        \ob_end_clean();
        exit();
    }

    /**
     * Send to client without exit.
     * @param FrontEnd_upload_err|null $code no pass to avoid sending, if `$success` is positive, will assume as null
     * @param int|null $http_code no specific to stop send http code
     */
    function Send_response(int $success,int $stage,string $file_name,?int $http_code = null,?FrontEnd_upload_err $code=null,string $desc = "") :void{
        $file_name = \trim(\basename($file_name));
        $uid = $_SESSION['uid'] ?? "unknown";
        $response = ['sucess'=>$success,'stage'=>$stage,'uid'=>$uid,'file'=>$file_name];

        if($desc !== '') $response['description'] = $desc;
        if($code !== null && $success < 0) $response['code'] = $code;


        if($http_code !== null) \http_response_code($http_code);

        echo json_encode($response,JSON_UNESCAPED_UNICODE) . "\n";
        if(\ob_get_level() > 0) \ob_flush();
        \flush();
    }