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

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;

    require_once __DIR__ . "/../../AppCommonVar.php";

    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";

    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
use Exception;
use Monolog\Level;
use Throwable;

    require_once __DIR__ . "/../../MIME_type.php";

use function ACEX_project\WEB\Private\Api\database\Column_belong_to_table;
use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Get_img_db_location;
use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;
use function ACEX_project\WEB\Private\Api\database\Simple_WHERE_wrapper;
use function ACEX_project\WEB\Private\Core\Construct_MIME;
    use function ACEX_project\WEB\Private\Core\Find_MIME_from_extension;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;
use function ACEX_project\WEB\Private\Core\Send_mime_type;
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
     * stage mean stage of validation. 1 mean in validation and 2 mean in ai validation, 3 mean db
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
        Send_mime_type(MIME['application']['json']);
        echo json_encode(['success'=>0,'stage'=>0]) . "\n";
        \ob_flush();
        
        $finfo_txt = Get_file_log_format();
        $ip = Get_IP_address_txt();
        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[IP-\"$ip\" | File Type - $target_location->name] Upload file $finfo_txt");

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
        $ip = Get_IP_address_txt();
        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[IP-\"$ip\" | File type - $target_location->name] Rewriting and compressing file $finfo_txt");
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
        

        $internal_fname = $new_name. '.'.$compressed_ext->name;
        $finfo_txt = Get_file_log_format($internal_fname);

        Register_image_in_db($target_location, $internal_fname, $og_name, $allowed_ext);


        Log_internal(new Error_code(Error_domain::upload,Resource_code::file,Error_condition::received), Level::Info,"[IP-\"$ip\" | File type - $target_location->name] File stored sucessfully: $finfo_txt");
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

    function Exit_upload_err(Custom_Upload_Err|int $code, int $stage,array $allowed_ext,string $og_name,string $moved_fpath = "", string $internal_name = "", string $extra_msg = "") : never
    {
        $ip = Get_IP_address_txt();
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

        $extra_msg = $extra_msg === "" ? '' : "| $extra_msg";

        Log_internal(
            new Error_code(Error_domain::request,Resource_code::file,Error_condition::failed),
            Level::Warning,
            "[IP-\"$ip\"](code: $code_args) $err_desc $extra_msg"
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


    /**
     * If alredy exist, delete old image
     */
    function Register_image_in_db(File_storation_path $category, string $internal_fname, string $user_upload_fname , array $allowed_ext){
        $full_name = $category->value . DIRECTORY_SEPARATOR . $internal_fname;
        $uid = $_SESSION['uid'];

        $db_map = Get_img_db_location($category);

        if($db_map === []) Exit_upload_err(Custom_Upload_Err::db_reference_missing,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname);
        $table = $db_map['table'];
        $column = $db_map['column'];
        $uid_column = $db_map['user_id'];

        if(!Is_query_permitted(HOST,[Query_type::select,Query_type::update],$table,[$column])) 
            Exit_upload_err(Custom_Upload_Err::db_failed,3,
            $allowed_ext,$user_upload_fname,$full_name,$internal_fname,
            "Not enought permission for $table->name and column [$column->name, $uid_column->name]"
        );
        
        

        $conn = null;
        try{
            $old_fname = Get_old_file_name($table,$column,$uid_column);

            if(is_int($old_fname)){
                switch($old_fname){
                    //new upload file
                    case 0: break;
                    case 1: Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Cannot connect to database when getting old file reference");
                    case 2: Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Prepare statement failed when getting old file reference");
                    case 3: Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Execute query to get old file reference failed");
                    case 4: Exit_upload_err(Custom_Upload_Err::db_reference_missing,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "No record found to update of table \"$table\"");
                    default: Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Unknown error when getting old file reference");
                }
            }
            
            //keep connect with old db connection
            $conn = Connect_database(true);

            if($conn === null) Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname);
            if(!$conn->inTransaction()) $conn->beginTransaction();


            //update reference
            $query = "
                UPDATE $table->name
                SET $column->name = :new_file
                WHERE $uid_column->name = :uid
            ";

            $smtm = $conn->prepare($query);
            if($smtm===false) {
                if($conn->inTransaction()) $conn->rollBack();
                Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Failed to prepare statement when update new file reference");
            }


            $success = $smtm->execute(['uid'=>$uid,'new_file'=>$internal_fname]);


            if($smtm->rowCount()===0) {
                if($conn->inTransaction()) $conn->rollBack();
                Exit_upload_err(Custom_Upload_Err::db_reference_missing,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "No affected row when update new file reference");
            }

            if(!$success || ($conn->inTransaction() && !$conn->commit())) {
                if($conn->inTransaction()) $conn->rollBack();
                Exit_upload_err(Custom_Upload_Err::db_failed,3,$allowed_ext,$user_upload_fname,$full_name,$internal_fname, "Failed to execute query when update new file reference");
            }

            $ip = Get_IP_address_txt();

            $log_msg = "uploaded new file \"$user_upload_fname\"";
            if(!is_int($old_fname)) {
                $log_msg = "replacing (old internal name - \"$old_fname\") with new file \"$user_upload_fname\"";
                \unlink("{$category->value}/$old_fname");
            }
            

            
            Log_internal(
                new Error_code(Error_domain::credential,Resource_code::creation,Error_condition::duplicated),
                Level::Notice,
                "[IP-\"$ip\"] User(uid - $uid) $log_msg [internal_name: $internal_fname | final directory - $category->name]"
            );

            if($table === All_tables::user) $_SESSION['profile_img_url'] = '/'. URI_PREFIX ."/image/profile/{$internal_fname}";

            
            Send_response(0,3,$user_upload_fname,201);
        }catch(Throwable $e){
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
            Exit_upload_err(Custom_Upload_Err::db_failed, 3, $allowed_ext, $user_upload_fname, $full_name, $internal_fname, $e->getMessage());
        }
    }

    /**
     * Get old file name before replace it to get reference to unlink it.
     * 
     * If unexpected error occur, e.g., sql query error, you may need catch it when use this
     * 
     * If no previous transaction, then start new one. subsequence from Connect_database will be same as here and transaction will not exit if no error occurs(so you may need to commit/rollback it).
     * @param \UnitEnum $file_column column name which store file name
     * @param \UnitEnum $uid_column column name for current table
     * @return string|int  INT mean failed: 0 = new file, 1 = failed to connect, 2= prepare failed, 3 = execute, 4 = record not found;
     * @param list<array{column:\UnitEnum,value:mixed}> $extra_condition expect: [value_name => value], become WHERE ... value_name = :value
     * @throws Exception
     */
    function Get_old_file_name(All_tables $table, \UnitEnum $file_column, \UnitEnum $uid_column, array $extra_condition = []) : string|int{
        $conn = null;

        if(!Column_belong_to_table($table,[$file_column,$uid_column],false)) throw new Exception("Requested columns [$uid_column->name, $file_column->name] doesn't exist in table \"{$table->name}\"");

        $uid = $_SESSION['uid'];
        $conn = Connect_database(true);
        
        if($conn === null) return 1;

        if(!$conn->inTransaction()) $conn->beginTransaction();

        $extra_condition[] = ['column'=> $uid_column, 'value'=>$uid];

        $condition = Simple_WHERE_wrapper($extra_condition);
        $where_clause = $condition['condition'];

        $query = "
            SELECT $file_column->name , $uid_column->name
            FROM $table->name 
            $where_clause
        ";


        $smtm = $conn->prepare($query);
        if($smtm===false) {
            if($conn->inTransaction()) $conn->rollBack();
            return 2;
        }

        $success = $smtm->execute($condition['binded_value']);
        if(!$success) {
            if($conn->inTransaction()) $conn->rollBack();
            return 3;
        }
        
        if($smtm->rowCount() <= 0) return 4;
        
        $row = $smtm->fetch(\PDO::FETCH_ASSOC);
        return $row[$file_column->name] ?? 0;
    }