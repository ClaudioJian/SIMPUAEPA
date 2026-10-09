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

    enum Avaible_extension{
        case png ;
        case jpg;
        case json;
        case html;
        case txt;
        case css;
        case webp;
        case unknown;

        public static function Convert(string $extension) : Avaible_extension{
            $extension = strtolower(trim($extension));
            $all_case = self::cases();
            foreach($all_case as $ext){
                if($extension === $ext->name) return $ext;
            }
            return self::unknown;
        }
    };

    /**
     * define all usable mime type and corresponded subtype.
     * The key is subtype and value is extension
     * @example MIME['application']['json']
     * @var array{
     *     application: list{json:'json'},
     *     image: list{png:'png',jpeg:'jpg'},
     *     text: list{html:'html',css:'css'}
     * }
     */
    define('MIME',
        [
            'application'=>[
                'json'=>'json'
            ],
            'image' => [
                'png'=>'png',
                'jpeg'=>'jpeg'
            ],
            'text'=>[
                'html'=>'jpeg',
                'css'=>'css',
                'plain'=>'plain'
            ]
        ]
    );

    define('MIME2EXT',
        [
            'application'=>[
                'json'=>Avaible_extension::json
            ],
            'image' => [
                'png'=>Avaible_extension::png,
                'jpeg'=>Avaible_extension::jpg,
                'webp'=>Avaible_extension::webp
            ],
            'text'=>[
                'html'=>Avaible_extension::html,
                'css' => Avaible_extension::css,
                'plain' => Avaible_extension::txt
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
     * return the full mime from extension
     */
    function Find_MIME_from_extension(Avaible_extension $extension):string|null{
        foreach(MIME2EXT as $type=>$subtypes){
            $subtype = array_search($extension,$subtypes,true);
            if($subtype !== false) return $type . '/' . $subtype;
        }
        return null;
    }

    /**
     * construct full MIME type fron mime subtype without option.
     * Use MIME['type']['subtype'] to avoid mistake.
     * @return string|null success example:application/json. null when type/subtring is not specified
     */
    function Construct_MIME(string $subtype):string|null{
        $subtype = strtolower(trim($subtype));

        foreach(MIME as $type=>$subtypes){
            if(in_array($subtype,$subtypes,true)) return $type.'/'.$subtype;
        }

        return null;
    }
?>