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

    class Route{
        private readonly string $res;
        private $action;


        /**
         * @param callable $action the first argument can pass IP rate limiter(class Rate_limiter) or null
         * @param bool $require_login if true, $use_session is automatically set to true
         */
        public function __construct(
            string $resource,
            private readonly string $method,
            callable $action,
            private readonly bool $use_session=true, 
            private readonly bool $require_login = false,
            private readonly bool $bypass_rate_limit = false,
            private readonly bool $require_nonce = false,
        )
        {
            $this->res = $resource;
            $method = \strtoupper($method);
            
            if(!\in_array($method,VALID_HTTP_METHOD,true)) throw new Exception("$method isn't valid http method");

            $this->action = $action;

            if($require_login || $require_nonce) $use_session = true;
        }

        public function execute(string $method,string $requested_resource) : bool{
            if($requested_resource !== $this->res || $method !== $this->method) return false;

            if($this->require_login) \define('REQUIRE_LOGIN',true);
            if($this->use_session){
                $status = \session_status();
                if($status===PHP_SESSION_DISABLED){
                    \http_response_code(500);
                    exit();
                }


                if($status === PHP_SESSION_NONE) session_initialize();
            }

            if($this->require_nonce) Require_nonce();

            $IP_rate_limiter = null;
            if(!$this->bypass_rate_limit) $IP_rate_limiter = IP_Rate_limit();

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

            define('CURRENT_ENDPOINT',$requested_resource);
            

            Validate_request();
            ($this->action)($IP_rate_limiter);
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


    function IP_Rate_limit() : Rate_limiter{
        //1 second for test
        $IP_rate_limiter = new Rate_limiter(Rate_bucket::IP,10000);

        try{
            $ip = $_SERVER['REMOTE_ADDR'];
            $key = hexdec(substr(hash('xxh32', $ip), 0, 8));

            $result = $IP_rate_limiter->At_limit($key,
                function(array &$data){
                    $limit = RATE_IP_MAX_REQUEST_PER_INTERVAL['max_request'];
                    $interval = RATE_IP_MAX_REQUEST_PER_INTERVAL['interval'];
                    $min_interval = RATE_IP_MINIUM_INTERVAL;

                    $current_t = (int)(\microtime(true) * 1000);
                    $last_request_at = $data['last_request_at'];
                    $data['last_request_at'] = $current_t;

                    $start_at = $data['start_at'];
                    $time = $current_t - $start_at;

                    if($time>=$interval) {
                        $data['count'] = 0;
                        $data['start_at'] = $current_t;
                    }

                    $current_interval = $current_t - $last_request_at;

                    if($current_interval < $min_interval || $data['count'] >= $limit){
                        $ip = Get_IP_address_txt();
                        $msg = $data['count'] >= $limit 
                        ? $data['count']." request exceeded within $interval ms(limit: $limit)" 
                        : "Request interval too short: {$current_interval}ms since last request(minium: {$min_interval}ms)";
                        
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
                
                $curr_t = (int)(\microtime(true) * 1000);
                if($result === false) $IP_rate_limiter->Modify_counter($key,['count'=>1]);
                else $IP_rate_limiter->Append_new_record($key,$curr_t + $life_time,['last_request_at'=>$curr_t,'start_at'=>$curr_t]);
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