<?php

namespace ACEX_project\WEB\Private\complain;

require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

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

use function ACEX_project\WEB\Private\Core\Security\File_upload\Secure_file_upload;
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;
use function ACEX_project\WEB\Private\Core\Send_mime_type;
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

require_once __DIR__ . "/../Core/Security/Request_manager.php";
require_once __DIR__ . "/../Core/MIME_type.php";

require_once __DIR__ . "/../Core/Security/File_upload/File_upload.php";

require_once __DIR__ . "/../Core/File_storation_map.php";

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
    case type = -1;
    case crop_option = -2;

    private function sanitization_rule(){
        return match($this){
            self::title => [
                    'type'=>'string',
                    'required'=>true,
            ],
            self::description => [
                'type'=>'string',
                'required'=>true
            ],
            self::location_reference=>[
                'type'=>'string',
                'required'=>false
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
                'compare_fn' => fn(int $val) => $val >0 && $val<=8,
                'condition_failed_msg' => 'The value should between 1 to 8'
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

    private function in_range(mixed $value, callable $compare_fn, array &$data, string $k, mixed $default):bool{
        $success = $compare_fn===null || $compare_fn($value);

        if(!$success && $default === null) return false;
        
        $data[$k] = $success ? $value : $default;
        return true;
    }

    /**
     * sanitize and validate data
     * @param string $extra_msg extra message to log when cannot sanitize the value, use to add after main log
     * @param Upload_err_code $err_code code showing why failed. Can be used to extract main log message
     * @return bool false if data is required but is empty/null
     */
    public function sanitize(array &$data, Upload_err_code &$err_code, ?string &$extra_msg) : bool{
        $value = $this->sanitization_rule();
        $k = $this->name;

        $type = $value['type'];
        $required = $value['required'];
        $default = $value['default']??null;
        $compare_fn = $value['compare_fn']??null;
        $cond_err_msg = $value['condition_failed_msg']??'';

        if(!\array_key_exists($k, $data) || $data[$k] === null || $data[$k] === ''){
            if($default !== null || !$required){
                //optional field will not removed but became null in case default value is missing
                $data[$k] = $default;
                return true;
            }


            $err_code = Upload_err_code::empty_value;
            $extra_msg = "The value of field \"{$k}\" is empty and required but no default data to substitute invalid data";
            return false;
        }

        switch($type){
            case 'string':
                if(!\is_string($data[$k])){
                    if($default !== null) {
                        $data[$k] = $default;
                        return true;                        
                    }

                    $err_code = Upload_err_code::invalid_type;
                    $extra_msg = "The value of field \"{$k}\" is not string but no default data to substitute invalid data";
                    return false;
                }

                $res = $this->in_range(trim($data[$k]),$compare_fn,$data,$k,$default);
                if($res) return true;

                $err_code = Upload_err_code::invalid_value;
                if($cond_err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$cond_err_msg}] but no default data to use";

                return false;
            case 'int':
                if(\filter_var($data[$k],FILTER_VALIDATE_INT) === false) return $this->invalid_type('int',$data,$k,$default,$err_code,$extra_msg);
                

                $res = $this->in_range((int)($data[$k]),$compare_fn,$data,$k,$default);
                if($res) return true;

                $err_code = Upload_err_code::invalid_value;
                if($cond_err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$cond_err_msg}] but no default data to use";

                return false;
            case 'double':
                $val = 0;
                if(!\is_numeric($data[$k]) || !\is_finite(($val = (float)$data[$k]))) return $this->invalid_type('valid double',$data,$k,$default,$err_code,$extra_msg);

                $res = $this->in_range($val,$compare_fn,$data,$k,$default);
                if($res) return true;
                $err_code = Upload_err_code::invalid_value;
                if($cond_err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$cond_err_msg}] but no default data to use";

                return false;
            case 'array':
                if(!\is_array($data[$k])) return $this->invalid_type('array',$data,$k,$default,$err_code,$extra_msg);

                $res = $this->in_range($data[$k],$compare_fn,$data,$k,$default);

                if($res) return true;
                $err_code = Upload_err_code::invalid_value;
                if($cond_err_msg!=='') $extra_msg = "The value of field \"{$k}\" don't meet condition: [{$cond_err_msg}] but no default data to use";

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
    
    case unknow;

    private static function map(int $http_code, string $desc, Upload_frontend_err_code $code, array $headers, Error_domain $category){
        return [
            'http_code'=>$http_code,
            'description'=>$desc,
            'frontend_code'=>$code,
            'headers'=>$headers
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
     * } err_type use for decide type of error
     */
    public function info(bool $end = false) : array{
        return match($this){
            self::success => self::map(201,$end ? 'sucessfully uploaded complain' : 'sucessfully validate all non file fields, moving to next stage',Upload_frontend_err_code::success,[], Error_domain::request),
            self::empty_value => self::map(400,'Empty value upload for "'.$this->name.'"',Upload_frontend_err_code::empty_value,[], Error_domain::request),
            self::invalid_type => self::map(400, '', Upload_frontend_err_code::invalid_value,[],Error_domain::request)
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
    case unknow = -99;
}

function Upload_complain(?Rate_limiter $IP_limiter, array $captured_args){
    $data = Get_request_body();

    $err_code = Upload_err_code::none;
    foreach(Complain_data_fields::cases() as $fields){
        if($fields->sanitize($data, $err_code, $extra_msg)) continue;
        Send_response(-1, $err_code,$fields, $extra_msg ?? '');
        exit();
    }

    $old_file = null;
    $current_internal_fpath = null;
    if(isset($_FILES[FILE_UPLOAD_FIELD_NAME])) {
        //send the first finish part if has upload file, else jump straight to database operation, which result only one json response and no new line
        Send_response(true,Upload_err_code::success);
        $finfo = Secure_file_upload(
            File_storation_path::Maps,
            [Avaible_extension::jpg, Avaible_extension::png, Avaible_extension::webp],
            $data['crop_option'],
            false
        );
        
        $old_file = $finfo['unlink_target'] ?? null;
        $current_internal_fpath = $finfo['current_fname'];
    }

    //db insertion

    //if db failed, discart user uploaded new img
    if($current_internal_fpath !== null) \unlink($current_internal_fpath);
    Send_response(false,Upload_err_code::database_register, end:true);

    //db insert success, then discart old file. *obs: since is insert, the old file always result in null
    if($old_file !== null) \unlink($old_file);
    Send_response(true,Upload_err_code::success, end:true);
}

/**
 * send response to front end. exit when end
 * @param Complain_data_fields|null if null, then response will exclude invalid_field.
 * @param bool $end if true, immedially exit and change some log message
*/
function Send_response(bool $success ,Upload_err_code $code , ?Complain_data_fields $err_field = null, string $extra_msg = '', bool $end = false){
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

    
    Send_mime_type(MIME['application']['json']);
    \http_response_code($http_code);

    $json = json_encode($response);
    if($success && isset($_FILES[FILE_UPLOAD_FIELD_NAME])) $json .= "\n";    
    echo $json;

    if(isset($_FILES[FILE_UPLOAD_FIELD_NAME])) ob_flush();
    if($end) exit();
}