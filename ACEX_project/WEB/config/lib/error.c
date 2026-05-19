#include "error.h"
#include "error_type.h"


trace_error emergency_node = {0};

int ERR_snprintf(const int expected,const size_t max_size,error_details *err){
    if(expected<0){
        err->code = ERR_encoding;
        return -1;
    }else if((size_t)expected >= max_size){
        err->code = buffer_overflow;
        return -1;
    }
    return 0;
}


int track_error(error_details *err, const char *file,const char *funct_name, const unsigned int line){
    trace_error* new_node = (trace_error*)malloc(sizeof(trace_error));
    if(!new_node) {
        err->code = ERR_malloc;
        new_node = &emergency_node;

        if(!emergency_in_use){
            snprintf(emergency_node.description,sizeof(emergency_node.description),"Out of memory!");
            emergency_in_use = 1;
        }else{
            err->description[0] = '\0';
            return -1;
        }
    }

    //set new node to attract
    if(strlen(file) > sizeof(new_node->file)){
        err->code = buffer_overflow;
        snprintf(new_node->description,sizeof(new_node->file),"buffer overflow, size of file passed cannot be greater than: %lld Bytes",sizeof(new_node->file));
        strncpy(new_node->file,file,sizeof(new_node->file));
        new_node->file[sizeof(new_node->file)-1] = '\0';
    }
    else strcpy(new_node->file,file);

    //only copy if isn't set alredy
    if(err->description[0] != '\0')strcpy(new_node->description,err->description);
    err->description[0] = '\0';

    //read until (
    size_t i=0;
    size_t max_funct_len = strlen(funct_name);
    for(;funct_name[i]!='\0';i++){
        if(i >= max_funct_len){
            err->code = buffer_overflow;

            char buffer[MAX_TEXT_SIZE];
            snprintf(buffer,MAX_TEXT_SIZE,"function name too long, cannot exceed %lld Bytes",sizeof(new_node->function));
            strcpy(new_node->description,buffer);
            return -1;
        }
        if(funct_name[i]=='(') break;
        new_node->function[i] = funct_name[i];
    }
    new_node->function[i] = '\0';


    new_node->line = line;

    new_node->next = NULL;


    // if last setting is not set, attract to new_node created
    // Update STRUCT storage
    if (err->err_trace == NULL) {
        err->err_trace = new_node;
    } else {
        err->last_error->next = new_node;
    }
    err->last_error = new_node;    
    return 0;
}




static void general_err_msg(int error_code){
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
    putchar('\n');
}


void print_error(error_details *err){
    printf("| ERROR FOUND!\n");
    general_err_msg(err->code);

    if(SHOW_DEBUG_INFO){
        printf("| Start from\n");
        printf("| vvvvvvvvvv\n");

        for(trace_error *curr_node = err->err_trace; curr_node != NULL; curr_node = curr_node->next){
            if(SHOW_ERR_LINE){
                size_t size_total = strlen(curr_node->function) + strlen(curr_node->file);
                char buf[100 + size_total];
                snprintf(buf,100 + size_total,"| Error in function %s on line %i of file %s :",curr_node->function,curr_node->line,curr_node->file);
                display_wrapped_text(buf,"| ",0,MAX_LINE_CHR);
                putchar('\n');
            }
            if(curr_node->description[0]!='\0') {
                printf("| |__");
                display_wrapped_text(curr_node->description,"| ",2,MAX_LINE_CHR);
                putchar('\n');
            }
        }
    }
}

static void ERR_details_clear_node(error_details *err){
    trace_error *curr_node = err->err_trace;
    //clear file list
    while(curr_node != NULL){
        trace_error *prev_node = curr_node;
        curr_node = curr_node->next;

        free(prev_node);
        prev_node = NULL;
    }

    err->err_trace = NULL;
    err->last_error = NULL;
}


void ERR_details_destroy(error_details **err){
    if(*err==NULL) return;
    ERR_details_clear_node(*err);
}