<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Core Configuration & Global Constants                                               |
  |                                                                                                 |
  | Initializes environment variables, defines database connection constants, and provides          |
  | global success status enums for operations.                                                     |
  | you should require this file if need these constraint                                           |
  | WARNING: when require this, if env file is malconfigured, return http code 500                  |
  |                                                                                                 |
  |                                        Table of content                                         |
  | Sucess_code:enum                                                                                |
  | user_found, db_insert, db_update, db_delete, db_select                                          |
  | Constant per request: const                                                                     |
  | DB_NAME :string, DB_PASSWORD:string, HOST: string, SERVER_USER:string                           |
  | DB_PORT: int, SESSION_OBSOLETE_MAXLIFE:int, SESSION_ACTIVE_TIME: int                            |
  | CSRF_TOKEN_MAXLIFE: int, CSRF_TOKEN_VALIDATE_METHOD : array<string>                             |
  +-------------------------------------------------------------------------------------------------+
*/

namespace ACEX_project\WEB\Private\Core;
    /**
     * define all usable mime type and corresponded subtype.
     * @example MIME['application']['json']
     * @var array{
     *     application: list{json:'json'},
     *     text: list{html:'html',css:'css'}
     * }
     */
    define('MIME',
        [
            'application' => [
                'json'=>'json'
            ],
            'text'=>[
                'html'=>'html',
                'css' => 'css'
            ]
        ]
    );

    /**
     * validate if mime is registered in constant MIME, have valid format and the subtype is in type.
     * @param string $full_MIME expect TYPE/SUBTYPE, no option is validated
     */
    function Validate_MIME(string $full_MIME):bool{
        $strip_option_mime = explode(';',$full_MIME)[0];
        $splited_mime = explode('/',$strip_option_mime);
        if(count($splited_mime)!=2) return false;

        $type = strtolower(trim($splited_mime[0]));
        $subtype = strtolower(trim($splited_mime[1]));

        return isset(MIME[$type][$subtype]);
    }

    /**
     * construct full MIME type without option.
     * Use MIME['type']['subtype'] to avoid mistake.
     * @return string|null success example:application/json. null when type/subtring is not specified
     */
    function Construct_MIME(string $subtype):string|null{
        $subtype = strtolower(trim($subtype));
        $target_type = "";
        foreach(MIME as $type=>$_subtype){
            if(in_array($subtype,$_subtype)) $target_type = $type;
        }
        
        if($target_type==='') return null;
        
        return $target_type.'/'.$subtype;
    }
?>