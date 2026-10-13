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

    use function ACEX_project\WEB\Private\Core\Construct_MIME;
    use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;


    require_once __DIR__ . "/../../../Error/Error_code.php";
    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

    require_once __DIR__ . "/../../../Error/Error_manager.php";
    use function ACEX_project\WEB\Private\Error\Handle_error;



    function Strip_all_extension(string $fname): string{
        $dotpos = \strrpos($fname,".");
        if($dotpos === false) return $fname;
        return \trim(\substr($fname,0, $dotpos));
    }

    /**
     * Convert a byte-size string into byte. Support bytes, kb, megabyte(above this will have unexpected result in 2GB).
     * Accept format:
     * - Integer or decimal without unit, interpreted as byte.
     * - value with the suffix "b" or "byte"(e.g.,15b, 15byte).
     * - value with suffix "m" or "mb"
     * - value with suffix "k" or "kb"
     * 
     * Units are case-insentive. 1024 as conversion base.
     * @param string $byte_size byte-size value to be parsed
     * @return int equivalent size in byte
     */
    function Parse_byte(string $byte_size) : int{
        $byte_size = \strtolower(trim($byte_size));

        //?: mean no capture, separate group will be insert to $matches in order found matches

        $optional_decimal = '(?:\.\d+)?';
        $optional_size_unit = '(?:([km])?b?|byte|b)';
        $pattern = '/^(' . "\\d+$optional_decimal" . ')'.
            '(?:'. $optional_size_unit . ')?' .
            '$/i';
        
    
        //start with at least 1 digit in string(^\d+)
        //find 1 or 0 of . with following digit(\.?)
        //in end have k/b/m
        // in end may have 1 or 0 of b
        if(!\preg_match($pattern,$byte_size,$matches)){
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::invalid),
                Level::Critical,
                "Invalid byte size when parsing byte: $byte_size"
            );
        }

        $size_unit = $matches[2] ?? 'b';
        $numeric = ($matches[1]) ?? 0;
        $numeric = \intval($numeric);

        //byte, kb, mb
        $exponent = match($size_unit){
            'b'=>0,
            'k'=>1,
            'm'=>2,
            default => 0,
        };

        return $numeric * \pow(1024,$exponent);
    }

    enum Custom_Upload_Err:int{
        case invalid_mime = -1;
        case unable_spot_file_type = -2;
        case invalid_upload = -3;
        case invalid_extension = -4;
        case cannot_start = -5;
        case too_many_request = -6;
        case timeout = -7;
        case no_gd_support = -8;
        case cannot_move_file = -9;
        case invalid_argument = -10;
        case cannot_find_requested_file = -11;
        case directory_not_exist = -12;
        case unsupported_extension = -13;
        case rewrite_failed = -14;
        case size_too_big = -15;

        private function map(int $http_code,FrontEnd_upload_err $client_code,string $err_description,array $headers = []){
            return ['http_code'=>$http_code,'description'=>$err_description,
                'headers'=>$headers,
                'client_code'=>$client_code
            ];
        }

        public function get_error_message(array $allowed_mimes): array{
            return match($this){
                Custom_Upload_Err::no_gd_support => $this->map(500,FrontEnd_upload_err::cannot_start,"Required php library isn't avaible, either don't have enabled or haven't instaled php_gd.dll in default extension folder."),
                Custom_Upload_Err::cannot_start => $this->map(500,FrontEnd_upload_err::cannot_start,"The request failed because lack of required library to work or failed to start validation proccess."),
                Custom_Upload_Err::cannot_move_file => $this->map(500,FrontEnd_upload_err::validation_failed,"File upload failed in Secure_file_upload(): the file isn't valid or cannot be moved! Please check if directory exist and have correct permission set."),
                Custom_Upload_Err::invalid_argument => $this->map(500,FrontEnd_upload_err::validation_failed,"Invalid argument error when pass to file_rewriter. Expect 3 argument(open file, request file name, file extension)"),
                Custom_Upload_Err::cannot_find_requested_file => $this->map(500,FrontEnd_upload_err::validation_failed,"Invalid argument error when pass to file_rewriter. cannot find file requested in Uploads/images/tmp/Awaiting_sanitation"),
                Custom_Upload_Err::directory_not_exist => $this->map(500,FrontEnd_upload_err::validation_failed,"Directory doesnt exist in one of: Uploads/images/tmp/*"),
                Custom_Upload_Err::rewrite_failed => $this->map(500,FrontEnd_upload_err::validation_failed,"Rewriting the uploaded file failed."),
                Custom_Upload_Err::invalid_extension => $this->map(400,FrontEnd_upload_err::invalid_file_type,"The content of file doesn't match or not allowed extension expected",["Accept-Post: ". implode(', ',$allowed_mimes)]),
                Custom_Upload_Err::invalid_mime => $this->map(400,FrontEnd_upload_err::invalid_file_type,"Provided mime type given by browser or extension from raw name of file isn't allowed",["Accept-Post: ". implode(', ',$allowed_mimes)]),
                Custom_Upload_Err::invalid_upload => $this->map(409,FrontEnd_upload_err::invalid_file_type,"Invalid upload",["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"]),
                Custom_Upload_Err::unable_spot_file_type => $this->map(400,FrontEnd_upload_err::invalid_file_type,"cannot find correct extension of uploaded file",["Accept-Post: ". implode(', ',$allowed_mimes)]),
                Custom_Upload_Err::too_many_request => $this->map(429,FrontEnd_upload_err::too_many_request,"The server is busy now."),
                Custom_Upload_Err::timeout => $this->map(408,FrontEnd_upload_err::timeout,"The request uses too many time to process uploaded file, current request will be abandoned"),
                Custom_Upload_Err::unsupported_extension => $this->map(415,FrontEnd_upload_err::unsupported_extension,"The uploaded file extension isn't supported by server.",["Accept-Post: ". implode(', ',$allowed_mimes)]),
                Custom_Upload_Err::size_too_big => $this->map(413,FrontEnd_upload_err::size_too_big,'size of uploaded file is too big!'),
            };
        }
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
        case unsupported_extension = -7;
        case size_too_big = -8;
        case no_file = -9;
        case unexpected = -99;
    }

    /** values is relative to current folder, which is ...Core/Security 
     * The directory structure:
     * Uploads
     * |__Images
     * |    |__Maps
     * |    |__Profiles
     * |__tmp
     *      |__Awaiting_sanitation
     *      |__Awaiting_validation
    */
    enum File_storation_path: string{
        case Error_logs = __DIR__ . "/../../../../../Log";
        case User_uploads = __DIR__ . "/../../../Uploads/";
            case Temp = __DIR__ . "/../../../Uploads/tmp";
                case Await_sanitation = __DIR__ . "/../../../Uploads/tmp/Awaiting_sanitation";
                case Await_validation = __DIR__ . "/../../../Uploads/tmp/Awaiting_validation";

            case User_images = __DIR__ . "/../../../Uploads/Images";
                case Maps = __DIR__ . "/../../../Uploads/Images/Maps";
                case profiles = __DIR__ . "/../../../Uploads/Images/Profiles";
        
        /**
         * No need create all, only the end of file tree need be created. check mkdir() for more information
         * @link https://www.php.net/manual/en/function.mkdir.php
         */
        public function Initialize_directory() : bool{
            $dir = $this->value;
            if(\is_dir($dir)) return true;
            if(\file_exists($dir)) return false;

            return \mkdir($dir,0700, true) || \is_dir($dir);
        }
    }

    /**
     * Move the current uploaded file from temp location to correct location, modify the name by param $name or by unique id.
     * If name is longer than 250 char or is empty
     * @param string $from absolute path if file will be moved
     * @param string $name all extension will be stripped(any after .)
     * @param bool $internal_move if false or not set, then will check is uploaded via http
     * @return bool|string full path name($to/$name.ext) when sucess else false
     */
    function Safe_file_store(File_storation_path $to,Avaible_extension $ext,string $name,string $from = "",bool $internal_move = false) : bool|string{
        if($from === '') $from = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        $name = Strip_all_extension(\basename($name));
        if(empty($name) || \strlen($name)>=250) $name = \uniqid();
        $dest = "$to->value/$name.$ext->name";


        $success = $internal_move ? \rename($from,$dest) : \move_uploaded_file($from,$dest);
        if(\is_file($from)) \unlink($from);

        return $success===false ? false:$dest;
    }

    /**
     * @return array{
     *      http_code: int,
     *      description: string,
     *      headers: array,
     *      client_code: FrontEnd_upload_err
     * } | null if dont match any or have no error
     */
    function Get_descriptive_ERRupload(Custom_Upload_Err|int $php_err_code, array $accept_ext = []){
        //fast mime check
        $allowed_mimes = [];
        $result = ['client_code' => 0];
        
        foreach($accept_ext as $ext){
            if(!$ext instanceof Avaible_extension) continue;

            $allowed_mime = Find_MIME_from_extension($ext);
            if($allowed_mime!==null) $allowed_mimes[] = $allowed_mime;
        }

        $http_code = 200;
        $err_description = "";
        $client_code = 0;
        
        
        $headers = [];

        if(\is_int($php_err_code)) {
            $find = true;
            switch($php_err_code){
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $http_code =  413;
                    $err_description = "The uploaded file size is too big";
                    $client_code = FrontEnd_upload_err::size_too_big;
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $http_code =  400;
                    $err_description = "The uploaded file is only uploaded partially";
                    $client_code = FrontEnd_upload_err::invalid_upload;
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $http_code =  400;
                    $err_description = "No file was uploaded";
                    $client_code = FrontEnd_upload_err::no_file;
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $http_code =  500;
                    $err_description = "Php configuration error: No location to store temporary file before process request. Please contact with admin to solve this problem";
                    $client_code = FrontEnd_upload_err::validation_failed;
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $http_code =  500;
                    $err_description = "Failed to write file. Can be permission problem or other unknown issues. Please contact with admin to solve this problem";
                    $client_code = FrontEnd_upload_err::validation_failed;
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $http_code =  415;
                    $client_code = FrontEnd_upload_err::invalid_file_type;
                    $err_description = "Php failed to process this file because of extension, Please provide valid extension";
                    break;
                default: $find = false;
            };
            if($find) {
                $result = ['http_code'=>$http_code,'description'=>$err_description,
                    'headers'=>[...$headers,"content-type: ".Construct_MIME(MIME['application']['json'])],
                    'client_code'=>$client_code];
            }
        }else $result = $php_err_code->get_error_message($allowed_mimes);

        if($result['client_code'] === 0){
            $http_code = 500;
            $client_code = FrontEnd_upload_err::unexpected;
            $err_description = "Unkown Error!";

        }
        return $result;
    }