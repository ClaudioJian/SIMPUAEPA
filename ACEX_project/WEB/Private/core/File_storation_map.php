<?php
namespace ACEX_project\WEB\Private\Core;
    /** values is relative to current folder, which is ...Core/Security 
     * The directory structure:
     * Uploads
     * |__Images
     * |    |__Maps
     * |    |__Profiles
     * |__tmp
     *      |__Awaiting_sanitation
     *      |__Awaiting_validation
    */

    enum File_storation_path: string{
        case Error_logs = __DIR__ . "/../../../Log";
        case Rate_track_logs = __DIR__ . "/Rate_track";
            case Rate_track_ip = __DIR__ . "/Rate_track/IP";
            case Rate_track_account = __DIR__ . "/Rate_track/Account";
            case Rate_track_endpoint = __DIR__ . "/Rate_track/EndPoint";
            
            case User_uploads = __DIR__ . "/../Uploads/";
                case Temp = __DIR__ . "/../Uploads/tmp";
                    case Await_sanitation = __DIR__ . "/../Uploads/tmp/Awaiting_sanitation";
                    case Await_validation = __DIR__ . "/../Uploads/tmp/Awaiting_validation";

                case User_images = __DIR__ . "/../Uploads/Images";
                    case Maps = __DIR__ . "/../Uploads/Images/Maps";
                    case profiles = __DIR__ . "/../Uploads/Images/Profiles";
        
        /**
         * No need create all, only the end of file tree need be created. check mkdir() for more information
         * @link https://www.php.net/manual/en/function.mkdir.php
         */
        public function Initialize_directory() : bool{
            $dir = $this->value;
            if(\is_dir($dir)) return true;
            if(\file_exists($dir)) return false;

            return \mkdir($dir,0700, true) || \is_dir($dir);
        }
    }