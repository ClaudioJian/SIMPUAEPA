<?php 
/*+-------------------------------------------------------------------------------------------------+
  |                                                                                                 |
  | Here puts function to manage session to prevent attack like:                                    |
  | session fixation : attacker force other user use provided session id to log as victim           |
  | session prediction/steal: same as above, but instead get other's one instead inject             |
  | we don't expire peridiocally because time and usability                                         |
  |                                                                                                 |
  |                                       Table of content                                          |     
  |                                                                                                 | 
  | Session_status: enum that mark status of session to determine is valid, disabled                |
  | Session_initialized: check if current session originated from previous                          |
  | Validate_session() : validate status of current session, return Session_status                  |
  | Filter_Session(): return false when exit session if obsolete else if abandoned exit+clean data  |
  | Update_Session(): should call for every request to update activity of session                   |
  | New_session(): should be use every risk action, change permission, authetication                |
  | session_initialize(): used when the request need session based action                              |
  | other function is used internally and should not be used in other context                       |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Auth;

    require_once __DIR__ . "/../Error/Error_manager.php";
    use function ACEX_project\WEB\Private\Error\Log_internal;

    require_once __DIR__ . "/../Error/Error_code.php";
    use ACEX_project\WEB\Private\Error\Error_code;
    use ACEX_project\WEB\Private\Error\Error_condition;
    use ACEX_project\WEB\Private\Error\Error_domain;
    use ACEX_project\WEB\Private\Error\Log_level;
    use ACEX_project\WEB\Private\Error\Resource_code;

    
    require_once __DIR__ . "/../Core/AppCommonVar.php";

    require_once __DIR__ . "/User/Logout.php";
    use function ACEX_project\WEB\Private\Auth\User\Internal_Logout;

    require_once __DIR__ . "/../Core/Security/CSRF_manager.php";
    use function ACEX_project\WEB\Private\Core\Security\CSRF_generate;
    

    require_once __DIR__ . "/../Core/MIME_type.php";
    use function ACEX_project\WEB\Private\Core\Construct_MIME;

    $sequence = [];
    //header('Cache-Control: no-cache, no-store, must-revalidate, private'); -> should put last moment when sending back

    enum Session_status: int{
        case active = 0;
        case obsolete = 1;
        case abandoned = 2;
        case inactive = 3;
        case disabled = 4;
    }

    /**
     * continue previous session or create new session
    */
    function session_initialize(){
        if(session_status()===PHP_SESSION_NONE && !session_start()) {
            http_response_code(500);
            exit();
        }


        if(Session_initialized() && Filter_session()) Update_session();
        else if(!New_session()){
            http_response_code(500);
            exit();
        }
    }

    /**
     * check current session is from previous or is actually completly new
     */
    function Session_initialized():bool{
        return isset($_SESSION['last_active_time']);
    }

    function Validate_session() : Session_status{
        if(session_status()===PHP_SESSION_DISABLED) return Session_status::disabled;
        if(session_status()===PHP_SESSION_NONE) return Session_status::inactive;
        if(isset($_SESSION['obsolete_time'])){
            if(time() - $_SESSION['obsolete_time'] > SESSION_OBSOLETE_MAXLIFE) return Session_status::abandoned;
            else return Session_status::obsolete;
        }
        return Session_status::active;
    }

    /**
     * if current session invalid, exit imedially for obsolete or abandoned(old session is destroyed), clean session data if abandoned
     * @return bool if valid then true else false
     */
    function Filter_session():bool{
        if(session_status()===PHP_SESSION_DISABLED || session_status()===PHP_SESSION_NONE) return false;
        $status = Validate_session();

        $expired = isset($_SESSION['expired']) && $_SESSION['expired'] !== 0 && defined('REQUIRE_LOGIN') && REQUIRE_LOGIN;

        if($expired || $status===Session_status::abandoned) Nuke_session();


        if(Mark_ifobsolete_session() || $status===Session_status::obsolete) Exit_session();

        return true;
    }

    /**
     * call this every time if request is done
     * if is not disabled/inactive or is alredy obsolete/abandoned then renew time of active time
     */
    function Update_session():void{
        $_SESSION['last_active_time'] = time();
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        Monitor_session('ip',$ip);
        Monitor_session('user_agent',$user_agent);
    }

    function Monitor_session(string $name,mixed $compared_value){
        $_SESSION['session_anomaly'] ??= [];
        if(isset($_SESSION[$name]) && $_SESSION[$name]!== $compared_value) {
            $_SESSION['anomaly'][] = $name;
            Log_internal(
                new Error_code(Error_domain::request,Resource_code::user,Error_condition::abnomaly),
                Log_level::warning,
                "Abnomaly detect: " . $name . " is different in middle of session - [previous:$_SESSION[$name] | current : $compared_value]"
            );
        }
        $_SESSION[$name] = $compared_value;
    }

    /**
     * start brand new session or replace previous session marking as obsolete
     * csrf token is generated.
     */
    function New_session(array $options=[]) : bool{
        if(session_status()===PHP_SESSION_DISABLED) return false;
        $replace = session_status()===PHP_SESSION_ACTIVE && Session_initialized();
        if($replace){
            //replace old id to new id
            session_regenerate_id(false);
            //force mark
            $_SESSION['obsolete_time'] = time();

            $current_sid = session_id();
            session_commit();

            session_id($current_sid);
        }
        $sucess = true;
        if(session_status()===PHP_SESSION_NONE) $sucess = session_start($options);
        if($replace) CSRF_generate();

        //set new timestamp tracker for new session
        $_SESSION['last_active_time'] = time();
        
        $_SESSION['absolute_time'] = time();
        if(isset($_SESSION['obsolete_time'])) unset($_SESSION['obsolete_time']);          
        
        return $sucess;
    }



    /**
     * mark current session obsolete if possible by tracking timestamp
     * @return bool true if obsolete else false
     */
    function Mark_ifobsolete_session() : bool{
        if(isset($_SESSION['obsolete_time'])) return true;

        $reason = Session_expired();
        if($reason===1 || $reason===2)
        {
            $_SESSION['obsolete_time'] = time();
            return true;
        }
        return false;
    }

    /**
     * exit if is end point require login, renewing session cleaning all data and logout user. 
     */
    function Nuke_session():void{
        if(session_status()!== PHP_SESSION_ACTIVE) return;
        $_SESSION['expired'] = Session_expired();
        
        if(!defined('REQUIRE_LOGIN') || !REQUIRE_LOGIN) {
            New_session();
            return;
        }
        
        
        $_SESSION = [];
        session_destroy();
        Log_internal(
            new Error_code(Error_domain::request,Resource_code::session,Error_condition::expired),
            Log_level::warning,
            "Invalid request to obsolete session"
        );
        
        Exit_session(false);
    }

    /**
     * exit if is end point require login, renewing session without clean data and logout user.  Since non logged session have no value to steal, the request is still done with renewing the session
     */
    function Exit_session(bool $new_session = true):void{
        if(session_status()!== PHP_SESSION_ACTIVE) return;
        $_SESSION['expired'] = Session_expired();
        
        if(!defined('REQUIRE_LOGIN') || !REQUIRE_LOGIN) {
            if($new_session) New_session();
            return;
        }
        $response = Session_logout();

        //track here in log which is destroyed: user,ip,time


        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Clear-Site-Data: cache');


        if($response===null) return;

        header('Content-Type: '.Construct_MIME(MIME['application']['json']));
        http_response_code(401);
        echo json_encode($response);
        exit();
    }

    function Session_logout() : array | null{
        $response = Internal_Logout();
        if($response===null) return null;
        
        $reason = Session_expired();

        if($reason===0 && isset($_SESSION['expired']) && $_SESSION['expired']!==0) $reason = $_SESSION['expired'];
        return [...$response, 'reason'=>$reason];
    }

    /**
     * @return 0 for not expired, 1 for inactivity and 2 for abs timeout
     * 
     */
    function Session_expired() : int{
        $lastActive = $_SESSION['last_active_time'] ?? 0;
        $absTime = $_SESSION['absolute_time'] ?? 0;
        
        if(time() - $lastActive > SESSION_ACTIVE_TIME) return 1;
        if(time() - $absTime > SESSION_ABSOLUTE_TIME) return 2;

        return 0;
    }

?>