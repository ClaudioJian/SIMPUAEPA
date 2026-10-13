<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";


    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Auth\session_initialize;
<<<<<<< HEAD
use function ACEX_project\WEB\Private\Auth\User\Is_logged;
use function ACEX_project\WEB\Private\Core\Construct_MIME;
=======
    use function ACEX_project\WEB\Private\Auth\User\Is_logged;
    use function ACEX_project\WEB\Private\Core\Construct_MIME;
>>>>>>> origin/BackEnd

    require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Validate_request;

    require_once __DIR__ . "/../WEB/Private/Core/MIME_type.php";


<<<<<<< HEAD
    use Exception;
=======
    use \Exception;
>>>>>>> origin/BackEnd


    class Route{
        private readonly string $res;
<<<<<<< HEAD
        private readonly string $method;
        private $action;
        private readonly bool $use_session;
        private readonly bool $require_login;
=======
        private $action;
>>>>>>> origin/BackEnd


        /**
         * @param bool $require_login if true, $use_session is automatically set to true
         */
<<<<<<< HEAD
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
=======
        public function __construct(
            string $resource,
            private readonly string $method,
            callable $action,
            private readonly bool $use_session=true, 
            private readonly bool $require_login = false)
        {
            $this->res = $resource;
            $method = \strtoupper($method);
            
            if(!\in_array($method,VALID_HTTP_METHOD,true)) throw new Exception("$method isn't valid http method");

            $this->action = $action;

            if($require_login) $use_session = true;
>>>>>>> origin/BackEnd
        }

        public function execute(string $method,string $requested_resource) : bool{
            if($requested_resource !== $this->res || $method !== $this->method) return false;

<<<<<<< HEAD
            if($this->require_login) define('REQUIRE_LOGIN',true);
            if($this->use_session){
                $status = session_status();
                if($status===PHP_SESSION_DISABLED){
                    http_response_code(500);
=======
            if($this->require_login) \define('REQUIRE_LOGIN',true);
            if($this->use_session){
                $status = \session_status();
                if($status===PHP_SESSION_DISABLED){
                    \http_response_code(500);
>>>>>>> origin/BackEnd
                    exit();
                }


                if($status === PHP_SESSION_NONE) session_initialize();
            }

            if($this->require_login && !Is_logged()){
<<<<<<< HEAD
                http_response_code(401);
                header('Content-Type: '.Construct_MIME(MIME['application']['json']));
=======
                \http_response_code(401);
                \header('Content-Type: '.Construct_MIME(MIME['application']['json']));
>>>>>>> origin/BackEnd
                echo json_encode(['success'=>-2]);
                exit();
            }
            

            Validate_request();
            ($this->action)();
            return true;
        }
    }

    function Route_start(array $all_route){
<<<<<<< HEAD
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $pathi = $_SERVER['PATH_INFO'];
        $required_resource = ($pathi===null||$pathi==='') ? null : strtolower($_SERVER['PATH_INFO']);
=======
        $method = \strtoupper($_SERVER['REQUEST_METHOD']);
        $pathi = $_SERVER['PATH_INFO'];
        $required_resource = ($pathi===null||$pathi==='') ? null : \strtolower($_SERVER['PATH_INFO']);
>>>>>>> origin/BackEnd

        foreach($all_route as $route){
            if ($route instanceof Route) {
                if($route->execute($method, $required_resource)) return;
            }
        }
<<<<<<< HEAD
        http_response_code(404);
        exit();
    }
?>
=======
        \http_response_code(404);
        \exit();
    }
>>>>>>> origin/BackEnd
