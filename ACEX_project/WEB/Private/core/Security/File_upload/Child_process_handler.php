<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | File upload validation                                                                          |
  |                                                                                                 |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core\Security\File_upload; 
    use \resource;

    require_once __DIR__ . "/../../../Error/Error_code.php";
    require_once __DIR__ . "/../../../Error/Error_manager.php";

use InvalidArgumentException;
use RuntimeException;
use Throwable;

    require_once __DIR__ . "/../../MIME_type.php";

class Pipe{
    /** signal for stop write/read */
    private bool $active = true;
    private string $buffer = "";
    private string $data = "";
    private int $consumed = 0;
    private int $rconsumed = 0;

    /**
    * @param mixed $pipe resource of pipe from proc_open or any valid file pointer like socket
    * @param int $mode 0 write, 1 read, 2 read(stderr)
    * @param string $mode mode to open pipe, e.g r,w,a,b or combined like rb
    */
    public function __construct(private mixed $pipe,private int $mode){
        if(!\is_resource($pipe))
        throw new InvalidArgumentException('Invalid Argument Exception in Pipe::__construct(): param $pipe isnt resource');

        if($mode <0 || $mode >2) throw new InvalidArgumentException('Invalid Argument Exception in Pipe.__construct(): param $mode isnt valid std channel');
        $this->pipe = $pipe;
        $this->mode = $mode;

        \stream_set_blocking($this->pipe,false);
    }

    /**
    * Enable/disable the current operation
    */
    public function continue(bool $continue = true) : void {$this->active = $continue;}
    public function close() : bool {return fclose($this->pipe);}

    /**
     * Read data from the current pipe until a complete message is received then passes the message to provided callback.
     * A message is considered as complete when delimiter `$end` is found. The delimiter is removed from data then pass as argument to user function.
     * 
     * Tryies to read until complete message or pipe return no data.
     * @param callable $user_function Callback that receives the complete message without delimiter. If this returns false, then result of this function will also be false.
     * @return true if `$user_function` runs
     * @return false if no data in pipe or `$user_function` returns false
     */
    public function Read_until_then(callable $user_function,?int $sec,?int $ms = null,?int $max_len = 1024,string $end = "\n") : bool{
        while(true){
            $raw_data = $this->read($sec,$ms,$max_len,$end);
            if($raw_data === false) return false;

            if (!\str_ends_with($raw_data, $end)) continue;

            $raw_data = \substr($raw_data, 0, -\strlen($end));
            

            if($user_function($raw_data) === false) return false;
            return true;
        }
    }

    /**
     * Clean buffered data in current operation. After call this, you should send new line or other deliminator to tell child the input ends
     * When `$rbuffer` is set to true, the pending read data is cleaned
     * else buffered write data is cleaned
     * 
     * This cause the read() or write() to start completly new read/write operation. 
     * @param bool $rbuffer true to clean read data else will clean write buffer
     */
    public function clean(bool $rbuffer) :void{
        if($rbuffer) {
            $this->buffer = "";
            $this->rconsumed = 0;
            return;
        }
        $this->consumed = 0;
        $this->data = "";
    }

    /**
    * Write data to current pipe.
    *
    * The pipe is set to non-blocking(not really, for more detail find pipe system of original php). Each call write at most $lenght.
    * If previous write didn't finhish, subsequence call will make it push remaining data. Calling `clean(false)` to discart pending data and start completly new write operation.
    * @param int $lenght maxium lenght to be write per iteration
    * @param int|null $sec second to wait till pipe is avaible, same effect stream_select
    * @param int|null $ms microsecond to wait till pipe is avaible, same effect as stream_select
    * @param int $lenght Maxium number of byte to write for this call
    * @return true all pending data written
    * @return string all remaining data to be written
    * @return false the pipe isn't ready to be write and nothing is been written yet before avaible(stream_select) or is stopped externally
    * @throws RuntimeException If stream_select() fails or writing to the pipe fails(fwrite).
    * @throws InvalidArgumentException If `$max_len` is less than or equal to zero, or `$ms` is not between 0-999999
    */
    public function write(string $data,?int $sec,?int $ms = null, int $lenght = 1024) : bool|string{
        if($ms > 999999 || $ms <0) throw new InvalidArgumentException('Invalid Argument Exception in Pipe.write(): param $ms must be between 0 to 999999');
        if($lenght <=0) throw new InvalidArgumentException('Invalid Argument Exception in Pipe.write(): param $lenght must >0');

        if($this->data === '') {
            $this->data = $data;
            $this->consumed = 0;
        }

        $total = \strlen($this->data);
        while(true){
            if(!$this->active) return substr($this->data,$this->consumed);

            $remain = $total - $this->consumed;
            if ($remain <= 0) {
                $this->data = '';
                $this->consumed = 0;
                return true;
            }

            $write_len = min($lenght,$remain);

            $ready = $this->stream_select_from_mode($sec,$ms);
            if($ready === 0) break;
            else if($ready === false) throw new RuntimeException("Pipe::write(): stream_select failed while waiting pipe to become writable");

            //strip data from consumed with maxium x characters
            $input_data = \substr($this->data,$this->consumed);


            $fwrite = \fwrite($this->pipe,$input_data,$write_len);
            
            
            

            if($fwrite === false) throw new RuntimeException("In Pipe::write(): fwrite() failed while writing to pipe - ". \error_get_last()["message"] ?? "unknown");

            $this->consumed += $fwrite;
            if($fwrite === 0) return substr($this->data,$this->consumed);
            if($fwrite < $write_len) continue;
            break;
        }
        //positive: strip consumed data and return striped, all $this->consumed value from original string is stripped
        if($this->consumed < $total) return substr($this->data,$this->consumed);

        $this->data = "";
        $this->consumed = 0;
        return true;
    }

