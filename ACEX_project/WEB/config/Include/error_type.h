#ifndef error_type
#define error_type

#include "ENV_const.h"


/**
 * store single node for each function has call it
 */
typedef struct trace_error{
    unsigned int line;
    char file[MAX_PATH_LEN];
    char function[MAX_BUFFER_SIZE];
    char description[MAX_TEXT_SIZE];
    struct trace_error *next;
}trace_error;


/**
 * store all information for error.
 * 
 * description is set when cannot malloc
 */
typedef struct{
    int code;
    char description[MAX_TEXT_SIZE];
    trace_error *err_trace;
    trace_error *last_error;
}error_details;

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
    ERR_WinApi,
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
    ERR_invalid_mode,
    test_sucess,
}error_code_list;

int track_error(error_details *err, const char *file,const char *funct_name, const unsigned int line);
void ERR_details_destroy(error_details **err);


//===============================Function==============================


/**
 * Call this function when calling any function that can throw error
 * 
 * track line, function and file caused error
 * 
 * description only track the first description it find
 * 
 */
#define catch_err(funct)\
(funct);\
do{\
    if(err->code){\
        track_error(err,__FILE__,#funct,__LINE__);\
    }\
}while(0)\

#endif