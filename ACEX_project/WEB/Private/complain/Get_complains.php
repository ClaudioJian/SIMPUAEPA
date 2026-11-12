<?php

namespace ACEX_project\WEB\Private\complain;

require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

use ACEX_project\API\Input_ruleset;
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\Query_type;
use ACEX_project\WEB\Private\Api\database\tb_complain_columns;
use ACEX_project\WEB\Private\Api\database\tb_location_columns;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
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

use function ACEX_project\WEB\Private\Api\database\Connect_database;

use function ACEX_project\WEB\Private\Api\database\Is_query_permitted;
use function ACEX_project\WEB\Private\Api\database\Limit_clause;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;
use function ACEX_project\WEB\Private\Core\Security\File_upload\Generate_img_url;

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

require_once __DIR__ . '/Common_complain_fields.php';


class Pagination_token{
    private mixed $previous_value;
    private int $previous_id;
    private ?string $token = null;

    public function get_log_msg():string{
        return "Complain id = \"{$this->previous_id}\" | Value = \"{$this->previous_value}\"";
    }

    public function get_prev_id(): int{return $this->previous_id;}
    public function get_prev_value(): mixed{return $this->previous_value;}

    public function __construct(?string $token, private int $current_order)
    {
        self::decode($token);
        self::validate($token);
    }

    public function has_cursor(){
        return $this->token !== null;
    }

    private function decode(?string $token) : bool{
        if($token === null || mb_trim($token,encoding:ENCODING_CHARSET) === '') {
            $this->token = null;
            return true;
        }

        $decode_base64 = base64_decode($token,true);

        if($decode_base64===false) self::invalid_base64($token);

        $data = json_decode($decode_base64, true);
        if($data===null || json_last_error() !== JSON_ERROR_NONE) self::invalid_json($token);

        $id = $data['id'] ?? null;
        if(!self::validate_id($id, $err_msg)) Get_complain_Err::invalid_cursor->Send_response(extra_msg:"$err_msg for complain id");
        
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
    private static function validate_id(mixed &$id, ?string &$err_msg): bool{
        $id_validator = new Input_ruleset(Data_type::unsigned_int, true, null);

        $success = $id_validator->execute($id, $err_msg);
        if(!$success && 
            $id_validator->current_error === Input_ruleset::INVALID_TYPE
        ) $err_msg = "$err_msg but value [$id] was given";
        

        return $success;
    }

    private function validate(?string $token) : bool{
        if($token === null || mb_trim($token,encoding:ENCODING_CHARSET) === '') {
            $this->token = null;
            return true;
        }

        if(!$this->Validate_value($err_msg, $this->previous_value)) Get_complain_Err::invalid_cursor->Send_response(extra_msg:"$err_msg for value");
        $this->token = $token;
        return true;
    }

    /**
     * Check if current value match order type value 
     */
    private function Validate_value(?string &$err_msg, mixed $value) : bool{
        $case = Order_type::tryFrom(abs($this->current_order));
        $validation = match($case){
            Order_type::by_create_at => new Input_ruleset(Data_type::string),
            Order_type::by_intensity_level,
            Order_type::by_likes,
            //Order_type::by_rate,
            //Order_type::by_views,
            =>new Input_ruleset(Data_type::unsigned_int,true, 0),
            default=> new Input_ruleset(Data_type::unsigned_int, true,0)
        };

        $success = $validation->execute($value, $err_msg);
        if(!$success && $validation->current_error === Input_ruleset::INVALID_TYPE) $err_msg = "$err_msg but [$value] was given";
        return $success;
    }

    /**
     * Encode to json then base64. 
     * Json will be array{
     *      id: int,
     *      value: mixed
     * }
     */
    public function encode(int $complain_id, mixed $value) : string{
        $fn = 'In function Pagination_token::encode()';
        if(!self::validate_id($complain_id, $err_msg)) Handle_error(
            new Error_code(Error_domain::server, Resource_code::invalidArgument, Error_condition::invalid),
            Level::Critical,
            "$fn: parameter \$complain_id is invalid [id: $complain_id - $err_msg]"
        );

        if(!$this->Validate_value($err_msg, $value)) Handle_error(
            new Error_code(Error_domain::server, Resource_code::invalidArgument, Error_condition::invalid),
            Level::Critical,
            "$fn: parameter \$value is invalid [value: $value - $err_msg]"
        );

        $json = json_encode(['id'=>$complain_id, 'value'=>$value]);
        $json_code = json_last_error();

        if($json===null || $json_code !== JSON_ERROR_NONE) {
            Handle_error(
                new Error_code(Error_domain::server, Resource_code::invalidArgument, Error_condition::invalid),
                Level::Critical,
                "$fn: Encode to json failed with code $json_code"
            );
        }

        return base64_encode($json);
    }

    /**
     * 
     * @return array{
     *      'sql':string,
     *      'binded':array{
     *          ':Tie_pagination_id':int,
     *          ':Pagination_value':mixed,
     *          ':Tie_pagination_value':mixed
     *      }
     * }
     */
    public function sql() : array{
        if(!$this->has_cursor()) return [
            'sql' => '',
            'binded' => []
        ];

        $new_val = $this->previous_value;

        $column = Order_type::tryFrom(abs($this->current_order))->column()->name;
        $direction = $this->current_order <0 ? '<' : '>';

        $bind_id_name = ':Tie_pagination_id';
        $bind_value_name = ':Pagination_value';

        $tb_target = All_tables::complain->name;

        $bind_tie_value_name = ':Tie_pagination_value';
        $tie_sql = "OR ({$tb_target}.". All_tables::complain->Get_primaryKey() ." $direction $bind_id_name AND {$tb_target}.{$column} = $bind_tie_value_name)";

        return [
            'sql'=>"{$tb_target}.$column $direction $bind_value_name $tie_sql",
            'binded'=>[
                $bind_value_name=>$new_val,
                $bind_id_name =>$this->previous_id,
                $bind_tie_value_name=>$new_val
            ]
        ];
    }
}



enum Filter_type : int{
    case none = 0;
    case by_type = 1;

