<?php

namespace ACEX_project\WEB\Private\complain;

require_once __DIR__ . "/../Core/AppCommonVar.php";

require_once __DIR__ . "/../Core/Security/Rate_limit_handler.php";

use ACEX_project\API\Input_ruleset;
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_comment_columns;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;
use ACEX_project\WEB\Private\Core\Data_type;
use ACEX_project\WEB\Private\Core\Security\Rate_limiter;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;

require_once __DIR__ . "/../Error/Error_code.php";
require_once __DIR__ . "/../Error/Error_manager.php";
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Database_simple_insert;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;
use function ACEX_project\WEB\Private\Core\Send_mime_type;
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
enum Post_comment_data_fields:int{
    case complain_id = 1;
    case description = 2;

    public static function TryGet_case(string $name) : Post_comment_data_fields{
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
                'key' => self::complain_id->name,
                'instruction'=> new Input_ruleset(Data_type::unsigned_int)
            ],
            [
                'key'=> self::description->name,
                'instruction' => new Input_ruleset(
                    Data_type::string,
                    validation_fn: fn(?string $val, ?string& $err_msg, ?int &$custom_err) => self::validate_text_lenght(MAX_COMMENT_LENGTH, 'description', $val, $err_msg)
                )
            ],
        ];
    }

    public static function Send_err_response(int $code, Post_comment_data_fields $field, string $err_msg) : never{
        switch($code){
            case Input_ruleset::EMPTY_VALUE:
                Upload_comment_err_code::empty_value->Send_response(extra_msg:$err_msg, only_extra_msg:true);
            case Input_ruleset::INVALID_TYPE:
                Upload_comment_err_code::invalid_value->Send_response(extra_msg:$err_msg, only_extra_msg:true);
            case Input_ruleset::INVALID_VALUE:
                Upload_comment_err_code::size->Send_response(extra_msg:$err_msg,only_extra_msg:true);
        }
        exit();
    }

    public static function Error_handler(int $code, string $key, string $err_msg, int $custom_err){
        $field = self::TryGet_case($key);
        self::Send_err_response($code,$field, $err_msg);
    }
}



enum Upload_comment_err_code : int{
    case success = 0;
    case empty_value = -1;
    case etag_wrong_version = -2;
    case err_internal = -3;
    case violation_policies_rules = -4;
    case inappropriate_content = -5;
    case unauthorized = -6;
    case target_not_found = -7;
    case size = -8;
    case invalid_value = -9;
    case unknown = -99;


