<?php

namespace ACEX_project\WEB\Private\complain;

require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

use ACEX_project\API\Input_ruleset;
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Core\Avaible_extension;
use ACEX_project\WEB\Private\Core\Data_type;
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
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;
use function ACEX_project\WEB\Private\Core\Security\File_upload\Generate_img_url;
use function ACEX_project\WEB\Private\Core\Security\File_upload\Secure_file_upload;
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
require_once __DIR__ . '/../Core/File_storation_map.php';

require_once  __DIR__ . '/../Core/Security/File_upload/File_upload_utils.php';

require_once __DIR__ . '/../../../API/Field_processor.php';
require_once __DIR__ . '/../Core/Data_type.php';

require_once __DIR__ . "/Common_complain_fields.php";


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

    public static function TryGet_case(string $name) : Complain_data_fields{
        $name = strtolower(trim($name));
        static $cases;
        $cases = self::cases();
        foreach($cases as $case){
            if(strtolower($case->name) === $name) return $case;
        }
        throw new \ValueError("$name is not a valid backed enum case for enum ". self::class);
    }

    private static function validate_text_lenght(int $max_lenght, string $text_type , ?string $val, ?string& $err_msg) : bool{
        if($val===null) return true; //only happen when isn't required and no default value
        if(mb_strlen($val, ENCODING_CHARSET) <= $max_lenght) return true;
        $err_msg = "The $text_type cannot exceed {$max_lenght} charactere";
        return false;
    }

    /**
     * @return list<array{
     * 'key':string,
     * 'instruction':bool,
     * }> Where: compare_fn is callable returns bool and receive sanitized value and validate using user provided function and second argument is referece to write error messague when user comparation function returns false;
     * The value passed in custom compare function alredy validate by type explicited.
     */
    public static function sanitization_rule() : array{
        return [
            [
                'key' => self::title->name,
                'instruction'=> new Input_ruleset(
                    Data_type::string,
                    validation_fn: fn(?string $val, ?string& $err_msg, ?int &$custom_err) => self::validate_text_lenght(MAX_TITLE_LENGTH, 'title', $val, $err_msg)
                )
            ],
            [
                'key'=> self::description->name,
                'instruction' => new Input_ruleset(
                    Data_type::string,
                    validation_fn: fn(?string $val, ?string& $err_msg, ?int &$custom_err) => self::validate_text_lenght(MAX_TITLE_LENGTH, 'title', $val, $err_msg)
                )
            ],
            [
                'key' => self::location_reference->name,
                'instruction' => new Input_ruleset(
                    Data_type::string,
                    false,
                    validation_fn: fn(?string $val, ?string& $err_msg, ?int &$custom_err) => self::validate_text_lenght(MAX_LOCATION_REFERENCE_LENGTH, 'location reference', $val, $err_msg)
                )
            ],
            [
                'key' => self::ang_x->name,
                'instruction' => new Input_ruleset(
                    Data_type::double,
                    validation_fn:fn(?float $val, ?string& $err_msg, ?int &$custom_err) => Validate_coordinate($val, $err_msg)
                ),
            ],
            [
                'key' => self::ang_x->name,
                'instruction' => new Input_ruleset(
                    Data_type::double,
                    validation_fn:fn(?float $val, ?string& $err_msg, ?int &$custom_err) =>Validate_coordinate($val, $err_msg)
                )
            ],
            [
                'key' => self::type->name,
                'instruction' => new Input_ruleset(
                    Data_type::int,
                    false,
                    1,
                    fn(int $val, ?string &$cond_failed_msg, ?int &$custom_err) => Complain_type::Validate($val, $cond_failed_msg)
                )
            ],
            [
                'key' => self::crop_option->name,
                'instruction' => new Input_ruleset(
                    Data_type::array,
                    false,
                    [],
                    sanitize_fn:function(array &$crop_option, ?string &$err_msg):bool{
                        $xy_sanitazion = new Input_ruleset(Data_type::int, false, 0);
                        $size_sanitazion = new Input_ruleset(Data_type::int, false);

                        $start_x = $crop_option['x']??null;
                        $start_y = $crop_option['y']??null;

                        $width = $crop_option['width']??null;
                        $height = $crop_option['height']??null;

                        $xy_sanitazion->execute($start_x,$err_msg);
                        $xy_sanitazion->execute($start_y,$err_msg);
                        $size_sanitazion->execute($width,$err_msg);
                        $size_sanitazion->execute($height,$err_msg);

                        $option = [
                            "start_x"=>$start_x,
                            "start_y"=>$start_y
                        ];

                        if($width !== null && $width>0) $option['crop_width'] = $width;
                        if($height !== null && $height>0) $option['crop_height'] = $height;

                        $crop_option = $option;
                        return true;
                    }
                )
            ]
        ];
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

    public static function From_router_code(int $err_code, Complain_data_fields $key) : Upload_err_code{
        return match($err_code){
            Input_ruleset::EMPTY_VALUE => self::empty_value,
            Input_ruleset::INVALID_TYPE => self::invalid_type,
            Input_ruleset::INVALID_VALUE => self::Get_INVALID_VALUE_err($key),
            Input_ruleset::SANITIZATION_FAILED => self::err_internal,
            default => self::unknown
        };
    }

    private static function Get_INVALID_VALUE_err(Complain_data_fields $key) : Upload_err_code{
        return match($key){
            Complain_data_fields::ang_x, Complain_data_fields::ang_y => self::invalid_coordinate,
            Complain_data_fields::title, Complain_data_fields::description, Complain_data_fields::location_reference => self::size,
            default => self::invalid_value
        };
    }

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

function Upload_complain(?Rate_limiter $IP_limiter, array $captured_args, array $processed_datas){
    $old_file = null;
    $current_internal_fpath = null;
    if(isset($_FILES[FILE_UPLOAD_FIELD_NAME]['size']) && $_FILES[FILE_UPLOAD_FIELD_NAME]['size']) {
        //send the first finish part if has upload file, else jump straight to database operation, which result only one json response and no new line
        Send_response(true,Upload_err_code::success);

        $finfo = Secure_file_upload(
            File_storation_path::Maps,
            [Avaible_extension::jpg, Avaible_extension::png, Avaible_extension::webp],
            $processed_datas['crop_option'],
            false,
            false
        );
        
        $old_file = $finfo['unlink_target'] ?? null;
        $current_internal_fpath = $finfo['current_fname'];
    }

    //alredy sanitized, so key always exist(can be null thought)

    $title = $processed_datas['title'];
    $desc = $processed_datas['description'];
    $location_ref = $processed_datas['location_reference'];

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

    $ang_x = $processed_datas['ang_x'];
    $ang_y = $processed_datas['ang_y'];


    //insert if location was not found and use that id from insertion, else select by using x and y
    $location_id = Upsert_location($ang_x,$ang_y,false,true);
    if($location_id === null) Send_response(false, Upload_err_code::register_coordinate, end:true);



    $complain_type = $processed_datas['type'];

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
    

    $ip = Get_IP_address_txt();
    
    Log_internal(
        new Error_code(Error_domain::upload, Resource_code::complain, Error_condition::success),
        Level::Info,
        "[IP-\"$ip\"] User(uid - $uid) updated new complain with id = [$complain_id]"
    );
    Send_response(true,Upload_err_code::success, end:true,internal_fname:$current_internal_fpath);
}

function Error_handler(int $code, string $key, string $err_msg, int $custom_err){
    $field = Complain_data_fields::TryGet_case($key);
    Send_response(false, Upload_err_code::From_router_code($code,$field), $field, $err_msg, end:true); exit();
}

/**
 * send response to front end. exit when end
 * @param Complain_data_fields|null $err_field if null, then response will exclude invalid_field.
 * @param bool $end if true, immedially exit and change some log message
*/
function Send_response(bool $success ,Upload_err_code $code , ?Complain_data_fields $err_field = null, string $extra_msg = '', bool $end = false, ?int $complain_id = null, ?string $internal_fname = null){
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

    $ip = Get_IP_address_txt();
    if(!$success) {
        if($err_field?->value > 0) $response['invalid_field'] = $err_field->value;

        $frontend_code = $info['frontend_code'];
        $response['err_code'] = $frontend_code->value;
        
        $full_msg = $desc === '' ? $extra_msg : "{$desc} | {$extra_msg}";
        if($full_msg === '') $full_msg = 'unkown error';
        Log_internal(
            new Error_code($type,Resource_code::complain,Error_condition::failed),
            Level::Info,
            "[IP-\"$ip\"] $full_msg"
        );
    }

    $has_file = isset($_FILES[FILE_UPLOAD_FIELD_NAME]['size']) && $_FILES[FILE_UPLOAD_FIELD_NAME]['size'] > 100;
    if($complain_id !==null) $response['complain_id'] = $complain_id;
    if($internal_fname !== null) $response['image_url'] = Generate_img_url(File_storation_path::Maps, $internal_fname);

    Send_mime_type(MIME['application']['json']);
    \http_response_code($http_code);

    $json = json_encode($response);
    if($success && $has_file) $json .= "\n";    
    echo $json;

    if($has_file) ob_flush();
    if($end) exit();
}