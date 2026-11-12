<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core Configuration & Global Constants                                               |
  |                                                                                                 |
  | Initializes environment variables, defines database connection constants, and provides          |
  | global success status enums for operations.                                                     |
  | you should require this file if need these constraint                                           |
  | WARNING: when require this, if env file is malconfigured, return http code 500                  |
  |                                                                                                 |
  |                                        Table of content                                         |
  | Sucess_code:enum                                                                                |
  | user_found, db_insert, db_update, db_delete, db_select                                          |
  | Constant per request: const                                                                     |
  | DB_NAME :string, DB_PASSWORD:string, HOST: string, SERVER_USER:string                           |
  | DB_PORT: int, SESSION_OBSOLETE_MAXLIFE:int, SESSION_ACTIVE_TIME: int                            |
  | CSRF_TOKEN_MAXLIFE: int, CSRF_TOKEN_VALIDATE_METHOD : array<string>                             |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core;

use ACEX_project\WEB\Private\Error\Error_code;
use ACEX_project\WEB\Private\Error\Error_condition;
use ACEX_project\WEB\Private\Error\Error_domain;
use ACEX_project\WEB\Private\Error\Resource_code;
use Monolog\Level;

use function ACEX_project\WEB\Private\Error\Handle_error;

    require_once __DIR__ . "/../Error/Error_manager.php";
    require_once __DIR__ . "/../Error/Error_code.php";


    /**
     * Simplify -> name and elapsed -> value
     */
    enum Time_unit : string{
        case ms = "millisecond";
        case sec = "second";
        case min = "minute";
        case h = "hour";
        case d = "day";
    }

    /**
     * Parse from input to $to format
     * @param string $input
     * @param Time_unit $to
     * @return float
     */
    function Parse_time(string $input,Time_unit $to = Time_unit::ms) : float{
        $time_size = \strtolower(trim($input));

        //?: mean no capture, separate group will be insert to $matches in order found matches

        $all_unit = [];
        foreach(Time_unit::cases() as $t_unit) {
            $all_unit[] = $t_unit->name;
            $all_unit[] = $t_unit->value;
        }

        //big to small
        usort($all_unit,fn(string $a, string $b) => \strlen($a) <=> \strlen($b));

        $optional_decimal = '(?:\.\d+)?';
        $size_unit = '(' . 
            \implode('|',\array_map(
            static fn(string $unit): string => \preg_quote($unit, '/'),$all_unit))
        .')';

        $pattern = '/^(' . "\\d+$optional_decimal" . ')'.
             $size_unit  .
            '$/i';
        
    
        //start with at least 1 digit in string(^\d+)
        //find 1 or 0 of . with following digit(\.?)
        //in end have ms/microsecond/...
        // in end may have 1 or 0 of b
        if(!\preg_match($pattern,$time_size,$matches)){
            Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::invalid),
                Level::Critical,
                "Invalid time format when parsing: $time_size"
            );
        }

        $size_unit = $matches[2] ?? '';
        $numeric = ($matches[1]) ?? 0;
        $numeric = (float)$numeric;

        $order = [Time_unit::ms->name=>86400000,Time_unit::sec->name=>86400,Time_unit::min->name=>1440,Time_unit::h->name=>24,Time_unit::d->name=>1];
        
        $scale = $order[$to->name]/$order[$size_unit];
        return $numeric * $scale;
    }