    /**
    * Read from current pipe. 
    *
    * Data is returned segmented by `$end`. The delimiter is included in returned string so caller can determine whether is completed message.
    * Important: subsequence call will not consume buffered data unless the delimiter contain in buffered data. This mean you don't need to buffer the result outside, using the result directly if matches protocol(e,g have delimiter in end).
    * For example: abc\ndef\ngg in one read cause successive call return abc\n, def\n gg
    *
    * The pipe is set to non-blocking(not really, for more detail find pipe system of original php).
    * The read operation is stopped when:
    *   1. `$max_len` readed
    *   2. deliminater `$end` is found in accumulated data
    *   3. Pipe reach EOF
    *   4. stopped externally
    *   5. No data avaible/pipe blocked before timeout(using stream_select()).
    *
    * If a previous read left data in the internal buffer of this object, subsequence call return/continue that data before proccess new. 
    * Calling `clean(true)` to discart buffered data and start completly new read operation.
    *
    * @param string $end deliminator to stop read, default is newline
    * @param int|null $max_len maxium lenght to be read per iteration including buffered data. pass null for no limit(PHP_INT_MAX)
    * @param int|null $sec second to wait till pipe is avaible, same effect stream_select
    * @param int|null $ms microsecond to wait till pipe is avaible, same effect as stream_select
    * @return string data readed from pipe
    * @return false no data is available before the timeout and the internal buffer is empty.
    * @return true operation stopped(previous data will return first if contain previous data with delimiter)
    * @throws RuntimeException If stream_select() fails or read to the pipe fails(fgetc).
    * @throws InvalidArgumentException If `$max_len` is less than or equal to zero, or `$ms` is not between 0-999999
    */
    public function read(?int $sec,?int $ms = null,?int $max_len = 1024,string $end = "\n") : string|bool{
        if($ms > 999999 || $ms <0) throw new InvalidArgumentException('Invalid Argument Exception in Pipe.write(): param $ms must be between 0 to 999999');

        $max_len ??= PHP_INT_MAX;
        $delimeter_len = \strlen($end);

        
        while(true){
            //check in previous data have delimeter, always run this first after data written and before read any pipe when first enter
            if($this->buffer !== '') {
                $delimeter_pos = strpos($this->buffer,$end);
                if($delimeter_pos !== false){
                    //return stripped version of message and remove from buffer, will include delimiter for caller to check.
                    $final_data = substr($this->buffer, 0, $delimeter_pos + $delimeter_len);
                    $this->buffer = substr($this->buffer, $delimeter_pos + $delimeter_len);

                    $this->rconsumed = 0;
                    return $final_data;
                } 
            }

            $new_bytes = \strlen($this->buffer) - $this->rconsumed;
            if(!$this->active || $new_bytes >= $max_len || feof($this->pipe)) break;

            $ready = $this->stream_select_from_mode($sec,$ms);

            if($ready === 0) break;
            else if($ready === false) throw new RuntimeException("In Pipe::write(): Selection for stream failed");

           
            $data = stream_get_contents($this->pipe,$max_len - $new_bytes);


            if($data === false) throw new RuntimeException("In Pipe::read(): stream_get_contents failed to read data from pipe");

            if ($data === '') {
                if (feof($this->pipe)) break;
                continue;
            }
            $this->buffer .= $data;
        }

        if(!$this->active) return true;
        if($this->buffer === '' || \strlen($this->buffer) === $this->rconsumed) return false;
        
        $this->rconsumed = \strlen($this->buffer);
        return $this->buffer;
    }

