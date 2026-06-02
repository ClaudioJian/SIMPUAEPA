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

static void msg_invalid_bool(){
    size_t str_offset = 0;
    char *ON[] = {on_keyword};
    char *OFF[] = {off_keyword};
    const int ON_count = sizeof(ON)/ sizeof(ON[0]);
    const int OFF_count = sizeof(OFF)/ sizeof(OFF[0]);
    
    str_offset = display_wrapped_text("| [Source Error] Expected :","| ",0,MAX_LINE_CHR); 
    
    for(int i=0; i < ON_count; i++) {
        str_offset = display_wrapped_text(ON[i], "| ",str_offset,MAX_LINE_CHR);
        putchar(' ');
    }
    for(int i=0; i < OFF_count; i++) {
        str_offset = display_wrapped_text(OFF[i], "| ",str_offset,MAX_LINE_CHR);
        putchar(' ');
    }     

    printf(" ");
    display_wrapped_text("0(false) or 1(true)!","| ",str_offset,MAX_LINE_CHR);
}


static void general_err_msg(int error_code){
    switch(error_code){
        case buffer_overflow:
            display_wrapped_text("| Fatal error: buffer overflow detected!","| ",0,MAX_LINE_CHR); break;
        case ERR_fopen:
            display_wrapped_text("| Unable to open/create file!","| ",0,MAX_LINE_CHR);
            break;
        case ERR_fclose: //!
            display_wrapped_text("| An error occurred while saving. Please ensure the file is closed and verify write permissions","| ",0,MAX_LINE_CHR); break;
        case ERR_pclose:
            display_wrapped_text("| Command not found or exited with error status!","| ",0,MAX_LINE_CHR); break;    
        case ERR_fseek: //!
            display_wrapped_text("| Failed to navigate within the template file structure!","| ",0,MAX_LINE_CHR); break;   
        case ERR_encoding:
            display_wrapped_text("| Encoding error detected!","| ",0,MAX_LINE_CHR); break;
        case ERR_malloc: //!
            display_wrapped_text("| Memory allocation failure (malloc failed)!","| ",0,MAX_LINE_CHR); break;
        case ERR_file_creation:
            display_wrapped_text("| Cannot create file! Please check directory permissions and make sure the file isn't open in another program.","| ",0,MAX_LINE_CHR); break;
        case ERR_set_EnvVal:
            display_wrapped_text("| An error occurred while setting the environment variable!","| ",0,MAX_LINE_CHR); break;
        case ERR_WinCreateProcess:
            printf("| Process execution error code:%lu\n",GetLastError());
            display_wrapped_text("| Review error code documentation at:\n","| ",0,MAX_LINE_CHR);
            display_wrapped_text("| https://learn.microsoft.com/en-us/windows/win32/debug/system-error-codes","| ",0,MAX_LINE_CHR);
            break;          
        // php and composer related
        case ERR_PHP_not_found:
            display_wrapped_text("| Cannot find php in run time!","| ",0,MAX_LINE_CHR); break;
        case ERR_COMPOSER_not_found:
            display_wrapped_text("| 'composer.phar' not found in the project root! Please install it and try again.","| ",0,MAX_LINE_CHR); 
            display_wrapped_text("| Install composer:","| ",0,MAX_LINE_CHR);
            display_wrapped_text("| https://getcomposer.org/download/","| ",0,MAX_LINE_CHR);
            break;      
        case ERR_ssl_cert:
            display_wrapped_text("| SSL certificate verification issue!\n","| ",0,MAX_LINE_CHR);
            display_wrapped_text("| Please check if your certificates exist and are up to date.\n","| ",0,MAX_LINE_CHR);
            display_wrapped_text("| Refer to the README file for more detail of possible causes.","| ",0,MAX_LINE_CHR);
            break;
        case ERR_COMPOSER_depencity:
            display_wrapped_text("| Composer encountered a dependency resolution error!!","| ",0,MAX_LINE_CHR); break;
        case ERR_PATH_invalid:
            display_wrapped_text("| Target path format contains invalid characters!","| ",0,MAX_LINE_CHR);
            display_wrapped_text("| Note: This validation check is not strict; unlisted invalid characters or words may still cause execution errors.","| ",0,MAX_LINE_CHR); 
            break;
        case ERR_PATH_get_curr_abs:
            display_wrapped_text("| Failed to resolve the absolute path of the project root!\n","| ",0,MAX_LINE_CHR); break;
        case ERR_ENV_invalid_format:
            display_wrapped_text("| Environment key format error: should be [key=value] format!","| ",0,MAX_LINE_CHR); break;
        case ERR_ENV_empty_value:
            display_wrapped_text("| Configuration values marked with a skip flag cannot be left empty!","| ",0,MAX_LINE_CHR); break;                
        case ERR_variable_start_digit:
            display_wrapped_text("| Environment variable keys in .env.example cannot begin with a number (excluding file identifiers)!","| ",0,MAX_LINE_CHR); break;
        case ERR_variable_start_special_char:
            display_wrapped_text("| Environment variable keys in .env.example cannot begin with special characters (excluding file identifiers)!","| ",0,MAX_LINE_CHR);break;
        case ERR_invalid_extension:
            display_wrapped_text("| File extension type is not supported!","| ",0,MAX_LINE_CHR); break;
        case ERR_too_many_options:
            char opt_buf[64];
            snprintf(opt_buf, sizeof(opt_buf), "| The maximum number of options is %d!", MAX_OPTIONS);
            display_wrapped_text(opt_buf, "| ", 0, MAX_LINE_CHR);
            break;
        case ERR_permition_denied:
            display_wrapped_text("| Permission denied!","| ",0,MAX_LINE_CHR); break;
        case ERR_invalid_boolean:
            msg_invalid_bool();
            break;
        case ERR_invalid_mode:
            display_wrapped_text("| [Source Error]: Invalid mode selected. Only modes constants defined in ENV_const.h are acceptable!","| ",0,MAX_LINE_CHR); break;
        case test_sucess:
            printf("| test went sucessfull!"); break;
    }
    putchar('\n');
}


void print_error(error_details *err){
    printf("| ERROR FOUND!\n");
    general_err_msg(err->code);

    if(SHOW_DEBUG_INFO){
        printf("| Error start from\n");
        printf("| vvvvvvvvvvvvvvvv\n");

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