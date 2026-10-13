<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Error handle                                                                         |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Error;
    require_once __DIR__ ."/../Core/AppCommonVar.php";
    require_once __DIR__ ."/../Core/File_storation_map.php";

use ACEX_project\WEB\Private\Core\File_storation_path;
use Exception;
    use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
    use Monolog\Level;
    use Monolog\Logger;

    /**
     * Logs the error and performs an action according to its Log_level.
     * Every time log the info. You may need implement rate limit to avoid bload of log.
     * 
     * fatal   - Log the error, return HTTP 500 and terminate the request.
     * error   - Log the error and throw an exception.
     * @param Level $lvl Critical,Alert and Emergency: end request | error: throw new Exception() | others: only log the message
     * @param string $data the data to send to browser when is fatal error
     */
    function Handle_error(Error_code $code,Level $lvl = Level::Error,string $err_msg = "", string $data = "") : void{
        Log_internal($code,$lvl,$err_msg);

        switch($lvl){
            case Level::Alert:
            case Level::Emergency :
            case Level::Critical :{
                \http_response_code(500);
                if($data!=="") echo $data;
                exit();                
            }
            case Level::Error:{
                throw new Exception();
            }
        }
    }


    /**
     * Register a message in the log system.
     *
     * Format:
     * [time] [channel:level] <<domain - resource/action - condition>> message
     *
     * Status: not implemented
     * @param Error_code $code
     * @param Level  $lvl
     * @param string     $err_msg
     */
    function Log_internal(Error_code $code,Level $lvl = Level::Warning,string $err_msg = ""){
        //day day/month/year time in milisec(hour:min:sec.ms)
        $date_format = "'D d/M/Y H:i:s";

        $output_format = $err_msg !== "" ? "[%datetime%] [%channel%: %level_name%] <<%context.domain% %context.resource% %context.condition%>> %message%"
        : "[%datetime%] [%channel%: %level_name%] %context.domain% %context.resource% %context.condition%";

        $formater = new LineFormatter("$output_format\n", $date_format, false, true);

        //not implanted yet
        $log = new Logger("SIMUP");

        $log_fname = $code->Get_log_name();
        $file_path = File_storation_path::Error_logs->value . "/" . $log_fname;
        if(!file_exists($file_path)){
            file_put_contents($file_path,"Start to log\n\n");
        }

        $handler = $log_fname === ini_get("error_log") ? new ErrorLogHandler($file_path, $lvl) : new StreamHandler($file_path, $lvl);
        $handler->setFormatter($formater);

        $log->pushHandler($handler);

        // add records to the log
        $log->addRecord($lvl, $err_msg, ["domain"=>$code->type->name, "resource"=>$code->res->name, "condition"=>$code->detail->name]);

        match($lvl){
            Level::Alert,
            Level::Emergency,
            Level::Critical => $log->close(),
            default => null,
        };
    }

    /**
     * throw error if any param is null or empty string
     */
    function Throw_if_empty(array $all_params){
        foreach($all_params as $param){
            if($param === null || empty($param)) throw new Exception();
        }
    }

    function MyArray_missing_key(string $key, string $func_name, string $param_name):void{
        Handle_error(
            new Error_Code(Error_domain::database,Resource_code::invalidArgument,Error_condition::type),
            Level::Critical,
            "In $func_name(): Missing key \"$key\" in array of param $param_name"
        );
    };

    function MyArray_check(mixed $target, string $msg):void{
        if(!\is_array($target)){
            Handle_error(
                new Error_Code(Error_domain::database,Resource_code::invalidArgument,Error_condition::type),
                Level::Critical,
                $msg
            );
        }
    };
?>