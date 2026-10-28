<?php 

namespace ACEX_project\API;
    error_log('hi');


    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";  


    require_once __DIR__ ."/router_class.php";

    require_once __DIR__ . "/../WEB/Private/Auth/User/Create_account.php";

    use function ACEX_project\WEB\Private\Auth\User\Create_account;

    require_once __DIR__ . "/../WEB/Private/Auth/User/User_data.php";

    require_once __DIR__ . "/../WEB/Private/Auth/User/login.php";


    require_once __DIR__ . "/../WEB/Private/Auth/User/Logout.php";
    use function ACEX_project\WEB\Private\Auth\User\Logout;
    use function ACEX_project\WEB\Private\Auth\User\Upload_map_img;
    use function ACEX_project\WEB\Private\Auth\User\Upload_profile;

    require_once __DIR__ . "/../WEB/Private/Auth/User/Upload.php";



    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Handle_CSRF_GET;
    use function ACEX_project\WEB\Private\Auth\User\Login;
    use function ACEX_project\WEB\Private\Auth\User\Send_user_data;

    require_once __DIR__ . "/../WEB/Private/Core/Render/html/Display_img.php";
    use function ACEX_project\WEB\Private\Core\Render\html\Send_img;

    \ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \header('x-content-type-options:nosniff');



    //routing logic
    $route = [
        new Route("/csrf",'POST',Handle_CSRF_GET(...),bypass_rate_limit:true),
        //display image
        new Route('/image/*/*','GET',Send_img(...)),
        //Get user data
        new Route('/user/*','GET', Send_user_data(...)),
        //Get complain data
        new Route("/subscribe",'POST',Create_account(...),require_nonce:true, rate_limit_option:RATE_ENDPOINT_SUBSCRIBE),
        new Route("/login",'POST',Login(...), rate_limit_option:RATE_ENDPOINT_LOGIN),
        new Route('/logout',"POST",Logout(...), require_login:true),
        new Route('/image/profile',"POST",Upload_profile(...),require_login:true, rate_limit_option:RATE_ENDPOINT_IMG_UPLOAD),
        new Route('/image/map',"POST",Upload_map_img(...),require_login:true, rate_limit_option:RATE_ENDPOINT_IMG_UPLOAD),
        
    ];


    Route_start($route);