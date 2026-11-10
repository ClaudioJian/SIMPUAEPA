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
use function ACEX_project\WEB\Private\Api\database\Generate_value_name;
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


class Pagination_token{
    private mixed $previous_value;
    private int $previous_id;
    private ?string $token;

    public function __construct(?string $token, private Order_type $current_order)
    {
        self::validate($token, $current_order);
    }

    public function has_cursor(){
        return $this->token === null;
    }

    private function decode(string $token) : bool{
        $decode_base64 = base64_decode($token,true);

        if($decode_base64===false) self::invalid_base64($token);

        $data = json_decode($decode_base64, true);
        if($data===null || json_last_error() !== JSON_ERROR_NONE) self::invalid_json($token);

        $id_validator = new Input_ruleset(Data_type::unsigned_int, true, null);

        $id = $data['id'] ?? null;
        if(!$id_validator->execute($id, $err_msg)) Get_complain_Err::invalid_cursor->Send_response(extra_msg:"$err_msg for complain id");
        
        $val = $data['value']??null;
        if($val !== null) $val = mb_trim($val,encoding:ENCODING_CHARSET);
        if($val === null || $val === '') Get_complain_Err::invalid_cursor-> Send_response(extra_msg:"Empty value for pagination");
        
        $this->previous_id = $id;
        $this->previous_value = $val;
        return true;
    }


    private static function invalid_base64(string $token):never{
        Get_complain_Err::invalid_cursor->Send_response(extra_msg:"Cannot decode from \"$token\" to base64");
    }
    private static function invalid_json(string $token):never{
        Get_complain_Err::invalid_cursor->Send_response(extra_msg:"Cannot decode from \"$token\" to json");
    }

    private function validate(?string $token) : bool{
        if($token === null || mb_trim($token,encoding:ENCODING_CHARSET) === '') {
            $this->token = null;
            return true;
        }

        if(!$this->Validate_value($err_msg)) Get_complain_Err::invalid_cursor->Send_response(extra_msg:$err_msg);
    }

    private function Validate_value(?string &$err_msg) : bool{

    }

    public static function Generate(int $complain_id, mixed $value){

    }

    /**
     * 
     * @return
     */
    public function sql(int $order_type, mixed $new_val, int $id) : array{
        $column = Order_type::get_column($order_type);
        $direction = $order_type <0 ? '<' : '>';


    }
}



enum Filter_type : int{
    case none = 0;
    case by_type = 1;

    public static function Validate(int $val, ?string &$err_msg) : bool{
        $result = self::tryFrom($val);

        if($result !== null) return true;

        $err_msg = "$val is invalid filter type";

        return false;
    }

    public static function Validate_attachment(int $type, ?array $attachment, ?string &$err_msg) : Get_complain_Err{
        $case = self::tryFrom($type)?? self::by_type;
        $type = $case->value;
        
        /**
         * @var list<list<
         *      array{
         *          'key':string,
         *          'instruction':Input_ruleset
         *      }
         * >|null>
         */
        $attachments = [
            0 => null,
            1 => [
                [
                    'key'=>'type',
                    'instruction'=> new Input_ruleset(Data_type::unsigned_int, true, 
                    validation_fn: fn(int &$value, ?string &$err_msg)=>Complain_type::Validate($value, $err_msg)),
                ]
            ]
        ];

        //now list of array or null
        /**
         * @var list<
         *      array{
         *          'key':string,
         *          'instruction':Input_ruleset
         *      }
         * >
         */
        $target = $attachments[$type] ?? null;

        if($target === null) return Get_complain_Err::success;

        if($attachment === null || $attachment === []) {
            $err_msg = $case->name;
            return Get_complain_Err::attachment_missing;
        }  

        foreach($target as $fields){
            $key = $fields['key'];
            $instruction = $fields['instruction'];

            $data = $attachment[$key]??null;
            
            if(!$instruction->execute($data, $err_msg)) {
                $err_code = $instruction->current_error;
                if($err_code === Input_ruleset::EMPTY_VALUE) {
                    $err_msg = "Missing $key";
                    return Get_complain_Err::attachment_invalid;
                }
                return Get_complain_Err::attachment_invalid;
            }

            $attachment[$key] = $data;
        }
        return Get_complain_Err::success;
    }

