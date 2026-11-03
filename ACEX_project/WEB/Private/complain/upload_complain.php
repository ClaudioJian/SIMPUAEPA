<?php

namespace ACEX_project\WEB\Private\complain;

require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\File_storation_path;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;

require_once __DIR__ . "/../Error/Error_code.php";
require_once __DIR__ . "/../Error/Error_manager.php";
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

use function ACEX_project\WEB\Private\Api\database\Database_simple_insert;
use function ACEX_project\WEB\Private\Core\Security\File_upload\Secure_file_upload;
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;
use function ACEX_project\WEB\Private\Core\Send_mime_type;
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

require_once __DIR__ . "/../Core/Security/Request_manager.php";
require_once __DIR__ . "/../Core/MIME_type.php";

require_once __DIR__ . "/../Core/Security/File_upload/File_upload.php";

require_once __DIR__ . "/../Core/File_storation_map.php";

require_once __DIR__ . "/../api/database/database_manager.php";
require_once __DIR__ . "/../api/database/database_dictionary.php";
require_once __DIR__ . "/../api/database/Authorization_manager.php";

require_once __DIR__ . "/location_handler.php";
require_once __DIR__ . "/Content_validator.php";


/**
 * list all field can be send by client
 * negative mean the value will not pass to client as invalid_field
 */
enum Complain_data_fields:int{
    case title = 1;
    case description = 2;
    case location_reference = 3;
    case ang_x = 4;
    case ang_y = 5;
    case intensity_level = 6;
    case type = -1;
    case crop_option = -2;

    private static function validate_text_lenght(int $max_lenght, string $text_type , string $val, ?string& $err_msg) : bool{
        if(mb_strlen($val, ENCODING_CHARSET) <= $max_lenght) return true;
        $err_msg = "The $text_type cannot exceed {$max_lenght} charactere";
        return false;
    }

    /**
     * @return array{
     * 'type':string,
     * 'required':bool,
     * 'default':mixed,
     * 'compare_fn':callable(int,?string&),
     * } Where: compare_fn is callable returns bool and receive sanitized value and validate using user provided function and second argument is referece to write error messague when user comparation function returns false;
     * The value passed in custom compare function alredy validate by type explicited.
     */
    private function sanitization_rule() : array{
        return match($this){
            self::title => [
                'type'=>'string',
                'required'=>true,
                'compare_fn'=>fn(string $val, ?string& $err_msg) => self::validate_text_lenght(MAX_TITLE_LENGTH, 'title', $val, $err_msg)
            ],
            self::description => [
                'type'=>'string',
                'required'=>true,
                'compare_fn'=> fn(string $val, ?string& $err_msg) => self::validate_text_lenght(MAX_DESCRIPTION_LENGTH, 'description', $val, $err_msg)
            ],
            self::location_reference=>[
                'type'=>'string',
                'required'=>false,
                'compare_fn'=>fn(string $val, ?string& $err_msg) => self::validate_text_lenght(MAX_LOCATION_REFERENCE_LENGTH, 'location reference', $val, $err_msg)
            ],
            self::ang_x=>[
                'type'=>'double',
                'required'=>true
            ],
            self::ang_y=>[
                'type'=>'double',
                'required'=>true
            ],
            self::type=>[
                'type'=>'int',
                'required'=>false,
                'default'=>1,
                'compare_fn' => function(int $val, ?string &$cond_failed_msg){
                        if($val >0 && $val<=8) return true;
                        $cond_failed_msg = 'The value should between 1 to 8';
                        return false;
                    },
            ],
            self::intensity_level=>[
                'type'=>'double',
                'required'=>true,
                'compare_fn' => function(int $val, ?string &$cond_failed_msg){
                        if($val >=1 && $val<=5) return true;
                        $cond_failed_msg = 'The value should between 1 to 5';
                        return false;
                },
            ],
            self::crop_option=>[
                'type'=>'array',
                'required'=>false,
                'default'=>[]
            ]
        };
    }

    private function invalid_type(string $type, array &$data, string $k, mixed $default, Upload_err_code &$err_code, string &$extra_msg):bool{
        if($default !== null) {
            $data[$k] = $default;
            return true;                        
        }

        $err_code = Upload_err_code::invalid_type;
        $extra_msg = "Field \"{$k}\" must be $type and has no default value";
        return false;
    }

