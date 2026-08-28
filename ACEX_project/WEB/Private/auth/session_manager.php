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
  | Validate_session() : validate status of current session, return Session_status                  |
  | Filter_Session(): return false when exit session if obsolete else if abandoned exit+clean data  |
  | Update_Session(): should call for every request to update activity of session                   |
  | New_session(): should be use every risk action, change permission, authetication                |
  | other function is used internally and should not be used in other context                       |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Auth;
    require_once __DIR__ . "/../Core/AppCommonVar.php";

    if (!defined('ENTRY_POINT_CHECKED')) {
        http_response_code(403);
        exit('Direct access not permitted');
    }
    //header('Cache-Control: no-cache, no-store, must-revalidate, private'); -> should put last moment when sending back

    enum Session_status: int{
        case active = 0;
        case obsolete = 1;
        case abandoned = 2;
        case inactive = 3;
        case disabled = 4;
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
     * if current session invalid, exit imedially for obsolete or abandoned, clean session data if abandoned
     * @return bool if valid then true else false
     */
    function Filter_session():bool{
        if(session_status()===PHP_SESSION_DISABLED || session_status()===PHP_SESSION_NONE) return false;
        $status = Validate_session();

        if($status===Session_status::abandoned) Nuke_session();

        if(Mark_ifobsolete_session() || $status===Session_status::obsolete) Exit_session();
        return true;
    }

    /**
     * call this every time if request is done
     * if is not disabled/inactive or is alredy obsolete/abandoned then renew time of active time
     */
    function Update_session():void{
        if(Filter_session()) $_SESSION['last_active_time'] = time();
    }

    /**
     * start brand new session or replace previous session marking as obsolete
     */
    function New_session(array $options=[]) : void{
        if(session_status()===PHP_SESSION_DISABLED) return;
        if(session_status()===PHP_SESSION_ACTIVE){
            //replace old id to new id
            session_regenerate_id(false);
            //force mark
            $_SESSION['obsolete_time'] = time();

            $current_sid = session_id();
            session_commit();

            session_id($current_sid);
        }
        session_start($options);
        //set new timestamp tracker for new session
        $_SESSION['last_active_time'] = time();
        if(!isset($_SESSION['absolute_time'])) $_SESSION['absolute_time'] = time();
        if(isset($_SESSION['obsolete_time'])) unset($_SESSION['obsolete_time']);
    }



    /**
     * mark current session obsolete if possible by tracking timestamp
     * @return bool true if obsolete else false
     */
    function Mark_ifobsolete_session() : bool{
        if(isset($_SESSION['obsolete_time'])) return true;

        $lastActive = $_SESSION['last_active_time'] ?? 0;
        $absTime = $_SESSION['absolute_time'] ?? 0;
        if(time() - $lastActive > SESSION_ACTIVE_TIME || time() - $absTime > SESSION_ABSOLUTE_TIME)
        {
            $_SESSION['obsolete_time'] = time();
            return true;
        }
        return false;
    }

    /**
     * immedially clean all session data and exit
     * TODO: if user use grant, flush their privilege
     */
    function Nuke_session():void{
        if(session_status()!== PHP_SESSION_ACTIVE) return;
        
        $_SESSION = [];
        session_destroy();
        $param = session_get_cookie_params();
        setcookie(session_name(),"",1,$param['path'],$param['domain'],$param['secure'],$param['httponly']);
        Exit_session();
    }

    /**
     * exit session without clean data
     */
    function Exit_session():void{
        if(session_status()!== PHP_SESSION_ACTIVE) return;
        //track here in log which is destroyed: user,ip,time
        header("HTTP/1.1 403 Forbidden");
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        exit();
    }

?>