    public static function get_key(int $type):string{
        $case = self::tryFrom($type)?? self::by_type;
        return match($case){
            self::by_type=>'type',
            default=>''
        };
    }

    public static function Validate(int $val, ?string &$err_msg) : bool{
        $result = self::tryFrom($val);

        if($result !== null) return true;

        $err_msg = "$val is invalid filter type";

        return false;
    }

    public static function Validate_attachment(int $type, ?array &$attachment, ?string &$err_msg) : Get_complain_Err{
        $case = self::tryFrom($type)?? self::none;
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
     * @return array{
     *      sql:string,
     *      binded:array{
     *          ':filter_name':mixed
     *      }
     * } sql is to put inside where clause and binded is array to put in PDO::execute()
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

        if($col === self::none) return [
            'sql'=>'',
            'binded'=>[]
        ];

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
        return self::tryFrom(abs($type))?->internal_get_column() 
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
        return self::tryFrom(abs($type))->generate_sql($type>=0);
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

        $tb_complain = All_tables::complain->name;

        
        $col = $this->internal_get_column();
        $main_col = "{$tb_complain}.{$col->name} $direction";
        $id_col = "{$tb_complain}.". tb_complain_columns::id->name . " $direction ";
        $sub_col = $col === tb_complain_columns::priority_level ? null : "$tb_complain." .tb_complain_columns::priority_level->name . " $direction";

        $all_col = [$main_col, $id_col];
        if($sub_col!==null) $all_col[] = $sub_col;

        return \implode(', ', $all_col);
        
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
    case quant;

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

        return $area <= MAX_LOCATION_SELECTION_AREA;
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
                                
                                Get_complain_Err::invalid_select_area->Send_response(self::ang_bbox, "value $key is invalid - <<$err_msg but [$coordinate] was given>>");
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
            ],
            [
                'key' => self::quant->name,
                'instruction' => new Input_ruleset(Data_type::unsigned_int, false, MAX_ITEM_PER_REQUEST, sanitize_fn:function(?int &$q, ?string &$err_msg){
                        $q = min($q,MAX_ITEM_PER_REQUEST);
                        return true;
                    }
                )
            ],
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
    $selected_quant = $processed_datas[GET_Complain_fields::quant->name];


