<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core for Class user                                                                 |
  |  TODO: add filter for input and null ?? check, better code                                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Core;

require_once "/../Error/Error_code.php";
use ACEX_project\WEB\Private\Error\Error_Code;

use function ACEX_project\WEB\Auth\Generate_password;
use function ACEX_project\WEB\Auth\Strong_password;

use Exception;
use PDO;

require_once "/../Auth";


class User{
    public string $name;
    public string $email;
    //TODO: define type
    public $user_id;

    /**
     * insert data in object
     * auto disconnect from database
     * 
     * @return object where contain all result of query
     * @return array
     * - sucess: ['sucess'=>DB_INSERT,'result'=>obj row/record finded] when sign up sucess
     * - ['sucess'=>USER_NOT_LOGGED,'description'=>'string'] if not logged
     * - ['sucess'=>USER_ALREDY_EXIST,'description'=>string] if user alredy exist in database
     * - ['sucess'=>DB_ERR_INSERT,'description'=>string] if cannot create in database
    */
    public function __construct(string $user_name,string $email,$user_id)
    {
        $this->name = $user_name;
        $this->email = $email;
        $this->user_id = $user_id;
    }

    public static function construct_from_record($record) :self
    {
        //TODO: find name for each variable
        return new self($record->userName,$record->email,$record->user_id);
    }

    /**
     * create new user and register, start database connection, after done, disconnect from database
     * 
     * @return object where contain all result of query
     * @return array
     * - sucess: ['sucess'=>DB_INSERT,'result'=>obj of user created] when sign up sucess
     * - ['sucess'=>USER_NOT_LOGGED,'description'=>'string'] if not logged
     * - ['sucess'=>USER_ALREDY_EXIST,'description'=>string] if user alredy exist in database
     * - ['sucess'=>DB_ERR_INSERT,'description'=>string] if cannot create in database
    */
    public static function Sign_up(string $user_name,string $password,string $email,bool $should_create_password=false){
        try{
            $conn = connect_database();
            if(is_array($conn) && $conn['sucess']===Error_Code::db_connection) return ['sucess'=>Error_Code::db_connection,'description'=>'failed to connect database'];

            //find if user name alredy taken
            if(self::Alredy_exist($user_name,$conn)) return ['sucess'=>Error_Code::user_alredy_exist,'description'=>'user alredy exist'];
            if((!isset($password)||$password==='')  && $should_create_password) $password = Generate_password();
            if(!Strong_password($password)) return ['sucess'=>Error_Code::password_weak,'description'=>'password is too weak'];

            self::Register_user($user_name,$password,$email,$conn);
        }catch(Exception $e){
            return ['sucess'=>Error_Code::db_insert,'description'=>$e->getMessage()];
        }

        //return entire row of that user, to set information into session
        $result = self::Retrieve_user_data($user_name,$conn);
        $user = self::construct_from_record($result);

        $conn = NULL;

        return ['sucess'=>Sucess_code::db_insert,'result'=>$user];
    }

    /**
     * login and  start database, after done, auto disconnect
     * 
     * @return object where contain all result of query
     * @return array
     * - sucess: ['sucess'=>DB_INSERT,'result'=>obj of user created] when sign up sucess
     * - ['sucess'=>Error_Code::user_alredy_logged,'description'=>'string','result'=>current user] if alredy logged
     * - ['sucess'=>Error_Code::user_wrong_credit,'password'=>bool,'email'=>bool] if user exist but data wrong, the bool indicate true if that data wrong
     * - ['sucess'=>Error_Code::user_not_found,'description'=>string] user not finded in database
    */
    public static function Login(string $user_name,string $password,string $email){
        $conn = connect_database();
        if(is_array($conn) && $conn['sucess']===Error_Code::db_connection) return ['sucess'=>Error_Code::db_connection,'description'=>'failed to connect database'];

        if(self::Logged()) {
            $conn = null;
            return 
            ['sucess'=>Error_Code::user_alredy_logged,
                'description'=>'User is alredy logged',
                'result'=>$_SESSION['user']
            ];}
        
        $response = self::Can_login($user_name,$password,$email,$conn);
        if($response['sucess'] >0) {
            //store credential to session
            //TODO: id
            $user = new self($user_name,$email,$response['user_id']);
            $_SESSION['user'] = $user;
            $response['result'] = $user;
        }
        $conn = null;
        return $response;
    }