    /**
     * sql to put in where clause.
     * 
     * @param mixed $value value to bind in where clause
     */
    public function sql(mixed $value) : array{
        return $this->generate_sql($value);
    } 

    /**
     * sql to put in where clause.
     * 
     * default none
     * 
     * @param mixed $value value to bind in where clause
     * @return array{
     *      sql:string,
     *      binded:array{
     *          ':filter_name':mixed
     *      }
     * } sql is to put inside where clause and binded is array to put in PDO::execute()
     */
    public static function get_sql(int $type, mixed $value) : array{
        return self::tryFrom($type)->generate_sql($value);
    }

    private function generate_sql(mixed $value): array{
        $col = match($this){
            self::by_type => tb_complain_columns::type,
            default => self::none
        };

        if($col === self::none) return [];

        $sql = '';
        $bind_name = ':filter_name';     
        
        $sql = "$col->name = $bind_name";
        return [
            'sql'=>$sql,
            'binded'=>[
                $bind_name=>$value
            ]
        ];
    }
}

enum Order_type : int{
    case by_priority_level = 0;
    case by_create_at = 1;
    case by_likes = 2;
    case by_rate = 3;
    case by_views = 4;
    case by_intensity_level = 5;

    public static function get_column(int $type) : tb_complain_columns{
        return self::tryFrom($type)?->internal_get_column() 
            ?? tb_complain_columns::priority_level;
    }

    public function column() : tb_complain_columns{return $this->internal_get_column();}

    public static function Validate(int $val, ?string &$err_msg) : bool{
        $result = self::tryFrom(abs($val));

        if($result !== null) return true;
        $err_msg = "$val is invalid ordering type";

        return false;
    }

    /**
     * default is by_priority_level, if isn't by_priority_level, then always insert priority level as order type
     * 
     * Every time insert complain id as order type
     */
    public function sql() : string{
        return $this->generate_sql();
    }

    /**
     * default is by_priority_level, if isn't by_priority_level, then always insert priority level as order type
     * 
     * Every time insert complain id as order type
     */
    public static function get_sql(int $type) : string{
        return self::tryFrom($type)->generate_sql($type>=0);
    }

    private function internal_get_column() : tb_complain_columns{
        return match($this){
            self::by_create_at=> tb_complain_columns::created_at,
            self::by_likes=>tb_complain_columns::like_count,
            //self::by_rate=>tb_complain_columns::rate,
            //self::by_views=>tb_complain_columns::views,
            default=> tb_complain_columns::priority_level
        };
    }

    private function generate_sql(bool $asc = true) : string{
        $direction = $asc ? 'ASC' : 'DESC';

        $col = $this->internal_get_column();

        return "$col->name $direction "
            . tb_complain_columns::id->name . "direction" .
            ($col === tb_complain_columns::priority_level) ? '' : tb_complain_columns::priority_level->name . ' direction'
        ;
    }
}


/**
 * list all field can be send by client
 * negative mean the value will not pass to client as invalid_field
 */
enum GET_Complain_fields{
    case next;
    case order;
    case filter;
    case ang_bbox;
    case attachment;
    case include_cursor;

    public static function TryGet_case(string $name) : GET_Complain_fields{
        $name = strtolower(trim($name));
        static $cases;
        $cases = self::cases();
        foreach($cases as $case){
            if(strtolower($case->name) === $name) return $case;
        }
        throw new \ValueError("$name is not a valid backed enum case for enum ". self::class);
    }