    private static function map(int $http_code, string $desc, Error_domain $category =  Error_domain::request, array $headers = []){
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
    private function info(string $extra_info = '', ?Post_comment_data_fields $err_field = null) : array{
        $field = $err_field===null?'':"Field {$err_field->name} - ";
        //optional message
        $extra_msg = $extra_info===''?'':": {$extra_info}";
        return match($this){
            self::success => self::map(201,'Sucessfully upload comment'.$extra_msg),
            self::unknown => self::map(500,'Uknown error', Error_domain::server),
            self::empty_value=>self::map(400,"{$field}Empty value"),
            self::etag_wrong_version=>self::map(409, "ETag version mismatch. Expected to update resource version"),
            self::err_internal=>self::map(500, "Internal server error - $extra_info", Error_domain::server),
            self::violation_policies_rules=>self::map(400, "Uploaded content violates application policies or rules: $extra_info"),
            self::inappropriate_content=>self::map(422, "Uploaded content is inappropriate: $extra_info"),
            self::unauthorized=>self::map(403,"Current user have no permission{$extra_msg}"),
            self::target_not_found=>self::map(400, "{$field}references a non-existence records{$extra_msg}"),
            self::size=>self::map(413, 'Field '.Post_comment_data_fields::description->name . " - Content exceed ". MAX_LOCATION_SELECTION_AREA. ", but $extra_info lenght is uploaded"),
            self::invalid_value=>self::map(400,"{$field}invalid: $extra_info"),
        };
    }

    public static function Error_handler(int $code, string $key, string $err_msg, int $custom_err){
        $field = Post_comment_data_fields::TryGet_case($key);

        $code = match($code){
            Input_ruleset::EMPTY_VALUE => self::empty_value,
            Input_ruleset::SUCCESS=>self::tryFrom($custom_err),
            default => self::unknown
        };

        $code->Send_response($field, $err_msg); 
    }

    /**
     * Send Error json to client
     */
    public function Send_response(?Post_comment_data_fields $err_field = null, ?string $extra_msg = '', bool $only_extra_msg = false){
        $extra_msg ??= ''; 
        $ip = Get_IP_address_txt();

        $response = ['success'=>-1];
        $http_code = 400;

        $info = $this->info($extra_msg, $err_field);
       
        $type = $info['err_type'];
        
        $headers = $info['headers'];
        $http_code = $info['http_code'];


        foreach($headers as $header) \header($header);
        
        

        $full_msg = $this->Get_log_msg($err_field, $extra_msg, $only_extra_msg, $info);
        Log_internal(
            new Error_code($type,Resource_code::complain,Error_condition::failed),
            Level::Info,
            "[IP-\"$ip\"] $full_msg when trying upload comment"
        );

        $response['err_code'] = $this->value;
        Send_mime_type(MIME['application']['json']);
        \http_response_code($http_code);

        $json = json_encode($response);   
        echo $json;
        exit();
    }

    private function Get_log_msg(?Post_comment_data_fields $err_field, ?string $extra_msg, bool $only_extra_msg, array $info) : string{
        if($only_extra_msg && $extra_msg !== '' && $extra_msg !== null) return $extra_msg;

        $msg = $info['description'];
        return $msg;
    }
}




function Upload_comment(?Rate_limiter $IP_limiter, array $captured_args, array $processed_datas){
    //alredy sanitized, so key always exist(can be null thought)
    $desc = $processed_datas['description'];
    $complain_id = $processed_datas['complain_id'];

    //TODO: implement reply system
    $parent_id = null;
    //$parent_id = $processed_datas['parent_id']; 



    if(!Validate_content($desc, $reject_reason, $reject_type)) {
        $err = match($reject_type){
            1=>Upload_comment_err_code::violation_policies_rules,
            2=>Upload_comment_err_code::inappropriate_content,
            default=>Upload_err_code::unknown
        };
        $err->Send_response(Post_comment_data_fields::description, $reject_reason);
    }

    $uid = $_SESSION['uid'];    
    //while race condition still happen and is not best solution, this is only easiest solution 
    Exit_not_exist($complain_id, $uid, $parent_id);

    $comment_insert_column= [
        [
            'column' => tb_comment_columns::complain_id,
            'value' => [$complain_id]
        ],
        [
            'column' => tb_comment_columns::description,
            'value' => [$desc]
        ],
        [
            'column' => tb_comment_columns::user_id,
            'value'=> [$uid]
        ],
        /*
        [
            'column' => tb_comment_columns::parent_id,
            'value' => [$parent_id]
        ],
         */
    ];


    $insert_comment = [
        'table' => All_tables::comment,
        'insert'=> $comment_insert_column
    ];


    //db insertion
    $id_list = Database_simple_insert([$insert_comment], true, auto_exit:false);
    $comment_id = null;

    foreach($id_list?:[] as $comment_ids){
        if($comment_ids['table'] === All_tables::comment) {
            $comment_id = (int)$comment_ids['id'];
            break;
        }
    }
    
    if($id_list===false) Upload_comment_err_code::err_internal->Send_response(extra_msg:'Insertion failed, please check database error log');
    if($comment_id===null) Upload_comment_err_code::target_not_found->Send_response(Post_comment_data_fields::complain_id);

    Send_success_response($complain_id, $comment_id, $parent_id);
}

function Exit_not_exist(int $complain_id, int $uid, ?int $parent_id) : void{
    $values = [
        ':complain_id'=>$complain_id,
        ':uid'=>$uid
    ];

    $check_list = [
        "EXISTS (
            SELECT 1
            FROM " . All_tables::complain->name . "
            WHERE " . All_tables::complain->Get_primaryKey() . " = :complain_id
        ) AS complain_exist",
        "EXISTS (
            SELECT 1
            FROM " . All_tables::user->name . "
            WHERE " . All_tables::user->Get_primaryKey() . " = :uid
        ) AS uid_exist"
    ];

    if($parent_id !== null) {
        $check_list[] = 
            "EXISTS (
                SELECT 1
                FROM " . All_tables::comment->name . "
                WHERE " . All_tables::comment->Get_primaryKey() . " = :parent_id
            ) AS parent_exist";
        
        $values[':parent_id'] = $parent_id;
    }


    $query = 'SELECT ' . \implode(', ', $check_list) . ';';
    
    $conn = Connect_database();
    if($conn === null) Upload_comment_err_code::err_internal->Send_response(extra_msg:'Cannot connect to database');
    
    
    $smtm = $conn->prepare($query);
    if($smtm===false) Upload_comment_err_code::err_internal->Send_response(extra_msg:'Database server failed to prepare statement for select query');

    $smtm->execute($values);

    $row = $smtm->fetch(\PDO::FETCH_ASSOC);

    $complain_exist = $row['complain_exist'];
    $uid_exist = $row['uid_exist'];
    $parent_exist = $row['parent_exist'] ?? false;

    if(!$complain_exist) Upload_comment_err_code::target_not_found->Send_response(Post_comment_data_fields::complain_id, 'in table:"'.All_tables::complain->name . '"(primary key: "' . All_tables::complain->Get_primaryKey() . "\", received value:\"{$complain_id}\")");
    //if($parent_id !== null && !$parent_exist) Upload_comment_err_code::target_not_found->Send_response(Post_comment_data_fields::parent_id, 'in the current table(primary key: "' . All_tables::comment->Get_primaryKey() . "\", received value:\"{$parent_id}\")");

    if(!$uid_exist) {
        \http_response_code(401);
        Send_mime_type(MIME['application']['json']);
        echo json_encode(['success'=>-2]);
        exit();
    }
}


/**
 * send success response to front end. exit when end
*/
function Send_success_response(int $complain_id, int $comment_id, ?int $parent_id = null) : never{
    $response = ['complete'=>0, 'comment_id'=>$comment_id];
    $http_code = 201;

    $insert_to_comment = $parent_id === null ? '' :", replying to parent comment id = [$parent_id]";

    $ip = Get_IP_address_txt();
    Log_internal(
        new Error_code(Error_domain::request,Resource_code::complain,Error_condition::success),
        Level::Info,
        "[IP-\"$ip\"] Sucessfully upload comment with id = [$comment_id], into complain id = [$complain_id]{$insert_to_comment}"
    );


    Send_mime_type(MIME['application']['json']);
    \http_response_code($http_code);

    $json = json_encode($response);  
    echo $json;

    exit();
}