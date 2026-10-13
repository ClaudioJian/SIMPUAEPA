<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Error codes                                                                         |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Error;


    require_once __DIR__ ."/../Core/AppCommonVar.php";

    //here put some error code
    // . PHP_EOL is a constant in PHP that represents the end of a line.

    /**
     * Application error type, category fail
     * e.g : database, request
     */
    enum Error_domain: int
    {
        case general       = -1;
        case unknown       = -2;

        case database      = -3;
        case credential    = -4;
        case request       = -5;
        case email         = -6;
        case server        = -7;
        case configuration = -8;
        case object = -9;
        case child_proccess = -10;
    };

    /**
     * type of resource or action.
     * Resource: start with 1XX to 4XX
     * Action: start with 5XX to 9XX
     */
    enum Resource_code : int{
        case general          = -1;
        case unknown          = -2;
        case php              = -3;

        //resource
        case resource         = -10;

        case user             = -12;
        case email            = -13;
        case user_name        = -14;
        case password         = -15;
        case csrf             = -16;
        case credential       = -17;
        case env              = -18;
        case session          = -19;
        case db_query         = -20;
        case file             = -21;
        case directory        = -22;

        //action
        case action           = -50;

        case request          = -51;
        case db_table         = -52;
        case db_column        = -53;
        case db_row           = -54;
        case db_select        = -500;
        case db_insert        = -501;
        case connection       = -60;
        case invalidArgument  = -70;
    };

    /**
     * Detail about error
     * e.g: missing, don't match
     */
    enum Error_condition:int{
        case missing       = -1;
        case unknown       = -2;
        case invalid       = -3;
        case duplicated    = -4;
        case incorrect     = -5;
        
        case format        = -6;
        case type          = -7;
        case too_long      = -8;
        case too_weak      = -9;
        case surpass_limit = -10;
        case unsupported   = -11;
        case expired       = -12;
        case unauthorized  = -13;
        case unauthenticated=-14;
        case configuration = -15;
        case denied        = -16;
        case abnomaly      = -17;
        case failed        = -18;
        case quantity      = -19;
    }

    final class Error_code{
        public function __construct(public Error_domain $type,public Resource_code $res,public Error_condition $detail){}
        public function Get_log_name() : string{
            if($this->res === Resource_code::php) return ini_get("error_log") ?? LOG_FILE_NAME. ".log";
            return match($this->type) {
                Error_domain::configuration, Error_domain::unknown,
                Error_domain::object, Error_domain::server, 
                Error_domain::child_proccess, Error_domain::general 
                => LOG_FILE_NAME .".log",

                Error_domain::database => LOG_DB_NAME. ".log",
                Error_domain::credential, Error_domain::request, Error_domain::email => LOG_REQUEST_NAME. ".log",
                default => LOG_FILE_NAME. ".log"
            };
        }
    }