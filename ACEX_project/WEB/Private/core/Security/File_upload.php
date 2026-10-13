<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security;

use Exception;
use Throwable;
    require_once __DIR__ . "/../AppCommonVar.php";

    require_once __DIR__ . "/../../Error/Error_code.php";
    require_once __DIR__ . "/../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Log_level;
use ACEX_project\WEB\Private\Error\Resource_code;


    require_once __DIR__ . "/../MIME_type.php";

use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
use function ACEX_project\WEB\Private\Core\Validate_MIME;
    use function ACEX_project\WEB\Private\Error\Handle_error;


    enum Custom_Upload_Err:int{
        case invalid_mime = -1;
        case method_not_allowed = -2;
        case unable_spot_file_type = -3;
        case invalid_upload = -4;
        case invalid_extension = -5;
    }

    /** values is relative to current folder, which is ...Core/Security */
    enum User_file_storation_path: string{
        case profiles = "/../../../User_images/Profiles";
        case Maps = "/../../../User_images/Maps";
    }

    function Secure_file_upload(User_file_storation_path $target_location, array $allowed_ext, array $allowed_mime = []){
        
    }

    /**
     * Move the current uploaded file from temp location to correct location. *another server is not used even thought is better.
     */
    function Safe_file_store(User_file_storation_path $target_location){

    }

    //rewrite the uploaded file if possible, also rename the file name to random name. This function try to CDR.
    function File_rewrite(){

    }


    function Exit_upload_err(int $http_code, int $code, array $headers, string $err_description)
    {
        foreach($headers as $header){
            if(!is_string($header)) {
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

    /**
     * @return array{
     *      http_code: int,
     *      description: string,
     *      headers: array
     * } | null if dont match any or have no error
     */
    function Get_descriptive_ERRupload(int $php_err_code){
        $http_code = 200;
        $err_description = "";
        $headers = [];
        if($php_err_code===UPLOAD_ERR_INI_SIZE) return null;

        switch($php_err_code){
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $http_code =  413;
                $err_description = "The uploaded file size is too big";
                break;
            case UPLOAD_ERR_PARTIAL:
                $http_code =  400;
                $err_description = "The uploaded file is only uploaded partially";
                break;
            case UPLOAD_ERR_NO_FILE:
                $http_code =  400;
                $err_description = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $http_code =  500;
                $err_description = "Php configuration error: No location to store temporary file before process request. Please contact with admin to solve this problem";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $http_code =  500;
                $err_description = "Failed to write file. Can be permission problem or other unkown issues. Please contact with admin to solve this problem";
                break;
            case UPLOAD_ERR_EXTENSION:
                $http_code =  415;
                $err_description = "Php failed to process this file because extension, Please provide valid extension";
                break;
            default:
                return null;
        }
        return ['http_code'=>$http_code,'description'=>$err_description,'headers'=>$headers];
    }
    

    /**
     * Validate if current uploaded file is valid or no by checking $_FILES
     * @param bool $exit control whether exit or no when validation failed, the automatic header is send in this case
     * @param array $allowed_ext only last extension is checked, e.g: jpeg, png; the returned Accept-post may be innacuracy
     * @return int 0 for sucess, positive for php's UPLOAD_ERR_* and negative is custom error code
     */
    function Validate_uploaded_file(array $allowed_ext, bool $exit = true) : int{
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            if($exit) Exit_upload_err(405,Custom_Upload_Err::method_not_allowed->value,[],"File upload is only avaible via POST. Currently don't support PUT method.");
            return Custom_Upload_Err::method_not_allowed->value;
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
            !in_array($expected_mime,$allowed_mimes,true)
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
?>