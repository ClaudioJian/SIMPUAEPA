<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security\File_upload;


use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Monolog\Level;


    require_once __DIR__ . "/../../MIME_type.php";
    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";
    require_once __DIR__ . "/../../File_storation_map.php";
    require_once __DIR__ . "/File_upload_error.php";
    
    use ACEX_project\WEB\Private\Core\Avaible_extension;
    use ACEX_project\WEB\Private\Core\File_storation_path;

    use function ACEX_project\WEB\Private\Error\Handle_error;






    function Strip_all_extension(string $fname): string{
        $dotpos = \strrpos($fname,".");
        if($dotpos === false) return $fname;
        return \trim(\substr($fname,0, $dotpos));
    }

    /**
     * Convert a byte-size string into byte. Support bytes, kb, megabyte(above this will have unexpected result in 2GB).
     * Accept format:
     * - Integer or decimal without unit, interpreted as byte.
     * - value with the suffix "b" or "byte"(e.g.,15b, 15byte).
     * - value with suffix "m" or "mb"
     * - value with suffix "k" or "kb"
     * 
     * Units are case-insentive. 1024 as conversion base.
     * @param string $byte_size byte-size value to be parsed
     * @return int equivalent size in byte
     */
    function Parse_byte(string $byte_size) : int{
        $byte_size = \strtolower(trim($byte_size));

        //?: mean no capture, separate group will be insert to $matches in order found matches

        $optional_decimal = '(?:\.\d+)?';
        $optional_size_unit = '(?:([km])?b?|byte|b)';
        $pattern = '/^(' . "\\d+$optional_decimal" . ')'.
            '(?:'. $optional_size_unit . ')?' .
            '$/i';
        
    
        //start with at least 1 digit in string(^\d+)
        //find 1 or 0 of . with following digit(\.?)
        //in end have k/b/m
        // in end may have 1 or 0 of b
        if(!\preg_match($pattern,$byte_size,$matches)){
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::invalid),
                Level::Critical,
                "Invalid byte size when parsing byte: $byte_size"
            );
        }

        $size_unit = $matches[2] ?? 'b';
        $numeric = ($matches[1]) ?? 0;
        $numeric = \intval($numeric);

        //byte, kb, mb
        $exponent = match($size_unit){
            'b'=>0,
            'k'=>1,
            'm'=>2,
            default => 0,
        };

        return $numeric * \pow(1024,$exponent);
    }




    /**
     * Move the current uploaded file from temp location to correct location, modify the name by param $name or by unique id.
     * If name is longer than 250 char or is empty
     * @param string $from absolute path if file will be moved
     * @param string $name all extension will be stripped(any after .)
     * @param bool $internal_move if false or not set, then will check is uploaded via http
     * @return bool|string full path name($to/$name.ext) when sucess else false
     */
    function Safe_file_store(File_storation_path $to,Avaible_extension $ext,string $name,string $from = "",bool $internal_move = false) : bool|string{
        if($from === '') $from = $_FILES[FILE_UPLOAD_FIELD_NAME]['tmp_name'];
        $name = Strip_all_extension(\basename($name));
        if(empty($name) || \strlen($name)>=250) $name = \uniqid();
        $dest = "$to->value/$name.$ext->name";


        $success = $internal_move ? \rename($from,$dest) : \move_uploaded_file($from,$dest);
        if(\is_file($from)) \unlink($from);

        return $success===false ? false:$dest;
    }