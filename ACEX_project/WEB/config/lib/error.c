#include "error.h"
#include "ENV_const.h"

int ERR_snprintf(const int expected,const size_t max_size,int *error_code){
    if(expected<0){
        *error_code = ERR_encoding;
        return -1;
    }else if((size_t)expected >= max_size){
        *error_code = buffer_overflow;
        return -1;
    }
    return 0;
}

void print_error(const int error_code){
    printf("| ERROR FOUND!\n");
        switch(error_code){
            case buffer_overflow:
                printf("| buffer overflow!"); break;
            case ERR_fopen:
                printf("| cannot open/create file!"); break;
            case ERR_fclose:
                printf("| some thing happen when writing! please check if is open or it's permission"); break;
            case ERR_pclose:
                printf("| Command not found or exited with error status!"); break;    
            case ERR_fseek:
                printf("| format for variable name/key in .env.example shoud contain \"=\"!"); break;   
            case ERR_encoding:
                printf("| Encoding error!");break;
            case ERR_malloc:
                printf("| cannot allocate memory for malloc()!"); break;
            case ERR_file_creation:
                printf("| cannot create file! Please check if you have permission and isn't open!"); break;
            case ERR_set_EnvVal:
                printf("| some thing went wrong when set enviroment variable!"); break;
            case ERR_WinCreateProcess:
                printf("| Error code when executing:%lu\n",GetLastError());
                printf("| See more detail in:\n");
                printf("| https://learn.microsoft.com/en-us/windows/win32/debug/system-error-codes");
                break;          
            // php and composer related
            case ERR_PHP_not_found:
                printf("| cannot find php!"); break;
            case ERR_COMPOSER_not_found:
                printf("| composer.phar not found in project root! Please check if you have it and try again!"); break;      
            case ERR_ssl_cert:
                printf("| SSL certificate problem!\n");
                printf("| Please check if certification exist and are up to date!\n");
                printf("| Also verify in read me to more possible reason...");
                break;
            case ERR_COMPOSER_depencity:
                printf("| Dependency solving error code in composer!"); break;
            case ERR_PATH_invalid:
                printf("| format for path contains invalid characters(note: some invalid character are not included)!"); break;
            case ERR_PATH_get_curr_abs:
                printf("| cannot get absolute path of project root!\n"); break;
            case ERR_ENV_invalid_format:
                printf("| format for variable name/key in .env.example shoud contain \"=\"!"); break;
            case ERR_ENV_empty_value:
                printf("| Values with skip flag in configuration file cannot be empty!"); break;                
            case ERR_variable_start_digit:
                printf("| variable name/key in .env.example cannot start with any digit!"); break;
            case ERR_variable_start_special_char:
                printf("| variable name/key in .env.example cannot start with any special char(except for file)!");break;
            case ERR_invalid_extension:
                printf("| file type is not supported!"); break;
            case ERR_too_many_options:
                printf("| max option can be used is %i!",MAX_OPTIONS); break;
            case ERR_permition_denied:
                printf("| Permission denied!"); break;
            case ERR_invalid_boolean:
                printf("| invalid boolean value! only accept \"on\", \"off\", \"true\", \"false\", integer 0(false) and positive integer 1(true)!"); break;
            case test_sucess:
                printf("| test went sucessfull!"); break;
        }
}