    private static function Validate_bbox_size(float $x1, float $y1, float $x2, float $y2, ?float &$area) : bool{
        $width = abs($x2 - $x1);
        $height = abs($y2 - $y1);
        $area = $width * $height;

        return true;
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
                'key' => self::next->name,
                'instruction'=> new Input_ruleset(
                    Data_type::string,
                    false
                )
            ],
            [
                'key'=> self::order->name,
                'instruction' => new Input_ruleset(
                    Data_type::int,
                    false,
                    Order_type::by_priority_level->value,
                    fn(?int $val, ?string& $err_msg, ?int &$custom_err) => Order_type::Validate($val, $err_msg)
                )
            ],
            [
                'key' => self::filter->name,
                'instruction' => new Input_ruleset(
                    Data_type::unsigned_int,
                    false,
                    Filter_type::none->value,
                    validation_fn: fn(?int $val, ?string& $err_msg, ?int &$custom_err) => Filter_type::Validate($val, $err_msg)
                )
            ],
            [
                'key' => self::ang_bbox->name,
                'instruction' => new Input_ruleset(
                    Data_type::array,
            
                    validation_fn:function(?array $bbox, ?string &$err_msg, ?int &$custom_err) : bool{
                        $valitador = new Input_ruleset(Data_type::double,validation_fn:fn(?float &$coordinate, ?string &$err_msg, ?int $custom_err)=>Validate_coordinate($coordinate,$err_msg));

                        foreach(['min_ang_x', 'min_ang_y', 'max_ang_x', 'max_ang_y'] as $key) {
                            $coordinate = $bbox[$key]??null;
                            if(!$valitador->execute($coordinate, $err_msg)) {
                                //not set or is empty
                                if($valitador->current_error === Input_ruleset::EMPTY_VALUE) {
                                    Get_complain_Err::invalid_select_area->Send_response(self::ang_bbox, "value $key is missing");
                                    return false;
                                }
                                
                                Get_complain_Err::invalid_select_area->Send_response(self::ang_bbox, "value $key is invalid - <<$err_msg>>");
                                return false;
                            }
                        }
                        if(!self::Validate_bbox_size($bbox['min_ang_x'], $bbox['min_ang_y'], $bbox['max_ang_x'], $bbox['max_ang_y'], $area)){
                            Get_complain_Err::select_area_too_big->Send_response(self::ang_bbox, $area);
                            return false;
                        }
                        return true;
                    }
                ),
            ],
            [
                'key' => self::attachment->name,
                'instruction' => new Input_ruleset(Data_type::array, false)
            ],
            [
                'key' => self::include_cursor->name,
                'instruction' => new Input_ruleset(Data_type::bool, false, false)
            ]
        ];
    }
}

enum Get_complain_Err :int{
    case success = 0;
    case invalid_cursor = -1;
    case coordinate_not_found = -2;
    case coordinate_invalid = -3;
    case attachment_missing = -4;
    case attachment_invalid = -5;
    case invalid_select_area = -6;
    case select_area_too_big = -7;
    case field_missing = -8;

    case unknown = -99;

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

    private static function map(int $http_code, string $desc, Error_domain $category =  Error_domain::request, array $headers = [], ){
        return [
            'http_code'=>$http_code,
            'description'=>$desc,
            'headers'=>$headers,
            'err_type'=>$category
        ];
    }
    /**
     * Get array of info about the error code
     * 
     * @return array{
     * 'http_code':int,
     * 'description':string,
     * 'headers':array,
     * 'err_type': Error_domain
     * } err_type use for decide type of error(mainly used for log)
     */
    private function info(string $extra_info = '', ?GET_Complain_fields $err_field = null) : array{
        $field = $err_field===null?'':"Field {$err_field->name} ";
        return match($this){
            self::success => self::map(201,'Sucessfully retrieve complain'),
            self::unknown=>self::map(500,'Uknown error', Error_domain::server),
            self::invalid_cursor=>self::map(400, 'Field '. GET_Complain_fields::next->name . " - invalid pagination cursor: $extra_info"),
            self::coordinate_not_found=>self::map(400, "Cannot find records in selected area - $extra_info"),
            self::coordinate_invalid=>self::map(400, 'Field '.GET_Complain_fields::ang_bbox->name . " - Invalid coordinate: $extra_info"),
            self::attachment_missing=>self::map(400, "Missing required attachment for current filter: $extra_info"),
            self::attachment_invalid=>self::map(400,"Attachment do not match current filter's structure: $extra_info"),
            self::invalid_select_area=>self::map(400, 'Field '.GET_Complain_fields::ang_bbox->name . " - invalid selected area: $extra_info"),
            self::select_area_too_big=>self::map(413, 'Field '.GET_Complain_fields::ang_bbox->name . " - selected area exceed limit of ". MAX_LOCATION_SELECTION_AREA. ", but $extra_info selected"),
            self::field_missing=>self::map(400,"$field - missing"),
        };
    }

