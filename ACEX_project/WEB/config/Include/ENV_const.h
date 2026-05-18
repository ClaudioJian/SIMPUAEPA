/*
===================================IMPORTANT======================================

If you are changing values/folder name/file name inside: config
Please also change in section below: IMPORTANT CONST 


*/

#ifndef ENV_const
#define ENV_const



//-----------------------define const--------------------------
// 32 is both 64 and 32 bits, 64 is only 64 and cygwin use unix symbols
//root is relative from .exe, not this .c file
// WARNING: current folder of .exe is in ROOT!!!!
#if defined(_WIN32) || defined(_WIN64)
    #define SLASH_CHR '\\'
    #define SLASH "\\"
#else
    #define SLASH "/"
    #define SLASH_CHR '/'
#endif

//==================IMPORTANT CONST====================
#define MAX_PATH_LEN 1024
#define MAX_KEY_SIZE 256
// store information like label
#define MAX_TEXT_SIZE 1024
#define MAX_VALUE_SIZE 512
// must match or greater than any others max size, it store some like max char of line that can used by fgets
#define MAX_BUFFER_SIZE 1024
//must be less than key/value size
#define MAX_INPUT_SIZE 256

#define OPTIONS_DELIMITERS_CHR '|'
// max size for each option before hit OPTIONS_DELIMITERS_CHR
#define MAX_OPTION_SIZE 256

// max quantity of option can have
#define MAX_OPTIONS 10

//max chr can one line hold before switch to new line in UI
#define MAX_LINE_CHR 90


//certificate location to ssl works
#define CERTIFICATES_DIR "config" SLASH "certificates"

//certificate file name for ssl
#define CERTIFICATES_FILE_NAME "curl-ca-bundle.crt.pem"

//name of folder contain setup.exe
#define SETUP_FOLDER "config"

//location of .env.example
#define ENV_EXAMPLE "." SLASH "config" SLASH "setting" SLASH ".env.example"

//location of internal use config file
#define CONFIG_FILE "." SLASH "config" SLASH "setting" SLASH "internal.cfg"


#define WARNING_FLAG_NAME "WARNING_FLAGS"
#define ENVIROMENT_KEY_NAME "ENVIROMENT"
#define PHP_path_envKeyName "PHP_PATH"
















#if defined(_WIN32) || defined(_WIN64)
    #define OS "Windows"
    
    #include <direct.h>

    // Exclude some Windows API
    #define WIN32_LEAN_AND_MEAN

    #include <Processthreadsapi.h>
    #include <wtypesbase.h>


    //function name
    #define chdir _chdir
    #define setenv _putenv_s
    #define access _access
    #define ExecPHP_script PHP_WinExec
    #define F_OK 0

    #define ROOT "..\\"
    #define NULL_REDIRECT ">nul 2>&1"
#else
    #define OS "Unix"

    #include <unistd.h>

    //function name
    #define ExecPHP_script PHP_ForkExec

    #define SLASH "/"
    #define SLASH_CHR '/'
    #define ROOT "../"
    #define NULL_REDIRECT ">dev/null 2>&1"
#endif

#include "general_includes.h"

//global values
extern char* ABSOLUTE_PATH;
extern char* PHP_LOCATION;
extern int WARNING_FLAGS;

typedef enum{
    buffer_overflow = 100,
    // standart function error
    ERR_fopen,
    ERR_fclose,
    ERR_pclose,
    ERR_fseek,
    ERR_encoding,
    ERR_malloc,
    ERR_file_creation,
    ERR_WinCreateProcess,
    ERR_set_EnvVal,
    // php and composer related
    ERR_PHP_not_found,
    ERR_COMPOSER_not_found,
    ERR_ssl_cert,
    ERR_COMPOSER_depencity,
    //path related
    ERR_PATH_invalid,
    ERR_PATH_get_curr_abs,
    //custom err
    ERR_ENV_invalid_format,
    ERR_ENV_empty_value,
    ERR_variable_start_digit,
    ERR_variable_start_special_char,    
    ERR_invalid_extension,
    ERR_too_many_options,
    ERR_permition_denied,
    ERR_invalid_boolean,
    test_sucess,
}error_code_list;

#endif