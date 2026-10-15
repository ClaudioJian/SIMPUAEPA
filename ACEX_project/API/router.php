<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";  


    require_once __DIR__ ."/router_class.php";

    require_once __DIR__ . "/../WEB/Private/Auth/User/Create_account.php";
    use function ACEX_project\WEB\Private\Auth\User\Create_account;

    require_once __DIR__ . "/../WEB/Private/Auth/User/login.php";
    use function ACEX_project\WEB\Private\Auth\User\Login;

    require_once __DIR__ . "/../WEB/Private/Auth/User/Logout.php";
    use function ACEX_project\WEB\Private\Auth\User\Logout;
    use function ACEX_project\WEB\Private\Auth\User\Upload_map_img;
    use function ACEX_project\WEB\Private\Auth\User\Upload_profile;

    require_once __DIR__ . "/../WEB/Private/Auth/User/Upload.php";


    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Handle_CSRF_GET;


    \ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \header('x-content-type-options:nosniff');


    //routing logic
    $route = [
        new Route("/csrf",'POST',Handle_CSRF_GET(...)),
        new Route("/subscribe",'POST',Create_account(...)),
        new Route("/login",'POST',Login(...)),
        new Route('/logout',"POST",Logout(...), require_login:true),
        new Route('/image/profile',"POST",Upload_profile(...),require_login:true),
        new Route('/image/map',"POST",Upload_map_img(...),require_login:true),
    ];


    Route_start($route);