    public static function Error_handler(int $code, string $key, string $err_msg, int $custom_err){
        $field = GET_Complain_fields::TryGet_case($key);

        $code = match($code){
            Input_ruleset::EMPTY_VALUE => self::field_missing,
            Input_ruleset::SUCCESS=>self::tryFrom($custom_err),
            default => self::unknown
        };

        $code->Send_response($field, $err_msg); 
    }

    /**
     * Send Error json to client
     */
    public function Send_response(?GET_Complain_fields $err_field = null, ?string $extra_msg = ''){
        $extra_msg ??= ''; 
        $ip = Get_IP_address_txt();

        $response = ['success'=>-1];
        $http_code = 400;

        $info = $this->info($extra_msg, $err_field);

        $desc = $info['description'];
        $type = $info['err_type'];
        
        $headers = $info['headers'];
        $http_code = $info['http_code'];

        foreach($headers as $header) \header($header);
        
        $full_msg = "$desc";
        if($err_field != null) $full_msg = "Field {$err_field->name} - $full_msg";
        $response['err_code'] = $this->value;

        Log_internal(
            new Error_code($type,Resource_code::complain,Error_condition::failed),
            Level::Info,
            "[IP-\"$ip\"] $full_msg when trying retrieve complains"
        );

        Send_mime_type(MIME['application']['json']);
        \http_response_code($http_code);

        $json = json_encode($response);   
        echo $json;
        exit();
    }
}


function GET_complain(?Rate_limiter $IP_limiter, array $captured_args, array $processed_datas){
    //alredy sanitized, so key always exist(can be null thought)

    //cursor for pagination encoded in base64 of json, can be null.
    $current_cursor = $processed_datas['next'];

    /**
     * @var int Order for complains. Possible values are defined in Order_type enum. Default type is assumed to 0(by_priority_level)
     * TODO: implement priority_level
     */
    $order_type = $processed_datas['order'];

    /**
     * @var int Filter for complains. Possible values are defined in Filter_type enum. Default type is assumed to 0(none)
     */
    $filter_type = $processed_datas['filter'];

    /**
     * required field to select area
     * @var array{
     *      min_ang_x: double,
     *      min_ang_y: double,
     *      max_ang_x: double,
     *      max_ang_y: double,
     * }
     */
    $select_area = $processed_datas['ang_bbox'];
    $filter_attachment = $processed_datas['attachment'];
    $include_cursor = $processed_datas['include_cursor'];

    /*
     The filter type is validate, if invalid, default will be Filter_type::none.
     If is none, then always sucess. Else will validate against pre-determined array to check & sanitize array $filter_attachment contain key and correct value(via custom function and type).
     */
    if(
        ($err_code = Filter_type::Validate_attachment($filter_type, $filter_attachment, $err_msg)) 
        !== Get_complain_Err::success
    ) $err_code->Send_response(extra_msg:$err_msg);

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
    if($location_id === null) Send_response(false, Upload_err_code::register_coordinate);



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
    Send_response(true,Upload_err_code::success,extra_msg:Selection_area_txt());
}


/**
 * send success response to front end. exit when end
 * @param Complain_data_fields|null $err_field if null, then response will exclude invalid_field.
 * @param bool $end if true, immedially exit and change some log message
*/
function Send_sucess_response(bool $success, Get_complain_Err $code , ?GET_Complain_fields $err_field = null, string $extra_msg = '', ?int $complain_id = null){
    $response = ['complete'=>0];
    $http_code = 200;

    $info = $code->info($extra_msg, $err_field);

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
        $full_msg = '';
        if($err_field != null) $full_msg = "Field {$err_field->name} - ";
        $response['err_code'] = $code->value;
        
        $full_msg .= $desc;
        if($full_msg === '') $full_msg = 'unkown error';
        Log_internal(
            new Error_code($type,Resource_code::complain,Error_condition::failed),
            Level::Info,
            "[IP-\"$ip\"] $full_msg when trying retrieve complains"
        );
    }

    if($complain_id !==null) $response['complain_id'] = $complain_id;

    Send_mime_type(MIME['application']['json']);
    \http_response_code($http_code);

    $json = json_encode($response);   
    echo $json;
    exit();
}