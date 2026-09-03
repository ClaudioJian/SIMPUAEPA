<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";


    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Auth\session_startup;
    use Exception;


    class Route{
        private readonly string $res;
        private readonly string $method;
        private $action;
        private readonly bool $use_session;


        public function __construct(string $resource,string $method,callable $action,bool $use_session=false)
        {
            $this->res = $resource;
            $method = strtoupper($method);
            
            if(!in_array($method,VALID_HTTP_METHOD,true)) throw new Exception($method . "isn't valid http method");
            $this->method = $method;
            $this->action = $action;
            $this->use_session = $use_session;
        }
        public function execute(string $method,string $requested_resource){
            if($requested_resource !== $this->res || $method !== $this->method) return;
            if($this->use_session){
                $status = session_status();
                if($status===PHP_SESSION_DISABLED){
                    http_response_code(500);
                    exit();
                }
                if($status === PHP_SESSION_NONE) session_startup();
            }
            ($this->action)();
        }
    }

    function Route_start(array $all_route){
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $pathi = $_SERVER['PATH_INFO'];
        $required_resource = ($pathi===null||$pathi==='') ? null : strtolower($_SERVER['PATH_INFO']);

        foreach($all_route as $route){
            if ($route instanceof Route) {
                $route->execute($method, $required_resource);
            }
        }
    }
?>