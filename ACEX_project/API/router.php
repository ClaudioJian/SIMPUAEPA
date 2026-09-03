<?php 

namespace ACEX_project\API;
    require_once __DIR__ . "/../WEB/Private/Core/Security/CSRF_manager.php";
    require_once __DIR__ . "/../WEB/Private/Core/AppCommonVar.php";
    require_once __DIR__ ."/router_class.php";

    ini_set('session.cookie_lifetime',(string)(SESSION_ABSOLUTE_TIME+10));
    ini_set('session.gc_maxlifetime',(string)(SESSION_ABSOLUTE_TIME+10));

    require_once __DIR__ . "/../WEB/Private/Core/Security/Request_manager.php";

    //routing logic
    $route = array(
        new Route("/test",'POST',function(){
            header('Location: /SIMPUAEPA/testawawa.php');
            http_response_code(303);
            exit();
        }),
        new Route("/testawawa.php",'GET',function(){
            echo 'YES';
            exit();
        })
    );

    Route_start($route);


?>