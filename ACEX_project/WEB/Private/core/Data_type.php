<?php

namespace ACEX_project\WEB\Private\Core;

require_once __DIR__ . '/AppCommonVar.php';


enum Data_type{
    case string;
    case double;
    case int;
    case unsigned_int;
    case array;
    case bool;

    /**
     * filter and normalize in common way of data according the type. String or alredy is type can be validated else always false.
     * 
     * If data doesn't belong the type, the value will not be changed and will return false.
     * 
     * the normalization and validation is:
     * string -> mb_trim - is_string
     * int/unsigned -> convert to int/unsigned int - filter_var($data,FILTER_VALIDATE_INT)/ >= 0
     * double -> float conversion - is_numeric and is_finite
     * array -> is_array - nothing
     */
    public function filter(mixed &$data) : bool{
        $buffer = $data;
        if((\is_string($buffer) && mb_trim($buffer,encoding:ENCODING_CHARSET) === '') || $data === null) return false;
        switch($this){
            case self::string :
                if(!\is_string($data)) return false;
                \mb_trim($data,encoding:ENCODING_CHARSET);
                break;
            case self::unsigned_int:
            case self::int:
                $val = \filter_var($data,FILTER_VALIDATE_INT);
                if($val === false || ($this === self::unsigned_int && $val < 0)) return false;

                $data = $val;
                break;
            case self::double:
                if(!\is_numeric($data)) return false;
                $val = doubleval($data);

                if(!\is_finite($val)) return false;
                $data = $val;
                break;
            case self::array:
                return \is_array($data);
                
            case self::bool:
                if(\is_bool($data)) return true;
                if(\is_int($data)){
                    $data = $data !== 0;
                    return true;
                }

                $buffer = \mb_strtolower(mb_trim($data,encoding:ENCODING_CHARSET),ENCODING_CHARSET);

                if(\is_numeric($buffer)) {
                    $buffer = ((float)$buffer) !== 0.0;
                    return true;
                }

                if(\is_string($buffer)) {
                    $buffer = match($buffer){
                        'true'=>true, 
                        'false'=>false, 
                        default=>null
                    };
                    if($buffer === null) return false;
                    
                    $data = $buffer;
                    return true;                    
                }

                return true;
            default: return false;
        }
        return true;
    }
}