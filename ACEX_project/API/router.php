<?php 

namespace ACEX_project\API;
    require_once __DIR__ ."/router_class.php";
    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";

    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";

    require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Validate_request;

    require_once __DIR__ . "/../WEB/Private/Api/RedirectUtils.php";    

    require_once __DIR__ . "/../WEB/Private/Auth/User/Create_account.php";
    use function ACEX_project\WEB\Private\Auth\User\Create_account;
    

    ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    header('x-content-type-options:nosniff');

    Validate_request();

    //routing logic
    $route = array(
        new Route("/subscribe",'POST',function(){
            Create_account();
        }),
        new Route("/testawawa.php",'GET',function(){
            echo 'argon2id support:' . defined('PASSWORD_ARGON2ID');
            exit();
        })
    );

    Route_start($route);
?>