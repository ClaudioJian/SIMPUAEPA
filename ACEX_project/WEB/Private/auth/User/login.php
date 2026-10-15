<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Auth\User;





use Exception;
use Throwable;

require_once __DIR__ . "/../../Api/database/database_manager.php";
use function ACEX_project\WEB\Private\Api\database\Connect_database;
use function ACEX_project\WEB\Private\Api\database\Handle_query_fail;

require_once __DIR__ . "/../../Api/database/database_dictionary.php";
use ACEX_project\WEB\Private\Api\database\All_tables;
use ACEX_project\WEB\Private\Api\database\tb_user_columns;


require_once __DIR__ ."/../../Core/MIME_type.php";
use function ACEX_project\WEB\Private\Core\Construct_MIME;

require_once __DIR__ . "/../../Core/Security/Request_manager.php";
use function ACEX_project\WEB\Private\Core\Security\Get_request_body;

require_once __DIR__ . '/../session_manager.php';
use function ACEX_project\WEB\Private\Auth\New_session;

require_once __DIR__ . '/../password_handler.php';
use function ACEX_project\WEB\Private\Auth\Secure_password_verify;

require_once __DIR__ . "/User_data.php";


    function Login(array $request_body = []){
        if(Is_logged()) {
            header('Content-type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode(['success'=>1,'uid'=>$_SESSION['uid']]);
            exit();
        }
        
        $conn = null;
        try{
            if($request_body===[]) $request_body = Get_request_body();

            $email = $request_body['email'] ?? '';
            $password = $request_body['password'] ?? '';

            Validate_enroll_data($email,$password);
            Get_user_data_authorized();

            $uid = Validate_login_credential($email,$password);
            Internal_login($uid);
        }catch(Throwable $e){
            Handle_query_fail($e,$conn);
        }

        header('Content-type: '.Construct_MIME(MIME['application']['json']));
        echo json_encode(['success'=>0,'uid'=>$_SESSION['uid']]);
        exit();
    }

    function Validate_login_credential(string $email, string $raw_passwd):int{
        $response = ['success'=>0,'email'=>4,'password'=>4];
        $conn = Connect_database();

        $query = "SELECT " .
            implode(", ",[tb_user_columns::id_user->name, tb_user_columns::email->name, tb_user_columns::password->name]).
            " FROM ". All_tables::user->name. 
            " WHERE ". tb_user_columns::email->name ." = :email"
        ;
        $smtm = $conn->prepare($query);
        if($smtm===false) throw new Exception('in Create_account(): Database server failed to prepare statement for select query');

        $smtm->execute([':email'=>$email]);

        if($smtm->rowCount()<1) {
            http_response_code(400);
            header('Content-Type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode([...$response,'email'=>2,'success'=>-1]);
            exit();
        }

        $row = $smtm->fetch(\PDO::FETCH_ASSOC);
        $stored_passwd = $row[tb_user_columns::password->name];

        if(!Secure_password_verify($raw_passwd,$stored_passwd)){
            http_response_code(400);
            header('Content-Type: '.Construct_MIME(MIME['application']['json']));
            echo json_encode([...$response,'success'=>-1,'password'=>2]);
            exit();
        }

        return $row[tb_user_columns::id_user->name];
    }


    function Internal_login(int $uid){
        New_session();
        $_SESSION['uid'] = $uid;
    }

    function Is_logged():bool{
        return isset($_SESSION['uid']);
    }
?>