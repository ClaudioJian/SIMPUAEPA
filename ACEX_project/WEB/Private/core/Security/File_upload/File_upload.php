<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security\File_upload; 
    require_once __DIR__ . "/Child_process_handler.php";

    require_once __DIR__ . "/File_upload_utils.php";

    use ACEX_project\WEB\Private\Core\Avaible_extension;

    require_once __DIR__ . "/../../AppCommonVar.php";

    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;

    require_once __DIR__ . "/../../MIME_type.php";

    use function ACEX_project\WEB\Private\Core\Construct_MIME;
use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
use function ACEX_project\WEB\Private\Error\Handle_error;
    use function ACEX_project\WEB\Private\Error\Log_internal;

        require_once __DIR__ . "/File_upload_utils.php";

    require_once __DIR__ . "/../../../Core/AppCommonVar.php";

    use RuntimeException;

    /**
     * Protocol:
     * When call this function, the output is send gradually while validation.
     * 1. send to client received file with json(['success'=>0,'code'=>0])
     * 2. send to client validation is sucessfully with json(['success'=>0,'code'=>1])
     * 3. end buffering and send when ai validation pass with json(['success'=>0,'code'=>2])
     * Alongside the original file name and user id who sending this request
     * Otherwise if fail, always send success as -1 with additional codes(defined in enum FrontEnd_upload_err).
     * 
     * This function will open child process File_validator.php with second argv as file's name(using internal generated name);
     * If have some error, then child process will exit immedially and return with:
     * json(
     *      success: -1
     *      stage: 1 to 3
     *      code: FrontEnd_upload_err or UPLOAD_ERR_*
     * )
     * else it will return from child process, also this will be send to front end as well:
     * json(
     *      success: 0 or 1
     *      stage: 1 to 4
     * )
     * the data is seem as finished once new line is send.
     * when success is set to 0, mean the current stage is finished and need to go to next stage
     * else if is 1, mean is requesting from parent to provide extra info. The extra info dependent from stage:
     *      success 1 stage 1 - want allowed extension as json
     * Stage is status for validation, where: 1 is in validation; 2 is in rewrite and 3 is in ai validation and 4 for end of validation(-1 for front end after stage 1)
     * 
     * 
     * @return bool sucess or no for file upload, the user of this funtion should handle error response(all error ids logged automatcally once have error)
     */
    function Secure_file_upload(User_file_storation_path $target_location, array $allowed_ext){
        Validate_uploaded_file($allowed_ext);

        $og_name = basename(mb_strtolower(trim($_FILES[FILE_UPLOAD_FIELD_NAME]['name'])));
        
        $extension = Avaible_extension::Convert(pathinfo($og_name,PATHINFO_EXTENSION));
        $new_name = uniqid();
        $full_name = Safe_file_store(User_file_storation_path::Await_sanitation,$extension,$new_name);
        

        if($full_name === false){
            Log_internal(
                new Error_code(Error_domain::request,Resource_code::file,Error_condition::unknown),
                Log_level::warning,
                "File upload failed in Secure_file_upload(): the file isn't valid or cannot be moved! Please check if directory ["
                . User_file_storation_path::Await_sanitation->value . "] exist and have correct permission set."
            );
            return false;
        }

        //start buffering the output
        

        //send to client that is sucessfully received
        header('Content-type: '.Construct_MIME(MIME['application']['json']));
        echo json_encode(['success'=>0,'code'=>0]);
        \ob_flush();
        \ob_start();

  
        $command = PHP_PATH . "/" . "php.exe";
        $open = __DIR__ . "/File_validator.php";
        
        /*
        $cwd = __DIR__; //initial working dir for command, must be absolute, null for current
        $env = null; //null mean use same as current
        */

        ob_end_clean();
    }


       /**
     * Validate if current uploaded file is valid or no by checking $_FILES
     * @param bool $exit control whether exit or no when validation failed, the automatic header is send in this case
     * @param array $allowed_ext only last extension is checked, e.g: jpeg, png; the returned Accept-post may be innacuracy
     * @return int 0 for sucess, positive for php's UPLOAD_ERR_* and negative is custom error code
     */
    function Validate_uploaded_file(array $allowed_ext, bool $exit = true) : int{
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            if($exit) Exit_upload_err(405,Custom_Upload_Err::invalid_upload->value,[],"File upload is only avaible via POST. Currently don't support PUT method.");
            return Custom_Upload_Err::invalid_upload->value;
        }

        if(!isset($_FILES[FILE_UPLOAD_FIELD_NAME])){
            if($exit) Exit_upload_err(400,UPLOAD_ERR_NO_FILE,[],"No file is uploaded.");
            return UPLOAD_ERR_NO_FILE;
        }

        $err_code = $_FILES[FILE_UPLOAD_FIELD_NAME]['error'];
        if($err_code !== UPLOAD_ERR_OK) {
            $err_reason = Get_descriptive_ERRupload($err_code);
            if($exit) Exit_upload_err($err_reason['http_code'], $err_code,
                                    $err_reason['headers'],
                                    $err_reason['description']
                                );
            return $err_code;
        }

        //TODO: size check, may need in future if need support other type of extension that require diferent size
        /*if($_FILES['userfile']['size']...) {
            ...
        }*/

        $client_provided_mime = $_FILES[FILE_UPLOAD_FIELD_NAME]['type'];

        //fast mime check
        $allowed_mimes = [];
        foreach($allowed_ext as $ext){
            $allowed_mime = Find_MIME_from_extension($ext);
            if($allowed_mime!==null) $allowed_mimes[] = $allowed_mime;
            if($client_provided_mime !== $allowed_mime) {
                if($exit) {
                Exit_upload_err(415,Custom_Upload_Err::invalid_mime->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes)],
                "Invalid mime type provided by browser or client");
            }
            return Custom_Upload_Err::invalid_mime->value;
            }
        }


        //fast check(extension)
        $allowed_ext = array_map(fn($item) => mb_strtolower(trim($item)),$allowed_ext);

        $file_name = mb_strtolower(trim($_FILES[FILE_UPLOAD_FIELD_NAME]['name']));
        $extension = pathinfo($file_name,PATHINFO_EXTENSION);

        if(!in_array($extension,$allowed_ext,true)){
            if($exit) {
                Exit_upload_err(415,
                Custom_Upload_Err::invalid_mime->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes)],
                "Invalid mime type provided by browser or client2");
            }
            return Custom_Upload_Err::invalid_mime->value;
        }

        //validate real extension by content
        $temp_file = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        if(!is_uploaded_file($temp_file)) {
            if($exit) {
                Exit_upload_err(400,Custom_Upload_Err::invalid_upload->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes)],
                "The uploaded file is invalid or was not received through a valid HTTP upload.");
            }
            return Custom_Upload_Err::invalid_upload->value;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            if ($exit) {
                Exit_upload_err(
                    500,
                    Custom_Upload_Err::unable_spot_file_type->value,
                    [],
                    "Unable to determine the uploaded file type."
                );
            }
            return Custom_Upload_Err::unable_spot_file_type->value;
        }

        $expected_mime = finfo_file($finfo,$temp_file);
        header('x-expected-mime: '.$expected_mime);
        if($expected_mime===false || 
            !\in_array($expected_mime,$allowed_mimes,true)
        ) {
            if($exit) {
                Exit_upload_err(415,Custom_Upload_Err::invalid_extension->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes)],
                "Invalid extension");
            }
            return Custom_Upload_Err::invalid_extension->value;
        }
                
        return 0;
    }

    function Exit_upload_err(int $http_code, int $code, string $err_description,array $allowed_ext)
    {
        $desc = Get_descriptive_ERRupload($code,$allowed_ext);
        $headers = $desc['headers'];
        foreach($headers as $header){
            if(!\is_string($header)) {
                http_response_code(500);
                Handle_error(
                    new Error_Code(Error_domain::server,Resource_code::syntax,Error_condition::invalid),
                    Log_level::fatal,
                    'Invalid argument [array]$headers in Exit_upload_err(): '
                    . 'value isnt string'
                );
            }
            header($header);
        }

        
        http_response_code($http_code);

        $response = ['sucess'=>$code];
        if($err_description!=='') $response['description'] = $err_description;
        echo json_encode($response);
        exit();
    }

    function Invalid_MIME(bool $exit,array $allowed_mimes){
        if($exit) {
            Exit_upload_err(415,
                Custom_Upload_Err::invalid_mime->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes)],
                "Invalid mime type provided by browser or client");
        }
        return Custom_Upload_Err::invalid_mime->value;
    }
?>