    private function in_range(mixed $value, ?callable $compare_fn, array &$data, string $k, mixed $default, ?string &$err_msg):bool{
        $success = $compare_fn===null || $compare_fn($value, $err_msg);

        if(!$success && $default === null) return false;
        
        $data[$k] = $success ? $value : $default;
        return true;
    }

    /**
     * sanitize and validate data. 
     * 
     * If the value isn't required and is empty, then if default value don't exist, insert [$key]=>null, else use default value to $data
     * If is required but no default, false will be returned.
     * @param string $extra_msg extra message to log when cannot sanitize the value, use to add after main log
     * @param Upload_err_code $err_code code showing why failed. Can be used to extract main log message
     * @return bool false if data is required but is empty/null
     */
    public function sanitize(array &$data, Upload_err_code &$err_code, ?string &$extra_msg) : bool{
        $value = $this->sanitization_rule();
        $k = $this->name;

        $type = $value['type']??'string';
        $required = $value['required']??true;
        $default = $value['default']??null;
        $compare_fn = $value['compare_fn']??null;
        $err_msg = '';

        if(!\array_key_exists($k, $data) || $data[$k] === null || $data[$k] === ''){
            if(!$required){
                //optional field will not removed but became null in case default value is missing
                $data[$k] = $default;
                return true;
            }

            if($default===null){
                $err_code = Upload_err_code::empty_value;
                $extra_msg = "The value of field \"{$k}\" is empty and has no default value";
                return false;
            }
        }

        switch($type){
            case 'string':
                if(!\is_string($data[$k])) return $this->invalid_type('string',$data,$k,$default,$err_code,$extra_msg);


                $res = $this->in_range(mb_trim($data[$k],encoding:ENCODING_CHARSET),$compare_fn,$data,$k,$default,$err_msg);
                if($res) return true;

                $err_code = Upload_err_code::invalid_value;
                if($err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$err_msg}] and has no default value";

                return false;
            case 'int':
                if(\filter_var($data[$k],FILTER_VALIDATE_INT) === false) return $this->invalid_type('int',$data,$k,$default,$err_code,$extra_msg);
                

                $res = $this->in_range((int)($data[$k]),$compare_fn,$data,$k,$default,$err_msg);
                if($res) return true;

                $err_code = Upload_err_code::invalid_value;
                if($err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$err_msg}] and has no default value";

                return false;
            case 'double':
                $val = 0;
                if(!\is_numeric($data[$k]) || !\is_finite(($val = (float)$data[$k]))) return $this->invalid_type('valid double',$data,$k,$default,$err_code,$extra_msg);

                $res = $this->in_range($val,$compare_fn,$data,$k,$default,$err_msg);
                if($res) return true;
                $err_code = Upload_err_code::invalid_value;
                if($err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$err_msg}] and has no default value";

                return false;
            case 'array':
                if(!\is_array($data[$k])) return $this->invalid_type('array',$data,$k,$default,$err_code,$extra_msg);

                $res = $this->in_range($data[$k],$compare_fn,$data,$k,$default,$err_msg);

                if($res) return true;
                $err_code = Upload_err_code::invalid_value;
                if($err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$err_msg}] and has no default value";

                return false;
            
            default:{
                $err_code = Upload_err_code::invalid_type;
                $extra_msg = "in Complain_data_fields.sanitize(): invalid type";
                return false;
            }
        }
    }
}

enum Upload_err_code{
    case none;
    case success;
    case empty_value;
    case invalid_type;
    case invalid_value;
    case etag_wrong_version;
    case err_internal;
    case database_connection;
    case database_register;
    case violation_policies_rules;
    case inappropriate_content;
    case unauthorized;
    case invalid_coordinate;
    case size;
    case register_coordinate;
    
    case unknown;

