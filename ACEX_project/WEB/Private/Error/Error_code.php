<?php 
/*
  +-------------------------------------------------------------------------------------------------+
  | Application Error codes                                                                         |
  +-------------------------------------------------------------------------------------------------+
*/
namespace ACEX_project\WEB\Private\Error;
    //here put some error code
    // . PHP_EOL is a constant in PHP that represents the end of a line.
    enum Error_Code : int{
        case user_not_logged = -10;
        case user_wrong_credit = -11;
        case user_alredy_exist = -12;
        case user_not_found = -15;
        case user_alredy_logged = -16;

        case data_invalid_format = -21;
        case data_not_found = -25;
        
        case request_invalid_general = -40;
        case request_invalid_input = -41;

        case db_connection = -50;
        case db_grant = -51;
        case db_insert = -52;
        case db_delete = -54;
        case db_select = -55;

        case password_weak = -60;
    };
?>