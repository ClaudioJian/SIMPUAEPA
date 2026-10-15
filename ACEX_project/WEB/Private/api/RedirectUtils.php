<?php
namespace ACEX_project\WEB\Private\Api;

require_once __DIR__ ."/../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;

use function ACEX_project\WEB\Private\Auth\session_initialize;
    use ltrim;

    /**
     * send redirect for client. need session to be active to able store information(don't matter if is guest or authenticated user).
     * Expect PRG pattern. Set cache related header before use this.
     * @param string $resource_name target resource name and url shown to client for router understand. leading '/' should be avoided.
     * @param array $data to be stored. if nonce, then only previoues request can acess this else will be globally avaible. Pass nothing if no data need to be send.
     * @param bool $nonce indicate if is acess from request who send or it's globally acessible via $_SESSION
     * @param int $http_code the redirect code can be send. if isn't in between 300 to 308, error is throwed
     * @param mixed $response_payload should able be json encoded
     */
    function Redirect(string $resource_name="",array $data=[],bool $nonce=false,int $http_code=303){
        if($data!==[] && session_status()===PHP_SESSION_NONE) session_initialize();
        if($resource_name==='') $resource_name = HOME_PAGE_LOCATION;
        header('Content-type: '.Construct_MIME(MIME['application']['json']));
        header('Location:'. "/SIMPUAEPA/".$resource_name);
        header("X-target-location:"."/SIMPUAEPA/".ltrim($resource_name,'/'));
        http_response_code($http_code);
        
        exit();
    }
?>