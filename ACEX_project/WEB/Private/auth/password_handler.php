<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Handler for password                                                                            |
  |                                                                                                 |
  | Validate if password is strong or no                                                            |
  | Generate password                                                                               |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Auth;
    /**
     * to be considered strong: >15 char(no MFA)
     */
    function Strong_password(string $password) : bool{
      //should consider to use some library to check. however, no time to implement
      return strlen($password) > 15;
    };

    function Generate_password(){

    };

    /**
     * !!! Please aware when upgrading from bcrypt to Argon2id(scrypt not supported by php), this function only see if PASSWORD_ARGON2ID is defined, so you need to make user to insert new password with proper MFA or hash the old password and change to Argon2id version instead in next login.
     * When using this function, note no pepper is used because the secret manager is not implanted due to time, so please aware using bcrypt is not secure! Also when bcrypt is used, the password is pre-hashed with base64(hash(sha384,password)), unfortunaty, without scret manager no server secret can be used
     * Before passing to password_hash, fast hash is done to avoid password lenght type attack
    */
    function Secure_hash_password(string $passwd, bool $fast_hash = true):string | null{
      //avoid truncate password and make it fixed size, to secure this, should use pepper as well
      if($fast_hash) $passwd = base64_encode(hash('sha256',$passwd));
      if(defined("PASSWORD_ARGON2ID")) {
        $passwd = password_hash($passwd,PASSWORD_ARGON2ID,['memory_cost'=>19456,'time_cost'=>2]);
      }else{
        $hashed_passwd = base64_encode(hash('sha384',$passwd));
        $passwd = password_hash($hashed_passwd,PASSWORD_DEFAULT);
      }
      return $passwd;
    }

    /**
     * !!! Please aware when upgrading from bcrypt to Argon2id(scrypt not supported by php), this function only see if PASSWORD_ARGON2ID is defined, so you need to make user to insert new password with proper MFA or hash the old password and change to Argon2id version instead in next login.
     * When using this function, note no pepper is used because the secret manager is not implanted due to time, so please aware using bcrypt is not secure! Also when bcrypt is used, the password is pre-hashed with base64(hash(sha384,password)), unfortunaty, without scret manager no server secret can be used
     * Before passing to password_hash, fast hash is done to avoid password lenght type attack
     * @param string $real_passwd real password from database
     */
    function Secure_password_verify(string $passwd, string $real_passwd) : bool{
      $passwd = base64_encode(hash('sha256',$passwd));

      return password_verify($passwd, $real_passwd);
    }
?>