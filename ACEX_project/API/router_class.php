<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";


    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Resource_code;

    use function ACEX_project\WEB\Private\Api\Require_nonce;
    use function ACEX_project\WEB\Private\Auth\session_initialize;
    use function ACEX_project\WEB\Private\Auth\User\Is_logged;
    use function ACEX_project\WEB\Private\Core\Construct_MIME;
use function ACEX_project\WEB\Private\Core\Get_IP_address_txt;

    require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Validate_request;
    use function ACEX_project\WEB\Private\Error\Log_internal;

    require_once __DIR__ . "/../WEB/Private/Core/MIME_type.php";

    require_once __DIR__ . "/../WEB/Private/Error/Error_code.php";
    require_once __DIR__ . "/../WEB/Private/Error/Error_manager.php";

    require_once __DIR__ . "/../WEB/Private/Core/Security/Rate_limit_handler.php";
    use ACEX_project\WEB\Private\Core\Security\Rate_bucket;
    use ACEX_project\WEB\Private\Core\Security\Rate_limiter;


    use \Exception;
use Monolog\Level;
use function ACEX_project\WEB\Private\Core\Security\Bucket_info_from_fixed_window;
use function ACEX_project\WEB\Private\Core\Security\Fixed_window_to_bucket_algo;

    class Route{
        private readonly string $res;
        private $action;
        private readonly ?array $rate_limit_option;
        private readonly bool $use_session;
        private array $captured_args = [];

        /**
         * @param string $resource endpoint or resorce requested. prefix SIMPUAEPA is stripeed, however, leading '/' need be prefix. The wildcard '*' will matches a path segment(segmented by '/', if wildcard is in the end, no need '/' at end to segment it) and capture its values as argument in a array, preserving the order in which wildcards appears
         * @param callable $action the first argument can pass IP rate limiter(class Rate_limiter) or null, second argument will pass captured values from wildcard or empty array.
         * @param bool $require_login if true, $use_session is automatically set to true
         * @param bool $bypass_rate_limit ignore ip rate limit and endpoint rate limit if set
         * @param null|array{
         *      max_request:int,
         *      interval:int
         * } $rate_limit_option if specified or not null, the endpoint will have rate limit for maximum request per interval
         */
        public function __construct(
            string $resource,
            private readonly string $method,
            callable $action,
            bool $use_session=true, 
            private readonly bool $require_login = false,
            private readonly bool $bypass_rate_limit = false,
            private readonly bool $require_nonce = false,
            ?array $rate_limit_option = null
        )
        {
            $this->res = \strtolower($resource);
            $method = \strtoupper($method);
            
            if(!\in_array($method,VALID_HTTP_METHOD,true)) throw new Exception("$method isn't valid http method");

            $this->action = $action;

            $this->use_session = $require_login || $require_nonce ? true : $use_session;
            $this->rate_limit_option = $bypass_rate_limit ? null : $rate_limit_option;
        }


        public function execute(string $method,string $requested_resource) : bool{
            if($method !== $this->method || !$this->is_request_path($requested_resource)) return false;

            if($this->require_login) \define('REQUIRE_LOGIN',true);
            if($this->use_session){
                $status = \session_status();
                if($status===PHP_SESSION_DISABLED){
                    Log_internal(
                        new Error_code(Error_domain::configuration, Resource_code::session, Error_condition::unsupported),
                        Level::Error,
                        "Failed to check permission of user: Session is not allowed in current php setting"
                    );

                    \http_response_code(500);
                    exit();
                }

                if($status === PHP_SESSION_NONE) session_initialize();
            }

            if($this->require_nonce) Require_nonce();

            define('CURRENT_ENDPOINT',$requested_resource);
            $IP_rate_limiter = null;

            if(!$this->bypass_rate_limit) {
                EndPoint_Rate_limit($this->rate_limit_option);
                $IP_rate_limiter = IP_Rate_limit();
            }

            if($this->require_login && !Is_logged()){
                $ip = Get_IP_address_txt();
                
                Log_internal(
                    new Error_code(Error_domain::request,Resource_code::resource,Error_condition::unauthenticated),
                    Level::Warning,
                    "[IP-\"$ip\"] Access to endpoint or resource \"$requested_resource\" require authentication, but the user isn't logged in"
                );

                \http_response_code(401);
                \header('Content-Type: '.Construct_MIME(MIME['application']['json']));
                echo json_encode(['success'=>-2]);
                exit();
            }
            

            Validate_request();
            ($this->action)($IP_rate_limiter, $this->captured_args);
            return true;
        }

        private function is_request_path(string $requested_resource): bool{
            if($requested_resource === $this->res) return true;
            $captured_args = [];
            $this->captured_args = [];

            $requested_parts = explode('/',$requested_resource);
            $current_parts = explode('/',$this->res);

            $segments_count = \count($current_parts);
            if($segments_count !== \count($requested_parts)) return false;

            for($i = 0; $i < $segments_count; $i++){
                $req_part = $requested_parts[$i];
                $curr_part = $current_parts[$i];

                if(trim($curr_part) === '*') {
                    $captured_args[] = trim($req_part);
                    continue;
                }
                if($curr_part !== $req_part) return false;
            }

            $this->captured_args = $captured_args;
            return true;
        }        
    }

    function Route_start(array $all_route){
        $method = \strtoupper($_SERVER['REQUEST_METHOD']);
        $pathi = $_SERVER['PATH_INFO'];
        $required_resource = ($pathi===null||$pathi==='') ? null : \strtolower($_SERVER['PATH_INFO']);

        foreach($all_route as $route){
            if ($route instanceof Route) {
                if($route->execute($method, $required_resource)) return;
            }
        }
        
        \http_response_code(404);
        exit();
    }

    function EndPoint_Rate_limit(?array $rate_option): void{
        if($rate_option === null) return;
        //1 second for test
        $ep_rlimiter = new Rate_limiter(Rate_bucket::EndPoint,10000);

        try{
            $endpoint = CURRENT_ENDPOINT;
            $key = hexdec(substr(hash('xxh32', $endpoint), 0, 8));

            $result = $ep_rlimiter->At_limit($key,
                function(array &$data) use ($rate_option, $endpoint){
                    $limit = $rate_option['max_request'];
                    $interval = $rate_option['interval'];

                    $current_t = \intdiv(\hrtime(true),1000000);
                    if(!isset($data['last_refill'])) $data['last_refill'] = $current_t;

                    $result = Fixed_window_to_bucket_algo($limit, $interval, $data['last_refill'], $data['count']);


                    if($result === true){
                        $ip = Get_IP_address_txt();
                        $msg = $data['count']." rate-limit tokens of Endpoint(\"$endpoint\") remained within interval $interval ms(limit: $limit requests)";

                        Log_internal(
                            new Error_code(Error_domain::request, Resource_code::rate, Error_condition::surpass_limit),
                            Level::Info,
                            "[IP-\"$ip\"] $msg"
                        );
                        return true;
                    }
                    return false;
                }
            );

            if($result === null || $result === false) {
                $life_time = RATE_RECORD_EXPIRATION;

                $limit = $rate_option['max_request'];
                $interval = $rate_option['interval'];
                $b_info = Bucket_info_from_fixed_window($limit,$interval);


                $curr_t = \intdiv(\hrtime(true),1000000);
                if($result === null) $ep_rlimiter->Append_new_record($key,$curr_t + $life_time,['last_refill'=>$curr_t,'count'=>$b_info['capacity']]);
                $ep_rlimiter->Push();
            }
            else{
                \http_response_code(429);
                $ep_rlimiter->Push();
                exit();
            }
        }finally{
            $ep_rlimiter->Release();
        }
    }

    function IP_Rate_limit() : Rate_limiter{
        //1 second for test
        $IP_rate_limiter = new Rate_limiter(Rate_bucket::IP,10000);

        try{
            $ip = $_SERVER['REMOTE_ADDR'];
            $key = hexdec(substr(hash('xxh32', $ip), 0, 8));

            $limit = RATE_IP_MAX_REQUEST_PER_INTERVAL['max_request'];
            $interval = RATE_IP_MAX_REQUEST_PER_INTERVAL['interval'];

            $result = $IP_rate_limiter->At_limit($key,
                function(array &$data) use($limit, $interval){
                    $min_interval = RATE_IP_MINIMUM_INTERVAL;
                    $current_t = \intdiv(\hrtime(true),1000000);

                    $last_request_at = $data['last_request_at'];
                    $data['last_request_at'] = $current_t;

                    if(!isset($data['last_refill'])) $data['last_refill'] = $current_t;
                    $result = Fixed_window_to_bucket_algo($limit, $interval, $data['last_refill'], $data['count']);
                    
                    $current_interval = $current_t - $last_request_at;

                    if($current_interval < $min_interval || $result === true){
                        $ip = Get_IP_address_txt();
                        $msg = $current_interval < $min_interval 
                        ? "Request interval too short: {$current_interval}ms since last request(minimum: {$min_interval}ms)"
                        : $data['count']." rate-limit tokens remained within window $interval ms(limit: $limit request)" ;

                        
                        Log_internal(
                            new Error_code(Error_domain::request, Resource_code::rate, Error_condition::surpass_limit),
                            Level::Info,
                            "[IP-\"$ip\"] $msg"
                        );
                        return true;
                    }
                    

                    return false;
                }
            );

            if($result === null || $result === false) {
                $life_time = RATE_RECORD_EXPIRATION;

                $b_info = Bucket_info_from_fixed_window($limit,$interval);
                
                $curr_t = \intdiv(\hrtime(true),1000000);
                if($result === null) $IP_rate_limiter->Append_new_record($key,$curr_t + $life_time,['count'=>$b_info['capacity'],'last_request_at'=>$curr_t,'last_refill'=>$curr_t]);
                $IP_rate_limiter->Push();
            }
            else{
                \http_response_code(429);
                $IP_rate_limiter->Push();
                exit();
            }
        }finally{
            $IP_rate_limiter->Release();
            return $IP_rate_limiter;
        }
    }