    /*
     The filter type is validate, if invalid, default will be Filter_type::none.
     If is none, then always sucess. Else will validate against pre-determined array to check & sanitize array $filter_attachment contain key and correct value(via custom function and type).
     */
    if(
        ($err_code = Filter_type::Validate_attachment($filter_type, $filter_attachment, $err_msg)) 
        !== Get_complain_Err::success
    ) $err_code->Send_response(extra_msg:$err_msg);

    $x1 = $select_area['min_ang_x'];
    $y1 = $select_area['min_ang_y'];
    $x2 = $select_area['max_ang_x'];
    $y2 = $select_area['max_ang_y']; 

    $retrieve = new Retrieve_complains(
        $current_cursor, $order_type,
        $filter_type, $filter_attachment[Filter_type::get_key($filter_type)]??null,
        $selected_quant,
        $include_cursor,
        $x1,$y1,$x2,$y2
    );

    $retrieve->execute();
}

class Retrieve_complains{
    private const VALUE_NAME_X1 = ':ang_x1';
    private const VALUE_NAME_X2 = ':ang_x2';
    private const VALUE_NAME_Y1 = ':ang_y1';
    private const VALUE_NAME_Y2 = ':ang_y2';   

    private const ALIAS_x = 'x';
    private const ALIAS_y = 'y';
    private const ALIAS_uid = 'uid';
    private const ALIAS_profile_img = 'profile_img_url';
    
    private array $bind = [];
    private array $return_rows = [];
    private array $all_rows = [];

    private bool $updated = false;

    private string $first_cursor;


    public function __construct(
        private readonly ?string $current_cursor,
        private int $order_type,
        private readonly int $filter,
        private readonly mixed $filter_attachment_value,
        private readonly int $selected_quant,
        private readonly bool $include_cursor,
        private readonly float $x1, 
        private readonly float $y1, 
        private readonly float $x2, 
        private readonly float $y2
    )
    {
        self::Exit_if_not_permitted();
    }


    public function execute(){
        $conn = null;
        try{
            $conn = Connect_database();

            if($conn===null) Handle_error(
                new Error_code(Error_domain::database, Resource_code::connection,Error_condition::failed),
                Level::Critical,
                'Cannot connect to database while trying retrieve complains'
            );

            $select_area = $this->Select_area_sql();

            $pagination_token = new Pagination_token($this->current_cursor,$this->order_type);
            $pagination_condition = $pagination_token->sql();

            $filter_array = Filter_type::get_sql($this->filter, $this->filter_attachment_value);
            $order = Order_type::get_sql($this->order_type);

            $condition_arr = [$select_area];
            if($filter_array['sql']!=='') $condition_arr[] = $filter_array['sql'];
            if($pagination_condition['sql']!=='') $condition_arr[] = $pagination_condition['sql'];

            $condition = \implode(' AND ', 
                array_map(fn(string $cond)=> "($cond)",$condition_arr)
            );

            //limit+1 to detect
            $sql = Limit_clause(Query_type::select, 
                $this->Select_cols() .
                ' FROM '. $this->Select_tables() .
                " WHERE {$condition} ORDER BY {$order}", $this->selected_quant+1) . ';';

            $smtm = $conn->prepare($sql);
            if($smtm===false) throw new \Exception('Database server failed to prepare statement for select query');

            $this->bind = [...$this->bind, ...$pagination_condition['binded'], ...$filter_array['binded']];

            $smtm->execute($this->bind);

        

            $cursor_col = Order_type::get_column($this->order_type)->name;

            $row_count = 0;
            $next_cursor = false;
            do{
                $row = $smtm->fetch(\PDO::FETCH_ASSOC);
                $next_cursor = $this->Parse_db_rows($row_count, $row, $pagination_token);
            }while($next_cursor===null);

            if($row_count===0) Get_complain_Err::coordinate_not_found->Send_response(extra_msg:$this->selection_area_text() . ' with cursor - ['. $pagination_token->get_log_msg().']');

            $last_row = $this->all_rows[$row_count-1];
            $first_row = $this->all_rows[0];

            $this->updated = $pagination_token->has_cursor() && $first_row[self::ALIAS_uid] !== $pagination_token->get_prev_id() && $first_row[$cursor_col] !== $pagination_token->get_prev_value();

            $last_cursor = !$next_cursor ? null : $pagination_token->encode($last_row['complain_id'], $last_row[$cursor_col]);
            $this->first_cursor = $pagination_token->encode($first_row['complain_id'], $first_row[$cursor_col]);

            $this->Send_response($last_cursor);
        }catch(\Throwable $e){
            if($conn instanceof \PDO && $conn->inTransaction()) $conn->rollBack();
            Log_internal(
                new Error_code(Error_domain::database, Resource_code::db_query, Error_condition::unknown),
                Level::Error,
                'In Retrieve_complains::execute() failed: ' .$e->getMessage()
            );
            \http_response_code(500);
            exit();
        }
    }

