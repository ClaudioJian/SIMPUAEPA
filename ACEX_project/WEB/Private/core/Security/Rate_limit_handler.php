<?php 


namespace ACEX_project\WEB\Private\Core\Security;
    require_once __DIR__ . '/../AppCommonVar.php';
    require_once __DIR__ . '/../../Auth/session_manager.php';

    require_once __DIR__ .'/../../Error/Error_code.php';
    require_once __DIR__ .'/../../Error/Error_manager.php';
    use Exception;
use function ACEX_project\WEB\Private\Error\Handle_error;
use function ACEX_project\WEB\Private\Error\Log_internal;

    use ACEX_project\WEB\Private\Core\File_storation_path;
    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;
    use Monolog\Level;
use Throwable;

    require_once __DIR__ .'/../File_storation_map.php';


    enum Rate_bucket{
        case IP;
        case Account;
        /**
         * @param int $key the number to decice which file will use for each different bucket. Pick rule for this should be same amoung call
         * @return string
         */
        public function Get_path(int $key) : string{
            $dir = match ($this) {
                self::IP => File_storation_path::Rate_track_ip->value,
                self::Account => File_storation_path::Rate_track_account->value,
            };

            $bucket = (($key % RATE_FILE_MAX_QUANT) + RATE_FILE_MAX_QUANT) % RATE_FILE_MAX_QUANT;
            $file = "$dir/record_$this->name"."_$bucket.txt";
            if(!\is_file($file)) \file_put_contents($file,"");
            return $file;
        }
        public function Get_data() : string{
            $request_body = Get_request_body();

            $email = $request_body['email'] ?? '';
            return match ($this) {
                self::IP => $_SERVER['REMOTE_ADDR'] ?? $_SERVER['X_HTTP_FOWARD_TO'] ?? '',
                self::Account => $email,
            };
        }
    } 

    /**
     * Use try finally to catch when calling any function here and use Rate_limiter::Release() to avoid file lock infidenilly
     * 
     */
    class Rate_limiter{
        /**
         * @var list<array{id:string,count:int,expired_at:int,...<string,int>}>
         */
        private array $data = [];
        private ?int $current_key = null;
        /**
         * @var resource|null
         */
        private mixed $current_ftp = null;
        private bool $write_limit = false;
        private int $deleted_record = 0;

        /**
         * @param int $abs_timeout microsecond timeout is allowed when read/write is blocked
         * @param int $wait_split how many time the check will be done for check absoule timeout in each iteration. this is for reduce cpu usage
         */
        public function __construct(
            private readonly Rate_bucket $type,
            private int $abs_timeout,
            private int $wait_split = 100
        )
        {
            if($abs_timeout <=0 || $wait_split <=0) Handle_error(
                new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::invalid),
                Level::Critical,
                'In Rate_limiter::__construct(): argument $abs_timeout must be greater than 0, '."$abs_timeout given"
            );
        }

        public function Release(){
            if(\is_resource($this->current_ftp)){
                \flock($this->current_ftp, LOCK_UN);
                \fclose($this->current_ftp);
            }

            $this->current_ftp = null;
            $this->write_limit = false;
            $this->deleted_record = 0;
            $this->data = [];
        }


        /**
         * Check wheter the requested bucket type reached its limit for current user request.
         * IMPORTANT: after calling this function, you must release file lock by calling `Push()` or `Release`
         * 
         * @param int $key Used to indentify the correct file to write at. negative are treat as 1.
         * @param callable $funct : [function(&array $data)] receives current data for that key by reference, allowing modify and comparation against current request related record.
         * The callback must accept the current data array by reference and return a boolean. 
         * If returns true, this function(`At_limit`) will immedially return true(mean hit limit)
         * Reserved values: expired_at, id; any change against reserved value won't have any effect
         * 
         * @return bool|null false if the limit has not been reached or record has expired;
         * true if the limit has reached according to the comparasion;
         * null if no record exist(i.e., a new record).
         */
        public function At_limit(int $key, callable $funct):bool|null{
            $target = $this->type->Get_data();
            if(!$this->Get_data($this->current_key ?? $key)) return true;

            $found = false;
            foreach($this->data as $idx=>&$data){
                if($this->Clean_expired_record($idx,$data) || $data['id'] !== $target) continue;
                $found = true;
                
                try{
                    $og_expired_at = $this->data[$idx]['expired_at'];

                    $result = $funct($this->data[$idx]);

                    $this->data[$idx]['expired_at'] = $og_expired_at;
                    $this->data[$idx]['id'] = $target;
                    if($result) return true;
                }catch(Throwable $e){
                    if(\is_resource($this->current_ftp)) \fclose($this->current_ftp);
                    Handle_error(
                        new Error_code(Error_domain::server,Resource_code::rate,Error_condition::unknown),
                        Level::Critical,
                        $e->getMessage()
                    );
                }
            }
            if(!$found) return null;
            return false;
        }

        /**
         * Check if requested bucket type for current user request has hit limit.
         * IMPORTANT: after use this function, you need to unlock the lock of file using function `Push()`
         * @param int $key This is to find correct file. if negative, turn into 1. Ignored if in lock
         * @param array<string,int> $key_val_pair extra counter to be registered, if the key doesn't exist, automatically add to record to value specified. reserved key: count(will add automatically to 1)
         * @return bool|null false when nothing is modified or null is locked. True when have modification
         */
        public function Modify_counter(int $key, array $key_val_pair = [], bool $replace = false) : bool|null{
            $target = $this->type->Get_data();
            if(!$this->Get_data($this->current_key ?? $key)) return null;

            foreach($this->data as $idx=>&$data){
                if($this->Clean_expired_record($idx,$data) || $data['id'] !== $target) continue;
                
                foreach($key_val_pair as $k=>$value){
                    if(!isset($this->data[$idx][$k]) && \is_int($value)) {
                        $this->data[$idx][$k] = $value;
                        continue;
                    }

                    $value_is_int = isset($data[$k]) && \is_int($value);
                    if(!\is_int($value) || !$value_is_int) {
                        if(\is_resource($this->current_ftp)) \fclose($this->current_ftp);
                        Handle_error(
                            new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::type),
                            Level::Critical,
                            'In Rate_limiter::Modify_counter(): counter "'.$k.'" in array $key_val_pair must be int'
                        );
                    }

                    if(!isset($data[$k])) $value = 0;
                    $this->data[$idx][$k] = $replace ? $value : ($this->data[$idx][$k] ?? 0) + $value;
                }

                return true;
            }
            return false;
        }

        /**
         * Only add new record if not exist and can write in
         * Log when add sucessfullty
         * @param array<string,int> $key_val_pair  
         * @param int $expired_at in ms(current time + limit = this value)
         * @return bool|null null if cannot write, false if no new record to add and true successfully add
         */
        public function Append_new_record(int $key,int $expired_at,array $key_val_pair = []) : bool|null{
            $target = $this->type->Get_data();
            if(!$this->Get_data($this->current_key ?? $key) || ($this->write_limit && $this->deleted_record===0)) return null;

            foreach($this->data as $data) if($data['id'] === $target) return false;

            $new_record = ['id'=>$target,'count'=>1];
            foreach($key_val_pair as $k=>$value){
                if(!\is_int($value)) {
                    if(\is_resource($this->current_ftp)) \fclose($this->current_ftp);
                    Handle_error(
                        new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::type),
                        Level::Critical,
                        'In Rate_limiter::Modify_counter(): one of key value case in array $key_val_pair isnt int'
                    );
                }

                $new_record[$k] = $value;
            }
            $new_record['expired_at'] = $expired_at;

            $this->data[] = $new_record;
            if($this->deleted_record>0) $this->deleted_record--;

            Log_internal(
                new Error_code(Error_domain::request,Resource_code::rate,Error_condition::timeout),
                Level::Info,
                "[Bucket type: ".$this->type->name."] Adding new record: [$target]"
            );
            return true;
        }

        /**
         * Push all change done to the data and unlokc file
         * @return bool
         */
        public function Push() : bool{
            $this->current_key = null;

            try{
                if(!\is_resource($this->current_ftp)) return false;
                if(!ftruncate($this->current_ftp,0) || !rewind($this->current_ftp)) return false;
                foreach($this->data as $data){
                    $json = \json_encode($data);
                    $line = "$json\n";
                    if($json === false || !\fwrite($this->current_ftp,$line)) return false;
                }
                return \fflush($this->current_ftp);
            }finally{
                $this->Release();
            }
        }

        /**
         * Log when processing will take too much of time
         * @param int $key
         * @return bool
         */
        private function Get_data(int $key) : bool{
            //alredy open
            if($this->current_key !== null) return true;
            if($key <=0) $key = 1;

            $file = $this->type->Get_path($key);

            $start_t = (int)(\microtime(true) * 1000);
            $current_time = $start_t;
            $ftp = \fopen($file,'r+');

            if($ftp === false) return false;

            while(true){
                $locked = \flock($ftp,LOCK_EX|LOCK_NB,$would_block);

                if(!$locked){
                    if(!$would_block) return false;
                    \usleep($this->abs_timeout/$this->wait_split);
                }

                $current_time = (int)(\microtime(true) * 1000);
                if($current_time - $start_t >= $this->abs_timeout) {
                    Log_internal(new Error_code(Error_domain::request,Resource_code::rate,Error_condition::timeout),
                        Level::Info,
                        "[Bucket type: ".$this->type->name."] Timeout waiting for file lock: file=[\"".basename($file)."\"], target=[\"". $this->type->Get_data() ."\"]; aborting request..."
                    );

                    \fclose($ftp);
                    return false;
                }
                if($locked) break;
            }

            $record = null;

            //adquire lock
            while(($line = \fgets($ftp)) !== false){
                if($line === '') continue;
                $record = \json_decode($line,true);

                try{
                    if(!\is_array($record) || json_last_error() !== JSON_ERROR_NONE) throw new Exception("In Rate_limiter::Get_data: cannot process data from file because is not valid json with error code: ".json_last_error());
                    if(!isset($record['id'])) throw new Exception("In Rate_limiter::Get_data: Missing id in record");
                    if(!isset($record['expired_at']) && !\is_int($record['expired_at'])) throw new Exception("In Rate_limiter::Get_data: Record's expired_at isn't valid or missing");
                    if(!isset($record['count']) && !\is_int($record['count'])) throw new Exception("In Rate_limiter::Get_data: Record's count isn't valid or missing");
                }catch(Throwable $e){
                    \fclose($ftp);
                    Handle_error(
                        new Error_code(Error_domain::server,Resource_code::invalidArgument,Error_condition::type),
                        Level::Critical,
                        $e->getMessage()
                    );
                }


                if($record !==null) $this->data[] = $record;

                $current_time = (int)(\microtime(true) * 1000);
                if($current_time - $start_t >= $this->abs_timeout) {
                    Log_internal(new Error_code(Error_domain::server,Resource_code::rate,Error_condition::timeout),
                        Level::Warning,
                        "[Bucket type-\"".$this->type->name."\"] Timeout while reading and decoding file=[\"". basename($file) . "\"]; Refusing to new record"
                    );
                    $this->write_limit = true;
                }
            }

            $this->current_key = $key;
            $this->current_ftp = $ftp;
            return true;
        }

        private function Clean_expired_record(int $idx,array &$data) : bool{
            $current_time = (int)(\microtime(true) * 1000);
            if(!isset($data['expired_at'])) return false;

            $time_limit = $data['expired_at'];

            if($current_time < $time_limit) return false;
            unset($data);
            unset($this->data[$idx]);
            $this->deleted_record++;
            return true;
        }
    }