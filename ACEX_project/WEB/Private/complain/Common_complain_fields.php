<?php

namespace ACEX_project\WEB\Private\complain;

enum Complain_type: int{
    case defaults = 1;
    case dangerous = 2;
    case polution = 3;
    case sound = 4;
    case leak_sewage = 5;
    case eletric = 6;
    case road = 7;
    case others = 8; 

    public static function Validate(int $type, ?string &$err_msg) : bool{
        $found = self::tryFrom($type) !== null;
        if($found) return true;

        $err_msg = 'The value should between 1 to 8';
        return false;
    }
}

