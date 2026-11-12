<?php 

namespace ACEX_project\API;

use ACEX_project\WEB\Private\complain\Complain_data_fields;
use ACEX_project\WEB\Private\complain\Get_complain_Err;
use ACEX_project\WEB\Private\complain\GET_Complain_fields;

    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";  


    require_once __DIR__ ."/router_class.php";

    require_once __DIR__ . "/../WEB/Private/Auth/User/Create_account.php";

    require_once __DIR__ . "/../WEB/Private/auth/User/Authentication.php";
    use function ACEX_project\WEB\Private\Auth\User\Create_account;
    use function ACEX_project\WEB\Private\Auth\User\Enroll_err_handler;
    use function ACEX_project\WEB\Private\Auth\User\Enroll_ruleset;

    require_once __DIR__ . "/../WEB/Private/Auth/User/User_data.php";

    require_once __DIR__ . "/../WEB/Private/Auth/User/login.php";


    require_once __DIR__ . "/../WEB/Private/Auth/User/Logout.php";
    use function ACEX_project\WEB\Private\Auth\User\Logout;
    use function ACEX_project\WEB\Private\Auth\User\Upload_profile;

    require_once __DIR__ . "/../WEB/Private/Auth/User/Upload.php";



    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\Handle_CSRF_GET;
    use function ACEX_project\WEB\Private\Auth\User\Login;
    use function ACEX_project\WEB\Private\Auth\User\Profile_upload_ruleset;
    use function ACEX_project\WEB\Private\Auth\User\Send_user_data;
    use function ACEX_project\WEB\Private\Auth\User\Send_user_data_ruleset;
    use function ACEX_project\WEB\Private\complain\Error_handler as Complain_Upload_err_handler;

    require_once __DIR__ . "/../WEB/Private/complain/Get_complains.php";
    use function ACEX_project\WEB\Private\complain\GET_complain;
    
    require_once __DIR__ . "/../WEB/Private/complain/upload_complain.php";
    use function ACEX_project\WEB\Private\complain\Upload_complain;

    require_once __DIR__ . "/../WEB/Private/Core/Render/html/Display_img.php";
    use function ACEX_project\WEB\Private\Core\Render\html\Send_img;


    

    \ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    \header('x-content-type-options:nosniff');



    //routing logic
    $route = [
        new Route("/csrf",'POST',Handle_CSRF_GET(...),bypass_rate_limit:true),
        //display image
        new Route('/image/*/*','GET',Send_img(...),bypass_rate_limit:true),
        //Get user data
        new Route('/user/*','GET', Send_user_data(...), ruleset:Send_user_data_ruleset()),
        //Get complain data
        new Route('/complain','GET', GET_complain(...), ruleset:GET_Complain_fields::sanitization_rule(), invalid_field_handler:Get_complain_Err::Error_handler(...)),
        new Route("/subscribe",'POST',Create_account(...),require_nonce:true, rate_limit_option:RATE_ENDPOINT_SUBSCRIBE,
            ruleset:Enroll_ruleset(false),
            invalid_field_handler:fn(int $err_code, string $name, string $err_msg, int $custom_err) => Enroll_err_handler($err_code,$name,$err_msg,$custom_err)
        ),
        new Route("/login",'POST',Login(...), rate_limit_option:RATE_ENDPOINT_LOGIN, 
            ruleset:Enroll_ruleset(), 
            invalid_field_handler:fn(int $err_code, string $name, string $err_msg, int $custom_err) => Enroll_err_handler($err_code,$name,$err_msg,$custom_err,true)
        ),
        new Route('/logout',"POST",Logout(...), require_login:true),
        new Route('/image/profile',"POST",Upload_profile(...),require_login:true, rate_limit_option:RATE_ENDPOINT_IMG_UPLOAD, ruleset:Profile_upload_ruleset()),
        new Route('/upload/complain',"POST",Upload_complain(...),require_login:true, require_nonce:true, ruleset: Complain_data_fields::sanitization_rule(),invalid_field_handler:Complain_Upload_err_handler(...)),
    ];


    Route_start($route);