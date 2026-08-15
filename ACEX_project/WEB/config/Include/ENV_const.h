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

#define on_keyword "ON","TRUE"
#define off_keyword "OFF","FALSE"


//certificate location to ssl works
#define CERTIFICATES_DIR "config" SLASH "certificates"

//certificate file name for ssl
#define CERTIFICATES_FILE_NAME "curl-ca-bundle.crt.pem"

//name of folder contain setup.exe
#define SETUP_FOLDER "config"

#define ENV_EXAMPLE_FILE_NAME ".env.example"

//location of .env.example
#define ENV_EXAMPLE "." SLASH "config" SLASH "setting" SLASH ENV_EXAMPLE_FILE_NAME

#define INTERNAL_CONFIG_FILE_NAME "internal.cfg"

//location of internal use config file
#define CONFIG_FILE "." SLASH "config" SLASH "setting" SLASH INTERNAL_CONFIG_FILE_NAME

//name defined in internal.cfg
#define WARNING_FLAG_NAME "WARNING_FLAGS"
#define ENVIRONMENT_KEY_NAME "ENVIRONMENT"
#define PHP_path_envKeyName "PHP_PATH"
#define show_debug_KeyName "SHOW_DEBUG_INFO"
#define show_r_only_name "SHOW_READ_ONLY"
#define show_ERR_location "DISPLAY_ERROR_LINE"
#define SIMPLIFIED_DISPLAY_NAME "SIMPLIFIED_DISPLAY"
















#if defined(_WIN32) || defined(_WIN64)
    #define OS "Windows"
    
    #include <direct.h>

    // Exclude some Windows API
    #define WIN32_LEAN_AND_MEAN

    #include <Processthreadsapi.h>
    #include <wtypesbase.h>
    #include <namedpipeapi.h>
    #include <fileapi.h>


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

//global values

//fall back when malloc error
extern int emergency_in_use;

typedef struct{
    char* ABSOLUTE_PATH;
    char* PHP_LOCATION;
    int WARNING_FLAGS;
    int SHOW_DEBUG_INFO;
    int SHOW_ERR_LINE;
    int SHOW_READ_ONLY;
    // flag that decide if need newline between variables=values
    int NEWLINE_BETWEEN_VARIABLES;
    int SIMPLIFIED_DISPLAY;
}global_values;


// only read setting, not display any UI, just set value in data
#define r_mode 0
//ask
#define w_mode 1
//internal use, just set value without ask, but still can display read only value
#define internal_mode 2


global_values* global_value_init();

#endif