    private function Send_response(?string $last_cursor) : never{
        $ip = Get_IP_address_txt();
        $attachment = $this->return_rows;

        Log_internal(
            new Error_code(Error_domain::request,Resource_code::complain,Error_condition::success),
            Level::Info,
            "[IP-\"$ip\"] retrieve complains in area ". $this->selection_area_text()
        );
        
        $response = [
            'success'=>0,
            'updated'=>$this->updated?0:1,
            'attachment'=>$attachment,
            'first_cursor'=>$this->first_cursor
        ];
        if($last_cursor !== null) $response['last_cursor'] = $last_cursor;

        Send_mime_type(MIME['application']['json']);
        \http_response_code(200);

        $json = json_encode($response);   
        echo $json;
        exit();
    }

    /**
     * @return ?bool indicate has next cursor or no if is bool. if is null mean still have row.
     */
    private function Parse_db_rows(int &$row_count, array|false $row, Pagination_token $token) : ?bool{
        if($row===false) return false;
        if($row_count >= $this->selected_quant) return true;

        //table and cols names
        $id_col_name = All_tables::complain->Get_primaryKey();
        $cursor_col = Order_type::get_column($this->order_type)->name;

        $create_at_col = tb_complain_columns::created_at->name;
        $title_col = tb_complain_columns::title->name;
        $desc_col = tb_complain_columns::description->name;
        $loc_ref_col = tb_complain_columns::location_reference->name;
        $type_col = tb_complain_columns::type->name;
        $img_url_col = tb_complain_columns::image_name->name;
        $likes_col = tb_complain_columns::like_count->name;
        $dislikes_col = tb_complain_columns::dislike_count->name;
        $priority_col = tb_complain_columns::priority_level->name;


        $insert_to = $internal = [];


        $curr_id = $row[$id_col_name];
        $profile_url = $row[self::ALIAS_profile_img] ?? null;
        $location_reference = $row[$loc_ref_col]??null;
        $map_img_url = $row[$img_url_col]??null;

        $insert_to['uid'] = $row[self::ALIAS_uid];

        if($profile_url!==null) $insert_to['profile_url'] = Generate_img_url(File_storation_path::profiles, $profile_url);
        if($location_reference!==null) $insert_to['location_reference'] = $location_reference;
        if($map_img_url!==null) $insert_to['image_url'] = Generate_img_url(File_storation_path::Maps, $map_img_url);
        if($this->include_cursor) $insert_to['last_cursor'] = $token->encode($curr_id, $row[$cursor_col]);

        $insert_to['complain_id'] = $curr_id;
        $insert_to['created_at'] = $row[$create_at_col];
        $insert_to['title'] = $row[$title_col];
        $insert_to['description'] = $row[$desc_col];
        $insert_to['type'] = $row[$type_col];
        $insert_to['likes'] = $row[$likes_col];
        $insert_to['dislikes'] = $row[$dislikes_col];
        $insert_to['ang_x'] = $row[self::ALIAS_x];
        $insert_to['ang_y'] = $row[self::ALIAS_y];


        $internal[$priority_col] = $row[$priority_col];

        $this->return_rows[$row_count] = $insert_to;
        $this->all_rows[$row_count] = [...$insert_to, ...$internal];
        
        $row_count++;
        return null;
    }