    private static function map(int $http_code, string $desc, Upload_frontend_err_code $code, array $headers, Error_domain $category){
        return [
            'http_code'=>$http_code,
            'description'=>$desc,
            'frontend_code'=>$code,
            'headers'=>$headers,
            'err_type'=>$category
        ];
    }
    /**
     * Get array of info about the error code
     * 
     * @param bool $end true will make some message change, specifically self::success to end request. else will log as start to next stage.
     * @return array{
     * 'http_code':int,
     * 'description':string,
     * 'frontend_code':Upload_frontend_err_code,
     * 'headers':array,
     * 'err_type': Error_domain
     * } err_type use for decide type of error(mainly used for log)
     */
    public function info(bool $end = false) : array{
        return match($this){
            self::none => self::map(500,'Unexpected error',Upload_frontend_err_code::unknown,[], Error_domain::server),
            self::success => self::map(201,$end ? 'Sucessfully uploaded complain' : 'sucessfully validate all non-file fields, moving to next stage',Upload_frontend_err_code::success,[], Error_domain::request),
            self::empty_value => self::map(400,'Empty value uploaded',Upload_frontend_err_code::empty_value,[], Error_domain::request),
            self::invalid_type => self::map(400, 'Invalid type data send by user', Upload_frontend_err_code::invalid_value,[],Error_domain::request),
            self::invalid_value => self::map(400,'Invalid value provided by user', Upload_frontend_err_code::invalid_value,[],Error_domain::request),
            self::invalid_coordinate => self::map(400,'Invalid coordinate provided',Upload_frontend_err_code::invalid_coordinate,[],Error_domain::request),
            self::size=>self::map(413,'Uploaded content exceeds the allowed size', Upload_frontend_err_code::size, [], Error_domain::request),
            self::etag_wrong_version=>self::map(409, 'ETag version mismatch. Expected to update resource version', Upload_frontend_err_code::etag_wrong_version,[],Error_domain::request),
            self::violation_policies_rules=>self::map(403,'Uploaded content violates application policies or rules', Upload_frontend_err_code::violation_policies_rules,[],Error_domain::request),
            self::inappropriate_content=>self::map(422,'Uploaded content is inappropriate', Upload_frontend_err_code::inappropriate_content,[],Error_domain::request),
            self::unauthorized=>self::map(403,'Current user have no right to edit/upload image of map or post', Upload_frontend_err_code::unauthorized,[],Error_domain::request),
            self::database_connection=>self::map(500,'Database connection failed',Upload_frontend_err_code::err_internal,[],Error_domain::database),
            self::database_register=>self::map(500,'Database registration failed',Upload_frontend_err_code::err_internal,[],Error_domain::database),
            self::register_coordinate=> self::map(500,'Failed to register new coordinate',Upload_frontend_err_code::err_internal,[],Error_domain::database),
            self::err_internal=>self::map(500,'Internal server error',Upload_frontend_err_code::err_internal,[],Error_domain::server),
            self::unknown=>self::map(500,'Uknown error',Upload_frontend_err_code::unknown,[],Error_domain::server)
        };

    }
}

enum Upload_frontend_err_code : int{
    case success = 0;
    case empty_value = -1;
    case etag_wrong_version = -2;
    case err_internal = -3;
    case violation_policies_rules = -4;
    case inappropriate_content = -5;
    case unauthorized = -6;
    case invalid_coordinate = -7;
    case size = -8;
    case invalid_value = -9;
    case unknown = -99;
}

