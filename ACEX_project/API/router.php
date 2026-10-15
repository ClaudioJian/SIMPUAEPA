<?php 

namespace ACEX_project\API;
    require_once __DIR__ ."/router_class.php";
    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";  

    require_once __DIR__ . "/../WEB/Private/Auth/User/Create_account.php";
    use function ACEX_project\WEB\Private\Auth\User\Create_account;

    require_once __DIR__ . "/../WEB/Private/Auth/User/login.php";
    use function ACEX_project\WEB\Private\Auth\User\Login;

    require_once __DIR__ . "/../WEB/Private/Auth/User/Logout.php";
    use function ACEX_project\WEB\Private\Auth\User\Logout;

    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Handle_CSRF_GET;

    require_once __DIR__ . "/../WEB/Private/Core/Security/File_upload.php";
use function ACEX_project\WEB\Private\Core\Security\Validate_uploaded_file;

    ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    header('x-content-type-options:nosniff');


    //routing logic
    $route = array(
        new Route("/csrf",'POST',function(){
            Handle_CSRF_GET();
        }),
        new Route("/subscribe",'POST',function(){
            Create_account();
        }),
        new Route("/login",'POST',function(){
            Login();
        }),
        new Route('/logout',"POST",function(){
            Logout();
        },require_login:true),

        new Route('/fileuploadtest',"POST",function(){
            Validate_uploaded_file(['png']);
        })
    );

    Route_start($route);
?>