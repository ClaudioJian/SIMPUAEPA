<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Error handle                                                                         |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Error;

use Exception;

    /**
     * Logs the error and performs an action according to its Log_level.
     * Every time log the info. You may need implement rate limit to avoid bload of log.
     * 
     * fatal   - Log the error, return HTTP 500 and terminate the request.
     * error   - Log the error and throw an exception.
     * @param Log_level $lvl fatal: end request | error: throw new Exception() | others: only log the message
     * @param string $data the data to send to browser when is fatal error
     */
    function Handle_error(Error_code $code,Log_level $lvl = Log_level::error,string $err_msg = "", string $data = "") : void{
        Log_internal($code,$lvl,$err_msg);

        switch($lvl){
            case Log_level::fatal :{
                http_response_code(500);
                if($data!=="") echo $data;
                exit();                
            }
            case Log_level::error :{
                throw new Exception();
            }
        }
    }


    /**
     * Register a message in the log system.
     *
     * Format:
     * [time] [level] domain.resource/action.condition -<<extra message>>
     *
     * Status: not implemented
     * @param Error_code $code
     * @param Log_level  $lvl
     * @param string     $err_msg
     */
    function Log_internal(Error_code $code,Log_level $lvl = Log_level::error,string $err_msg = ""){
        //day day/month/year time in milisec(hour:min:sec.ms)
        $curr_t = date('D d/M/Y H:i:s'). sprintf('.%03d', (int)((microtime(true) * 1000) % 1000));
        
        $extra_msg = $err_msg === '' ? '' : "- <<".$err_msg.">>";

        //log this error somewhere
        $log_msg = sprintf('[%s] [%s] %s %s',$curr_t,$lvl->name,(string)$code,$extra_msg);
        //not implanted yet
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
            new Error_Code(Error_domain::database,Resource_code::syntax,Error_condition::type),
            Log_level::fatal,
            'Invalid argument in '.$func_name.'(): Missing key "'.$key.'" in array of param'.$param_name
        );
    };

    function MyArray_check(mixed $target, string $msg):void{
        if(!is_array($target)){
            Handle_error(
                new Error_Code(Error_domain::database,Resource_code::syntax,Error_condition::type),
                Log_level::fatal,
                $msg
            );
        }
    };
?>