<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security\File_upload; 

    require_once __DIR__ . "/../../MIME_type.php";
    use ACEX_project\WEB\Private\Core\Avaible_extension;
    use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;

    enum Custom_Upload_Err:int{
        case invalid_mime = -1;
        case unable_spot_file_type = -2;
        case invalid_upload = -3;
        case invalid_extension = -4;
        case cannot_start = -5;
        case too_many_request = -6;
        case timeout = -7;
        case no_gd_support = -8;
    }

    /** -1: when the extension, mime isn't allowed or when php fail to find mime type from content
     * -2: when the method is not http POST or is invalid
     * -3: when file validation cannot start, e.g start child process, missing php_gd.dll
     * -4: the validation is too busy to process
     * -5: the validation is using too many time
    */ 
    enum FrontEnd_upload_err:int{
        case invalid_file_type = -1;
        case invalid_upload = -2;
        case cannot_start = -3;
        case too_many_request = -4;
        case timeout = -5;
        case validation_failed = -6;
        case unexpected = -99;
    }

    /** values is relative to current folder, which is ...Core/Security */
    enum User_file_storation_path: string{
        case profiles = __DIR__ . "/../../../Uploads/images/Profiles";
        case Maps = __DIR__ . "/../../../Uploads/images/Maps";
        case Await_sanitation = __DIR__ . "/../../../Uploads/tmp/Awaiting_sanitation";
        case Await_validation = __DIR__ . "/../../../Uploads/tmp/Awaiting_validation";
    }

    /**
     * Move the current uploaded file from temp location to correct location, modify the name by param $name or by unique id.
     * If name is longer than 250 char or is empty
     * @return bool|string full path name($to/$name.ext) when sucess else false
     */
    function Safe_file_store(User_file_storation_path $to,Avaible_extension $ext,string $name,string $from = "") : bool|string{
        if($from === '') $from = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        $name = trim(basename($name));
        if(empty($name) || strlen($name)>=250) $name = uniqid();
        $dest = $to->value . "/".$name.".".$ext->name;

        $sucess = move_uploaded_file($from,$dest);

        return $sucess===false ? false:$dest;
    }

        /**
     * @return array{
     *      http_code: int,
     *      description: string,
     *      headers: array,
     *      client_code: int
     * } | null if dont match any or have no error
     */
    function Get_descriptive_ERRupload(int $php_err_code, array $accept_ext = []){
        //fast mime check
        $allowed_mimes = [];
        foreach($accept_ext as $ext){
            if(!$ext instanceof Avaible_extension) continue;

            $allowed_mime = Find_MIME_from_extension($ext);
            if($allowed_mime!==null) $allowed_mimes[] = $allowed_mime;
        }

        $http_code = 200;
        $err_description = "";
        $client_code = $php_err_code;
        $headers = [];

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
                $err_description = "Failed to write file. Can be permission problem or other unknown issues. Please contact with admin to solve this problem";
                break;
            case UPLOAD_ERR_EXTENSION:
                $http_code =  415;
                $client_code = FrontEnd_upload_err::invalid_file_type;
                $err_description = "Php failed to process this file because of extension, Please provide valid extension";
                break;
            case Custom_Upload_Err::no_gd_support:
                $http_code = 500;
                $client_code = FrontEnd_upload_err::cannot_start;
                $err_description = "Required php library isn't avaible, either don't have enabled or haven't instaled php_gd.dll in default extension folder";
                break;
            case Custom_Upload_Err::cannot_start:
                $http_code = 500;
                $client_code = FrontEnd_upload_err::cannot_start;
                $err_description = "The request failed because lack of required library to work or failed to start validation proccess.";
                break;
            case Custom_Upload_Err::invalid_extension:
                $http_code = 400;
                $client_code = FrontEnd_upload_err::invalid_file_type;
                $err_description = "The content of file doesn't match or not allowed extension expected";
                $headers = ["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"];
                break;
            case Custom_Upload_Err::invalid_mime:
                $http_code = 400;
                $client_code = FrontEnd_upload_err::invalid_file_type;
                $err_description = "Provided mime type given by browser or extension from raw name of file isn't allowed";
                $headers = ["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"];
                break;
            case Custom_Upload_Err::invalid_upload:
                $http_code = 409;
                $client_code = FrontEnd_upload_err::invalid_file_type;
                $err_description = "Invalid upload";
                $headers = ["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"];
                break;
            case Custom_Upload_Err::unable_spot_file_type:
                $http_code = 400;
                $client_code = FrontEnd_upload_err::invalid_file_type;
                $err_description = "cannot find correct extension of uploaded file";
                $headers = ["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"];
                break;
            case Custom_Upload_Err::too_many_request:
                $http_code = 429;
                $client_code = FrontEnd_upload_err::too_many_request;
                $err_description = "The server is busy now.";
                break;
            case Custom_Upload_Err::timeout:
                $http_code = 408;
                $client_code = FrontEnd_upload_err::timeout;
                $err_description = "The request uses too much time to process uploaded file, current request will be abandoned";
                break;
        }
        return ['http_code'=>$http_code,'description'=>$err_description,'headers'=>$headers,'client_code'=>$client_code];
    }
?>