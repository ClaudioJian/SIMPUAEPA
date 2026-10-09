<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";


    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Auth\session_initialize;
    use function ACEX_project\WEB\Private\Auth\User\Is_logged;
    use function ACEX_project\WEB\Private\Core\Construct_MIME;

    require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Validate_request;

    require_once __DIR__ . "/../WEB/Private/Core/MIME_type.php";


    use Exception;


    class Route{
        private readonly string $res;
        private readonly string $method;
        private $action;
        private readonly bool $use_session;
        private readonly bool $require_login;


        /**
         * @param bool $require_login if true, $use_session is automatically set to true
         */
        public function __construct(string $resource,string $method,callable $action,bool $use_session=true, bool $require_login = false)
        {
            $this->res = $resource;
            $method = strtoupper($method);
            
            if(!in_array($method,VALID_HTTP_METHOD,true)) throw new Exception($method . "isn't valid http method");
            $this->method = $method;
            $this->action = $action;

            if($require_login) $use_session = true;

            $this->use_session = $use_session;
            $this->require_login = $require_login;
        }

        public function execute(string $method,string $requested_resource) : bool{
            if($requested_resource !== $this->res || $method !== $this->method) return false;

            if($this->require_login) define('REQUIRE_LOGIN',true);
            if($this->use_session){
                $status = session_status();
                if($status===PHP_SESSION_DISABLED){
                    http_response_code(500);
                    exit();
                }


                if($status === PHP_SESSION_NONE) session_initialize();
            }

            if($this->require_login && !Is_logged()){
                http_response_code(401);
                header('Content-Type: '.Construct_MIME(MIME['application']['json']));
                echo json_encode(['success'=>-2]);
                exit();
            }
            

            Validate_request();
            ($this->action)();
            return true;
        }
    }

    function Route_start(array $all_route){
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $pathi = $_SERVER['PATH_INFO'];
        $required_resource = ($pathi===null||$pathi==='') ? null : strtolower($_SERVER['PATH_INFO']);

        foreach($all_route as $route){
            if ($route instanceof Route) {
                if($route->execute($method, $required_resource)) return;
            }
        }
        http_response_code(404);
        exit();
    }
?>