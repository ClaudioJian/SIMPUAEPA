<?php 
namespace ACEX_project\WEB\Private\Api;
/**
 * send redirect for client. need session to be active to able store information(don't matter if is guest or authenticated user).
 * Expect PRG pattern. Set cache related header before use this.
 * @param string $resource_name target resource name and url shown to client for router understand. leading '/' should be avoided.
 * @param array $data to be stored. if nonce, then only previoues request can acess this else will be globally avaible. Pass nothing if no data need to be send.
 * @param bool $nonce indicate if is acess from request who send or it's globally acessible via $_SESSION
 * @param int $http_code the redirect code can be send. if isn't in between 300 to 308, error is throwed
 */
function Redirect(string $resource_name,array $data=[],bool $nonce=false,int $http_code=303){
    $nonce_token = $nonce ? bin2hex(random_bytes(16)) : "";
    $nonce_param_txt = $nonce ? "?nonce=".$nonce_token : "";
    header('Location:'. "/SIMPUAEPA/".$resource_name.$nonce_param_txt);
    http_response_code($http_code);

    //storing data in session
    
    exit();
}
?>