    private function stream_select_from_mode(?int $sec, ?int $ms){
        $write = null;
        $read = null;
        $err = null;


        switch($this->mode){
            case 0: //in
            $write = [$this->pipe];
            break;
            case 2:
            case 1: //out
            $read = [$this->pipe];
            break;
        }

        return stream_select($read,$write,$err,$sec,$ms);
    }
}

    class Child_process{
        /**
         * @var array<int, array{
         *          process:resource,
         *          pipes:array{
         *              0: Pipe|null,
         *              1: Pipe|null,
         *              2: Pipe|null
         *          }
         *      }>
        */
        private array $all_process = [];
        public int $return_code = 0;

        /**
         * @param array $args append to end of command with escapeshellarg
         * @throws RuntimeException when one of child fail to open
         * @throws InvalidArgumentException 
         */
        public function __construct(string $cmd,array $descriptor_spec, array $args = [], ?string $cwd = null,  ?array $env_vars = null,array $options = [], int $child_count = 1, )
        {
            if($child_count <= 0) throw new InvalidArgumentException("Invalid argument exception in Child_process.__construct(): param child_count can only be greater than 0 but $child_count provided");
            
            $arg_txt = [];
            foreach($args as $arg) $arg_txt[] = escapeshellarg($arg);

            $arg_txt = implode(" ",$arg_txt);

            for($i = 0; $i < $child_count; $i++){
                $process = proc_open("$cmd $arg_txt",$descriptor_spec,$pipes,$cwd,$env_vars,$options);
                if(!\is_resource($process)) throw new RuntimeException("In Child_process.__construct(): One of child process failed to open");

                $status = proc_get_status($process);

                $pid = $status['pid'];

                $write = $pipes[0]===null ? null : new Pipe($pipes[0],0);
                $read = $pipes[1]===null ? null : new Pipe($pipes[1],1);
                $err = $pipes[2]===null ? null : new Pipe($pipes[2],2);
                

                $this->all_process[$pid] = [
                    'process'=>$process,
                    'pipes'=>[0=>$write,1=>$read,2=>$err]
                ];
            }
        }

        public function Is_running(int $pid){
            return isset($this->all_process[$pid]) && proc_get_status($this->all_process[$pid]['process'])['running'];
        }

        /**
         * Close all/some pipes
         * @param int[] $pids Contain all pid that want to be closed. If is empty or not passed, all pipe is closed
         * @param int[] $channel list which can contain 0 for stdin, 1 for stdout and 2 is stderr. if not passed then take as all channel. For invalid range, it is simply ignored
         */
        public function Close_pipes(array $pids = [], array $channel = []){
            $close_all_pids = $pids === [];
            $close_all_pipes = $channel === [];

            $close_pipes = 
            [
                0=> $close_all_pipes || in_array(0,$channel,true),
                1=> $close_all_pipes || in_array(1,$channel,true),
                2=> $close_all_pipes || in_array(2,$channel,true)
            ];

            foreach($this->all_process as $pid=>$details_arr){
                if(!$close_all_pids && !in_array($pid,$pids,true)) continue;


                foreach($close_pipes as $chan => $close){
                    if(!$close) continue;

                    $pipe = $this->all_process[$pid]['pipes'][$chan];
                    if($pipe === null) continue;

                    $pipe->close();
                    $this->all_process[$pid]['pipes'][$chan] = null;
                }
            }
        }

        /**
         * Close all/some proccess with proc_close and all related pipes
         * @param int[] $pids Contain all pid that want to be closed. If is empty or not passed, all is closed
         * @return array<int,int> pid=>return code
         */
        public function Proc_closes(array $pids = []) : array{
            $close_all_pids = $pids === [];
            $return_codes = [];

            foreach($this->all_process as $pid=>$details_arr){
                if(!$close_all_pids && !in_array($pid,$pids,true)) continue;
                
                $this->Close_pipes([$pid]);
                $return_codes[$pid] = proc_close($details_arr['process']);
                unset($this->all_process[$pid]);
            }
            return $return_codes;
        }

        /**
         * Returns array contain all pid=>resource or specific process of opened proccess from proc_open in this object
         * @param int $pid if specified, only return 1 of resource stored else return list of pid=>resourc
         * @param int $pipe if have no pid specified or invalid, this argument is ignored, return requested resource but only one of pipe array where:
         * 0 = read, 1 = write, 2 = err.
         * 
         * @return array<int, array{
         *          process:resource,
         *          pipes:array{
         *              0: Pipe|null,
         *              1: Pipe|null,
         *              2: Pipe|null
         *          }
         *      }> 
         * Contain all opened process, check their states by find their pid and pipe
         */
        public function Get_process(int $pid = -1,?int $pipe = null) : array|Pipe{
            $all_process = $this->all_process;
            $target = [];
            if($pid !== -1){
                if($pipe !== null && isset($all_process[$pid]['pipes'][$pipe])) $target = $all_process[$pid]['pipes'][$pipe];
                else $target = $all_process[$pid];
            }
            else $target = $all_process;

            return $target;
        }
    }
?>