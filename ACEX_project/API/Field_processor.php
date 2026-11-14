<?php 

namespace ACEX_project\API;

use ACEX_project\WEB\Private\Core\Data_type;
use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Closure;
use InvalidArgumentException;
use Monolog\Level;

use function ACEX_project\WEB\Private\Core\Security\Get_request_body;
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;
use function ACEX_project\WEB\Private\Error\MyArray_check;
use function ACEX_project\WEB\Private\Error\MyArray_missing_key;

require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php"; 
require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";

require_once __DIR__ . "/../WEB/Private/Core/Data_type.php";
require_once __DIR__ . "/../WEB/Private/Error/Error_code.php";
require_once __DIR__ . "/../WEB/Private/Error/Error_manager.php";


Class Input_ruleset{
    private ?Closure $validation_fn = null;
    private ?Closure $sanitize_fn = null;
    private readonly mixed $default;


    public const SUCCESS = 0;
    public const EMPTY_VALUE = 1;
    public const INVALID_TYPE = 2;
    public const SANITIZATION_FAILED = 3;
    public const INVALID_VALUE = 4;

    public int $current_error = 0;
    public int $custom_err = 0;

    /**
     * @param Data_type $type type of data like string
     * @param bool $required check if the value isn't send and have rule:
     *     - If the value isn't required and is empty, then use default value else insert null
     *     - If is required but no default, false will be returned.
     * @param mixed $default value to used when invalid field doesn't exist.
     * @param null|callable $validation_fn contains 2 parameters and returns bool:
     *      - sanitized value by reference, can be null if not required and default doesn't exist.
     *      - error messague when user comparation function returns false
     *      - int custom error by reference
     * @param null|callable $sanitize_fn contain 2 paramenters and return bool:
     *      - raw value normalizad to type by reference. can be null if not required and default doesn't exist.
     *      - error messague when sanitize is not possible
     *      - null|int custom error by reference
     * @throws InvalidArgumentException if default value cannot be converted to type inserted
     */
    public function __construct(
        public readonly Data_type $type,
        public readonly bool $required = true,
        mixed $default = null,
        ?callable $validation_fn = null,
        ?callable $sanitize_fn = null,
    ){
        $this->validation_fn = $validation_fn !== null ? Closure::fromCallable($validation_fn) : null;
        $this->sanitize_fn = $sanitize_fn !== null ? Closure::fromCallable($sanitize_fn) : null;

        $err_msg = null;
        $custom_err = null;
        if($default !== null){
            if(!$type->filter($default)) throw new InvalidArgumentException("Cannot convert default data into {$this->type->name}");
            if($sanitize_fn !== null && !($sanitize_fn)($default, $err_msg, $custom_err)) throw new InvalidArgumentException("Cannot sanitize default value: {$err_msg}");
            if($validation_fn !== null && !($validation_fn)($default, $err_msg, $custom_err)) throw new InvalidArgumentException("Default value doesn't meet validation condition: [{$err_msg}]");            
        }
       

        $this->default = $default;
    }

    /**
     * execute the instruction.
     * 
     * if error happens, the message will be writeen and $this->current_error will be set to one of:
     *  *    EMPTY_VALUE = 1
     *  *    INVALID_TYPE = 2
     *  *    SANITAZION_FAILED = 3
     *  *    INVALID_VALUE = 4
     * if the user validation write into custom err, then $this->custom_err will be set to
     * @param null|string $err_msg message to written when violate ruleset
     * @param mixed $data if the instruction sucess then the data will be overwritten by thes if sucess:
     * 1. will be sanitized and normalized to type and suces;
     * 2; sanitized to user sanitize rule, if applicable, else try use default value;
     * 3. validate to user compare_fn, if applicable and sucess;
     */
    public function execute(mixed &$data, ?string &$err_msg) : bool{
        $this->current_error = self::SUCCESS;
        $this->custom_err = 0;
        $err_msg = null;

        return $this->Type_check($data, $err_msg) &&
            $this->Apply_user_sanitization($data, $err_msg) &&
            $this->Apply_user_compare_fn($data, $err_msg);
    }

    /**
     * If user sanitazion_fn return false, will try insert default value
     */
    private function Apply_user_sanitization(mixed &$data, ?string &$err_msg) : bool{
        if($this->sanitize_fn === null) return true;

        $custom_err = 0;
        if(!($this->sanitize_fn)($data, $err_msg, $custom_err)) {
            $default_avaible = $this->Apply_default($data);

            
            if(!$default_avaible) {
                $condition = $err_msg;
                $this->custom_err = $custom_err;
                $this->Set_error(self::SANITIZATION_FAILED , $err_msg, $condition);
            }

            return $default_avaible;
        }

        return true;
    }

    /**
     *   if empty, try use default value
     *   else convert to desired type and normalization, if failed try use default value
     *   if default value is null, write to err_msg and set $this->current_error to:
     *  *    EMPTY_VALUE = 1
     *  *    INVALID_VALUE = 2
     */
    private function Type_check(mixed &$data, ?string &$err_msg) : bool{
        $d = \is_string($data) ? mb_trim($data, encoding:ENCODING_CHARSET): $data;
        $data_empty = $d === '' || $d === null;
        $data_invalid = $data_empty || !$this->type->filter($data);

        if(!$data_invalid && !$data_empty) return true;

        if(!$this->Apply_default($data)) return $this->Set_error($data_empty ? self::EMPTY_VALUE : self::INVALID_TYPE, $err_msg);

        return true;
    }

    /**
     * @return false if default is null
     * @return true if default is applied
     */
    private function Apply_default(mixed &$data) : bool{
        if(!$this->required || $this->default !== null) {
            $data = $this->default;
            return true;
        }
        return false;
    }

    /**
     * Check if the user compare function return true, else write to error message
     */
    private function Apply_user_compare_fn(mixed &$data, ?string &$err_msg):bool{
        if($this->validation_fn === null) return true;

        $custom_err = 0;
        if(!($this->validation_fn)($data, $err_msg, $custom_err)) {
            if($this->Apply_default($data)) return true;
            
            $condition = $err_msg;
            $this->custom_err = $custom_err;
            return $this->Set_error(self::INVALID_VALUE, $err_msg, $condition);
        }
        return true;
    }

    private function Set_error(int $error_code, ?string &$err_msg, ?string $condition = null) : bool{
        $condition ??= 'unkown';

        $err_msg = match($error_code){
            self::EMPTY_VALUE => "Required field is missing and has no default value",
            self::INVALID_TYPE => "Invalid type. Expected {$this->type->name} and has no default value",
            self::SANITIZATION_FAILED  => "Sanitize error - [{$condition}] | Default data - NONE",
            self::INVALID_VALUE => "Validation error - [{$condition}] | Default data - NONE",
            default => throw new InvalidArgumentException("Invalid error code: [$error_code]")
        };
        $this->current_error = $error_code;
        return false;
    }
}


    /**
     * Convert type, sanitize value and validates input values from frontend.
     * @param list<array{
     *      key : string,
     *      instruction : Input_ruleset|false 
     * }> $instructions list of instructions used to process data.
     * Only field explicitly listed in this array will be processed.
     * If a field is present in the client submitted data but not present in this list here, it will be ignored
     *  - key: field name
     *  - instruction: ruleset to convert type, sanitize value and validates. 
     *  if set to false, the field will be skipped without throwing an error. This indicates that caller acknowledge the field's existence but instends to handle it separately.
     * Otherwise, error will be thrown if field cannot be processated.
     * 
     * Important: All data[key] not passed in list isn't processed at all. Avoid to use unexpected values.
     * 
     * @param array $datas The output data after all values have been converted and sanitized(or use default value).
     * The structure follows the format returned by function `Get_request_body()`. 
     * If returned value is false, will be assigned: 
     * array{
     * 'key':string,
     * 'code':int
     * }
     * - key is the field name
     * - code is int defined in class `Input_ruleset` indicating the type of error
     * 
     * @param string $err_msg If an error occurs, this parameter will contain an error message describing the reason of fairule, using the following format:
     *   * Field "name" is invalid because ["reason"]
     * @param Closure $invalid_field_handler function to be called without execute `$action` if processing the user data fails using the `$ruleset` provided. This function receives:
     * - int: error code defined by class `Input_ruleset`
     * - string: field name
     * - string: error message generated by `Input_ruleset`
     * - int custom error code
     */
    function Process_client_fields(array $instructions, ?string &$err_msg, ?array &$datas = null, ?Closure $invalid_field_handler = null) : bool
    {
        $raw_data = Get_request_body();

        foreach($instructions as $instruction){
            MyArray_check($instruction, 'instructions','Input_processor::__construct()');
            if(!array_key_exists('key', $instruction)) MyArray_missing_key('key','Input_processor::__construct()','array from list $instructions');
            if(!array_key_exists('instruction', $instruction)) MyArray_missing_key('instruction','Input_processor::__construct()','array from list $instructions');

            $ruleset = $instruction['instruction'];
            if($ruleset === false) continue;

            if(!$ruleset instanceof Input_ruleset) {
                Handle_error(
                    new Error_code(Error_domain::server, Resource_code::invalidArgument, Error_condition::type),
                    Level::Critical,
                    'In function Process_client_fields(): array["instruction"] in $instruction key must be object `Input_ruleset`'
                );
            }

            $key = $instruction['key'];
            $target_data = $raw_data[$key] ?? null;

            if(!$ruleset->execute($target_data, $err_msg)) {
                $err_msg ??= 'unknown';
                $err_msg = "Field \"{$key}\": [{$err_msg}]";

                $datas = [
                    'code'=>$ruleset->current_error,
                    'key'=>$key,
                    'custom_code'=>$ruleset->custom_err
                ];

                if($invalid_field_handler !== null) ($invalid_field_handler)($ruleset->current_error, $key, $err_msg, $ruleset->custom_err);
                return false;
            }
            $raw_data[$key] = $target_data;
        }

        $datas = $raw_data;
        return true;
    }