function Upload_complain(?Rate_limiter $IP_limiter, array $captured_args){
    $data = Get_request_body();

    $err_code = Upload_err_code::none;
    foreach(Complain_data_fields::cases() as $fields){
        if($fields->sanitize($data, $err_code, $extra_msg)) continue;
        Send_response(false, $err_code,$fields, $extra_msg ?? '');
        exit();
    }

    $old_file = null;
    $current_internal_fpath = null;
    if(isset($_FILES[FILE_UPLOAD_FIELD_NAME]['size']) && $_FILES[FILE_UPLOAD_FIELD_NAME]['size']) {
        //send the first finish part if has upload file, else jump straight to database operation, which result only one json response and no new line
        Send_response(true,Upload_err_code::success);
        $finfo = Secure_file_upload(
            File_storation_path::Maps,
            [Avaible_extension::jpg, Avaible_extension::png, Avaible_extension::webp],
            $data['crop_option'],
            false,
            false
        );
        
        $old_file = $finfo['unlink_target'] ?? null;
        $current_internal_fpath = $finfo['current_fname'];
    }

    //alredy sanitized, so key always exist(can be null thought)

    $title = $data['title'];
    $desc = $data['description'];
    $location_ref = $data['location_reference'];

    $validate_content = ['title'=>$title,'description'=>$desc,'location reference'=>$location_ref];
    foreach($validate_content as $field => $val_target){
        if($val_target==null) continue;

        $reject_type = 0;
        $reject_reason = '';
        if(!Validate_content($val_target, $reject_reason, $reject_type)) {
            $err = match($reject_type){
                1=>Upload_err_code::violation_policies_rules,
                2=>Upload_err_code::inappropriate_content,
                default=>Upload_err_code::unknown
            };
            Send_response(false,$err,Complain_data_fields::tryFrom($field),$reject_reason,true);
        }
    }

    $ang_x = $data['ang_x'];
    $ang_y = $data['ang_y'];
    $invalid_reason = '';
    $valid_coordinate = Validate_coordinate($ang_x, $ang_y, $invalid_reason);
    if($valid_coordinate !== true) Send_response(false, Upload_err_code::invalid_coordinate,
                                        $valid_coordinate === 1 ? Complain_data_fields::ang_x : Complain_data_fields::ang_y,
                                        $invalid_reason, true);


    //insert if location was not found and use that id from insertion, else select by using x and y
    $location_id = Upsert_location($ang_x,$ang_y,false,true);
    if($location_id === null) Send_response(false, Upload_err_code::register_coordinate, end:true);



    $complain_type = $data['type'];
    $intensity_lvl = $data['intensity_level'];

    $uid = $_SESSION['uid'];
    $complain_insert_column= [
        [
            'column' => tb_complain_columns::title,
            'value' => [$title]
        ],
        [
            'column' => tb_complain_columns::location_id,
            'value' => [$location_id]
        ],
        [
            'column' => tb_complain_columns::type,
            'value'=> [$complain_type]
        ],
        [
            'column' => tb_complain_columns::intensity_level,
            'value'=> [$intensity_lvl]
        ],
        [
            'column'=>tb_complain_columns::user_id,
            'value'=>[$uid]
        ]
    ];

    if($location_ref !== null) {
        $complain_insert_column[] = [
            'column'=>tb_complain_columns::location_reference,
            'value'=>[$location_ref]
        ];
    }

    if($desc !== null && $desc !== ''){
        $complain_insert_column[] = [
            'column' => tb_complain_columns::description,
            'value' => [$desc]
        ];
    }

    if($current_internal_fpath !== null){
        $complain_insert_column[] = [
            'column' => tb_complain_columns::image_name,
            'value' => [$current_internal_fpath]
        ];
    }


    $insert_complain = [
        'table' => All_tables::complain,
        'insert'=> $complain_insert_column
    ];


    //db insertion
    $id_list = Database_simple_insert([$insert_complain],true,true);
    $complain_id = null;

    foreach($id_list?:[] as $complain_ids){
        if($complain_ids['table'] === All_tables::complain) {
            $complain_id = (int)$complain_ids['id'];
            break;
        }
    }

    $insert_error = $id_list===false || $complain_id===null;
    


    if($insert_error) {
        //if db failed, discart user uploaded new img
        if($current_internal_fpath !== null) \unlink($current_internal_fpath);
        Send_response(false,Upload_err_code::database_register, end:true);
    }

    //db insert success, then discart old file. *obs: since is insert, the old file always result in null
    if($old_file !== null) \unlink($old_file);
    Send_response(true,Upload_err_code::success, end:true);
}

/**
 * send response to front end. exit when end
 * @param Complain_data_fields|null $err_field if null, then response will exclude invalid_field.
 * @param bool $end if true, immedially exit and change some log message
*/
function Send_response(bool $success ,Upload_err_code $code , ?Complain_data_fields $err_field = null, string $extra_msg = '', bool $end = false, int $complain_id = null){
    if(!$success) $end = true;
    $response = ['complete'=>$success?0:-1];
    $http_code = 200;

    $info = $code->info($end);

    $desc = $info['description'];
    $type = $info['err_type'];
    
    $headers = $info['headers'];
    $http_code = $info['http_code'];

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

    if(!$success) {
        if($err_field?->value > 0) $response['invalid_field'] = $err_field->value;

        $frontend_code = $info['frontend_code'];
        $response['err_code'] = $frontend_code->value;
        
        $full_msg = $desc === '' ? $extra_msg : "{$desc} | {$extra_msg}";
        if($full_msg === '') $full_msg = 'unkown error';
        Log_internal(
            new Error_code($type,Resource_code::complain,Error_condition::failed),
            Level::Info,
            $full_msg
        );
    }

    $has_file = isset($_FILES[FILE_UPLOAD_FIELD_NAME]['size']) && $_FILES[FILE_UPLOAD_FIELD_NAME]['size'] > 100;
    if($complain_id !==null) $response['complain_id'] = $complain_id;

    Send_mime_type(MIME['application']['json']);
    \http_response_code($http_code);

    $json = json_encode($response);
    if($success && $has_file) $json .= "\n";    
    echo $json;

    if($has_file) ob_flush();
    if($end) exit();
}