    private function Select_cols():string{
        $tb_complain = All_tables::complain->name;
        $tb_location = All_tables::location->name;
        $tb_user = All_tables::user->name;
        $ang_x_col = tb_location_columns::ang_x->name;
        $ang_y_col = tb_location_columns::ang_y->name;

        $uid_col = All_tables::user->Get_primaryKey();
        $img_url = tb_user_columns::profile_image_name->name;

        return "{$tb_complain}.*,
         {$tb_location}.{$ang_x_col} AS ". self::ALIAS_x.",
         {$tb_location}.{$ang_y_col} AS ". self::ALIAS_y.", 
         {$tb_user}.{$uid_col} AS ". self::ALIAS_uid . ", {$tb_user}.{$img_url} AS ". self::ALIAS_profile_img ;
    }

    private function Select_tables():string{
        $tb_complain = All_tables::complain->name;
        $tb_location = All_tables::location->name;
        $tb_user = All_tables::user->name;

        $complain_location_id_col = tb_complain_columns::location_id->name;
        $location_id_col = All_tables::location->Get_primaryKey();

        $uid_col = All_tables::user->Get_primaryKey();
        $complain_uid = tb_complain_columns::user_id->name;

        return "{$tb_complain}
         INNER JOIN {$tb_location} ON
            {$tb_complain}.{$complain_location_id_col} = {$tb_location}.{$location_id_col}
         INNER JOIN {$tb_user} ON
            {$tb_complain}.{$complain_uid} = {$tb_user}.{$uid_col}";
    }

    private function selection_area_text():string{return Selection_area_txt($this->x1,$this->y1,$this->x2,$this->y2);}

    private function Select_area_sql() : string{
        $location_tb_name = All_tables::location->name;

        $ang_x_col = tb_location_columns::ang_x->name;
        $ang_y_col = tb_location_columns::ang_y->name;

        $this->bind[self::VALUE_NAME_X1] = $this->x1;
        $this->bind[self::VALUE_NAME_X2] = $this->x2;
        $this->bind[self::VALUE_NAME_Y1] = $this->y1;
        $this->bind[self::VALUE_NAME_Y2] = $this->y2;

        return "
                {$location_tb_name}.{$ang_x_col} BETWEEN ". self::VALUE_NAME_X1 .' AND '. self::VALUE_NAME_X2 ."
                AND {$location_tb_name}.{$ang_y_col} BETWEEN ". self::VALUE_NAME_Y1 . ' AND ' . self::VALUE_NAME_Y2 .'
            '
        ;
    }

    private static function Exit_if_not_permitted():void{
        $location_access = Is_query_permitted(HOST, [Query_type::select],All_tables::location);
        $complain_access = Is_query_permitted(HOST, [Query_type::select],All_tables::complain);

        if(!$location_access || !$complain_access){
            $denied_table = [];
            if(!$location_access) $denied_table[] = All_tables::location;
            if(!$complain_access) $denied_table[] = All_tables::complain;

            Handle_error(
                    new Error_code(Error_domain::database,Resource_code::db_select,Error_condition::unauthorized),
                    Level::Critical,
                    'Get complains - denied table: ' . \implode(' ', $denied_table). ' | user:'. HOST
            );
        }
    }
}