/*
===================================IMPORTANT======================================

If you are changing values/folder name/file name inside: config
Please also change in section below: IMPORTANT CONST 


*/

#ifndef ENV_const
#define ENV_const

#include "general_includes.h"


//-----------------------define const--------------------------
// 32 is both 64 and 32 bits, 64 is only 64 and cygwin use unix symbols
//root is relative from .exe, not this .c file
// WARNING: current folder of .exe is in ROOT!!!!
#if defined(_WIN32) || defined(_WIN64)
    #define OS "Windows"

    #include <direct.h>

    #define chdir _chdir
    #define setenv _putenv_s
    #define access _access
    #define F_OK 0

    #define SLASH_CHR '\\'
    #define SLASH "\\"
    #define ROOT "..\\"
    #define NULL_REDIRECT ">nul 2>&1"
#else
    #define OS "Unix"

    #include <unistd.h>

    #define SLASH "/"
    #define SLASH_CHR '/'
    #define ROOT "../"
    #define NULL_REDIRECT ">dev/null 2>&1"
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

































//global values
extern char* ABSOLUTE_PATH;
extern char* PHP_LOCATION;
extern int WARNING_FLAGS;

typedef enum{
    buffer_overflow = 100,
    fopen_error,
    fclose_error,
    variable_start_digit,
    variable_start_special_char,
    ENV_invalid_format,
    ENV_empty_value,
    invalid_path,
    fseek_error,
    malloc_error,
    php_not_found,
    extension_invalid,
    too_many_options,
    ERR_file_creation,
    premition_denied,
    invalid_boolean,
    pclose_error,
    no_abs_path,
    composer_not_exist,
    ERR_set_enviroment_value,
    ssl_cert_error,
    composer_error,
    test_sucess,
}error_code;

#endif