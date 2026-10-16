<?php 


namespace ACEX_project\WEB\Private\Core\Security;
    require_once __DIR__ . '/../../Auth/session_manager.php';

    require_once __DIR__ .'/../../Error/Error_code.php';
    require_once __DIR__ .'/../../Error/Error_manager.php';
    use function ACEX_project\WEB\Private\Error\Handle_error;

use ACEX_project\WEB\Private\Core\File_storation_path;
use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Monolog\Level;

    require_once __DIR__ .'/../File_storation_map.php';


enum Bucket_type{
    case IP;
    public function Get_path(int $key) : string{
        $path = match ($this) {
            self::IP => File_storation_path::Rate_track_ip->value,
        };
    }
} 

class Rate_limiter{


}