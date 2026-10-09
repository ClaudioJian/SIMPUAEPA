<?php
namespace ACEX_project\WEB\Private\Core\Security\File_upload; 
    require_once __DIR__ . "/File_upload_utils.php";
    use ACEX_project\WEB\Private\Core\Avaible_extension;

    require_once __DIR__ . "/../../../Core/AppCommonVar.php";

    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;


    require_once __DIR__ . "/../../MIME_type.php";

    use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
    use function ACEX_project\WEB\Private\Error\Handle_error;

    require_once __DIR__ . "/Child_process_handler.php";

    if(php_sapi_name() !== "cli") {
        http_response_code(403);
        exit();
    }

    //if(!function_exists("imagecreatefrompng")) exit();
    
    
    Start();


    
    function Start(){
        $stdin_pipe = new Pipe(STDIN,1);
        $stdout_pipe = new Pipe(STDOUT,0);
        $stderr_pipe = new Pipe(STDERR,2);       
        $allowed_ext = Get_allowed_extension($stdin_pipe, $stdout_pipe );


        $allowed_ext = Get_allowed_extension($stdin_pipe,$stdout_pipe);
        $stdout_pipe->write(json_encode(['success'=>0,'stage'=>1,'allowed_ext_received'=>$allowed_ext]) . "\n",0,20000);

        exit();
    }

    /**
     * 
     */
    function Request_to_parent(Pipe &$stdout, int $stage){
        $stdout->write(json_encode(['success'=>1,'stage'=>$stage]) . "\n",0,20000);
    }


    //rewrite the uploaded file if possible, also rename the file name to random name. This function try to CDR.
    function File_rewrite(Avaible_extension $ext = Avaible_extension::png){
        
    }

    /**
     * Validate if current uploaded file is valid or no by checking $_FILES
     * @param bool $exit control whether exit or no when validation failed, the automatic header is send in this case
     * @param Avaible_extension[] $allowed_ext only last extension is checked, e.g: jpeg, png; the returned Accept-post may be innacuracy
     * @return int|Avaible_extension 0 for sucess, positive for php's UPLOAD_ERR_* and negative is custom error code. return Avaible_extension when sucess validation
     */
    function Validate_uploaded_file(array $allowed_ext, bool $exit = true) : int|Avaible_extension{
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
            if(!$ext instanceof Avaible_extension){
                Handle_error(
                    new Error_code(Error_domain::server,Resource_code::syntax,Error_condition::type),
                    Log_level::fatal,
                    'Invalid argument $allowed_ext in Validate_uploaded_file(): the value isnt enum Avaible_extension.'
                );
            }

            $allowed_mime = Find_MIME_from_extension($ext);
            if($allowed_mime!==null) $allowed_mimes[] = $allowed_mime;
            if($client_provided_mime !== $allowed_mime) {
                return Invalid_MIME($exit,$allowed_mimes);
            }
        }


        //fast check(extension)
        $file_name = mb_strtolower(trim($_FILES[FILE_UPLOAD_FIELD_NAME]['name']));
        $extension = Avaible_extension::Convert(pathinfo($file_name,PATHINFO_EXTENSION));

        if(!\in_array($extension,$allowed_ext,true)){
            return Invalid_MIME($exit,$allowed_mimes);
        }

        //validate real extension by content
        $temp_file = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        if(!is_uploaded_file($temp_file)) {
            if($exit) {
                Exit_upload_err(400,Custom_Upload_Err::invalid_upload->value,
                ["Accept-Post: ". implode(', ',$allowed_mimes),"allow: post"],
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
                
        return $extension;
    }

    function Get_allowed_extension(Pipe &$stdin ,Pipe &$stdout) : array{
        error_log("CHILD WRITES!");
        Request_to_parent($stdout,1);

        error_log("CHILD START READS!");
        $stdin->Read_until_then(function(string $raw_data){
                $allowed_ext = json_decode($raw_data);
                return $allowed_ext;
            },
        0,20000,1);

        return [];
    }

    function Exit_upload_err(int $http_code, int $code, array $headers, string $err_description)
    {
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