    private static function Register_user(string $user_name,string $password,string $email,PDO $conn){
        //search row with same name
        //TODO: find table name for user
        $query = "INSERT INTO ___(name,senha,email) VALUES(:user_name,:password,:email)";
        //query
        $smtm = $conn->prepare($query);
        $smtm->bindParam(':user_name',$user_name);
        $smtm->bindParam(':password',$password);
        $smtm->bindParam(':email',$email);
        if(!($smtm->execute())) throw new Exception("Usuário não foi criado!");
    }
    /**
     * delete user from database
     * 
     * auto disconnect from database
     * @param PDO $conn PDO object pointer to connection of database, can be finded by return value of connect_database()
     * @return array
     * - sucess: ['sucess'=>DB_DELETE,'result'=>obj row/record finded] when deleted
     * - ['sucess'=>USER_NOT_LOGGED,'description'=>'string'] if not logged
     * - ['sucess'=>USER_NOT_FIND,'description'=>'string'] if user not existed
     * - ['sucess'=>DB_ERR_DELETE,'description'=>description] database fail to delete this user
     */
    public static function delete_user(PDO $conn){
        if(!self::logged()) return ['sucess'=>Error_Code::user_not_logged,'description'=>'not logged'];
        if(!user_alredy_exist($_SESSION['user'],$conn)) return ['sucess'=>Error_Code::user_not_found,'description'=>'usuário não existe'];
        try{
            //search row with this user
            $query = "DELETE FROM data WHERE user_id=:user_id";
            //query
            $smtm = $conn->prepare($query);
            $smtm->bindParam(':user_id',$_SESSION['user']);
            if(!($smtm->execute())) throw new Exception("Database não consegue deletar o usuário!");
        }catch(Exception $e){
            $conn = NULL;
            return ['sucess'=>Error_Code::db_delete,'description'=>$e->getMessage()];
        }

        $conn = NULL;

        return ['sucess'=>Sucess_code::db_delete,'description'=>'user deleted'];
    }

    /**
     * check if user can login.
     * 
     * this function do query to find user. 
     * auto disconnect if user not finded in db.
     * 
     * @param PDO $conn Database connection
     * 
     * @return object row of user in format: {column_name=value} 
     * @return array
     * - ['sucess'=>Sucess_code::user_found,'result'=>object row finded,'db'=>$conn] if all crendential matches
     * - ['sucess'=>Error_Code::user_wrong_credit,'password'=>bool,'email'=>bool] if user exist but data wrong, the bool indicate true if that data wrong
     * - ['sucess'=>Error_Code::user_not_found,'description'=>string] user not finded in database
     */
    private static function Can_login(string $user_name,string $password,string $email,PDO $conn){
        $result = self::Retrieve_user_data($user_name,$conn);
        
        if($result){
            $err = ['password'=>false,'email'=>false];

            //TODO: add more security toward password
            if($result->senha != $password) $err['password'] = true;
            if($result->email != $email) $err['email'] = true;
            //return immedialy if there has different value
            foreach($err as $e){
                if($e) {
                    $err['sucess']=Error_Code::user_wrong_credit;
                    return $err;
                }
            }
            return ['sucess'=>Sucess_code::user_found,'result'=>$result];
        }
        $conn = NULL;
        return ['sucess'=>Error_Code::user_not_found, "description"=>'user don\'t exist'];
    }

    /**
     * retrieve all data of table data for that user
     * @param PDO $conn Database connection
     * @return object where contain all result of query
     */
    private static function Retrieve_user_data(string $user_name,PDO $conn){
        //search row with same name
        $query = "SELECT * FROM data WHERE name=:user_name";
        //query
        $smtm = $conn->prepare($query);
        $smtm->bindParam(':user_name',$user_name);
        $smtm->execute();

        //the result will be in obj
        return $smtm->fetch(PDO::FETCH_OBJ);
    }

    /**
     * find if user name is alredy taken
     * @param PDO $conn Database connection.
     * @return bool var
     * - **true**
     * - **false** : new user.
     */
    private static function Alredy_exist(string $user_name,PDO $conn){
        $query = "SELECT 1 FROM data WHERE name=:user_name";
        $smtm = $conn->prepare($query);
        $smtm->bindParam(':user_name',$user_name);
        $smtm->execute();

        return $smtm->rowCount()>0;
    }

    /**
     * check if user logged
     * @return bool true if user is logged else false
     * 
     */
    private static function Logged() : bool{ return isset($_SESSION['user']);}
};
?>