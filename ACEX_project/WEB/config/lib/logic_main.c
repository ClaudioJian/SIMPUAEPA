#include "logic_main.h"

void free_global_value(){
    if(PHP_LOCATION){
        free(PHP_LOCATION);
        PHP_LOCATION = NULL;        
    }
    free(ABSOLUTE_PATH);
    ABSOLUTE_PATH = NULL;
}



int set_abs_path(int *error_code){
    ABSOLUTE_PATH = malloc(MAX_PATH_LEN);

    if(!ABSOLUTE_PATH){
        *error_code = malloc_error;
        return -1;
    }

    //get absolute path of current folder
    if(strcmp(OS,"Windows") == 0){
        FILE *fp = popen("cd","r");
        fgets(ABSOLUTE_PATH,MAX_PATH_LEN,fp);

        if (pclose(fp)) {
            *error_code = pclose_error;
            return -1;
        }
    }else{
        ABSOLUTE_PATH = getcwd(ABSOLUTE_PATH,MAX_PATH_LEN);
    }

    if(ABSOLUTE_PATH == NULL){
        *error_code = no_abs_path;
        return -1;
    }else{
        char *pos; //position of \n and \r in the end of path
        //remove \n and \r in the end of path
        pos = strchr(ABSOLUTE_PATH,'\n');
        if(pos!=NULL) *pos = '\0';

        pos = strchr(ABSOLUTE_PATH,'\r');
        if(pos!=NULL) *pos = '\0';
    }
    return 0;
}

/**
 * set temporary enviroment variable SSL_CERT_FILE in current process
 * @return 0 if sucess else -1
 */
static int set_certificate_enviroment(int *error_code){
    char abs_path[MAX_PATH_LEN];
    char abs_dir[MAX_PATH_LEN];

    int sucess = 0;
    snprintf(abs_path,MAX_PATH_LEN,"%s%c%s%c%s", ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR, SLASH_CHR , CERTIFICATES_FILE_NAME);
    snprintf(abs_dir,MAX_PATH_LEN,"%s%c%s", ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR);

    sucess = setenv("SSL_CERT_FILE", abs_path);
    if(!sucess) sucess = setenv("SSL_CERT_DIR", abs_dir);
    

    if(sucess){
        *error_code = ERR_set_enviroment_value;
        return -1;
    }

    return sucess;
}

/**
 * print confirmation msg:
 * @param string *action printed after: "| WARNING: This program are trying to [param action string]"
 * @return
 * 
 * - 0 = denied
 * 
 * - 1 = accepted
*/ 
static int confirmation(char *action,int* error_code){
    char answer[MAX_INPUT_SIZE];

    while(1){
        char text[MAX_TEXT_SIZE];
        int expected = snprintf(text,MAX_TEXT_SIZE,"| WARNING: This program is trying to %s",action);
        text[strlen(text)] = '\0';

        if(expected >= MAX_TEXT_SIZE){
            *error_code = buffer_overflow;
            return 0;
        }

        display_wrapped_text(text,"|",0,MAX_LINE_CHR); putchar('\n');

        printf("| Are you sure about that?\n");
        printf("|  |__[Y/N]:");
        scanf("%39s",answer);
        if(answer[0]!='y'&& answer[0]!='Y'){
            *error_code = premition_denied;
            return 0;
        }else break;
    }
    return 1;
}

/**
 * Check if php exists.
 * 
 * Ask to user where is their php.exe is in and check if path provided is true.
 * 
 * After sucess, set in global value .
 * @param error_code change address of it, check content
 * @returns int
 * - 1 = true when path is accuracy 
 * 
 * - 0 = false when PHP_path_envkeyName is wrong or EOF
 * 
 * - -1 = buffer overflow, fseek error, malloc error
 */
static int PHP_exists(ENV_CONFIG_field *data, int *error_code){
    const int size_cmd = MAX_BUFFER_SIZE + 13;
    char cmd[size_cmd];

    int exit_code;
    
    //if not finded php path, loop until find
    do{
        //find where is php path
        if(ENV_CONFIG_adjust_key(PHP_path_envKeyName,data,error_code)){
            
            // 0 mean key name error and negative mean error like malloc
            if(*error_code) return -1;

            char *right_path = normalize_path(data->value,error_code);
            if(*error_code) return -1;

            if(right_path==NULL) *error_code = invalid_path;
            if(*error_code) return -1;

            
            strcpy(data->value,right_path);

            free(right_path);
            right_path = NULL;

            if(strcmp(OS,"Windows")==0){
                // join real php path

                int expected = snprintf(cmd,size_cmd,"\"%s\\php.exe\" -v %s",data->value,NULL_REDIRECT);
                // check buffer overflow

                if(expected >= size_cmd){
                    *error_code = buffer_overflow;
                    return -1;
                }

                exit_code = system(cmd);
            }

            //not find, tell to user
            if(exit_code) {
                printf("| php is not finded, perhabs you didn't install php or path[%s] provided was wrong!\n",data->value);
                printf("|\n");
            }            
        }else{
            printf("| php is not finded, perhabs setting for [%s] was wrong!\n",PHP_path_envKeyName);
            printf("|\n");
            // when hit eof or is skippable and not find
            if(data->mode== -1) *error_code = php_not_found;
            ENV_CONFIG_clear(data);
            if(*error_code) return -1;
            return 0;
        }
    }while(exit_code);

    printf("| php found!\n");
    printf("|\n");

    //only track when find
    ENV_CONFIG_track_depencity(data,error_code);
    if(*error_code) return -1;

    //store php location in global value
    PHP_LOCATION = malloc(strlen(data->value)+1);
    strcpy(PHP_LOCATION, data->value);
    ENV_CONFIG_clear(data);

    if(PHP_LOCATION == NULL) {
        *error_code = malloc_error;
        return -1;
    }

    ENV_CONFIG_track_depencity(data,error_code);
    ENV_CONFIG_clear(data);
    return 1;
}


static void run_all_file(ENV_CONFIG_field *data, int *error_code){
    if(!PHP_LOCATION) {
        *error_code = php_not_found;
        return;
    }

    file_node *curr_node = data->file_list;



    //clear depencity list
    while(curr_node != NULL){
        const int size_cmd = MAX_BUFFER_SIZE + 13;
        char cmd[size_cmd];

        int expected;

        if(curr_node->extension==1){
            //PHP_LOCATION/php ABSOLUTE_PATH/SETUP_FOLDER/curr_node->file
            expected = snprintf(cmd,size_cmd,"%s%cphp %s%c%s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , SETUP_FOLDER, SLASH_CHR , curr_node->file);
        }else if(curr_node->extension==2){
            //sql
        }
         
        // check buffer overflow
        if(expected >= size_cmd){
            *error_code = buffer_overflow;
            return;
        }

        // if WARNING_FLAGS is set, ask for confirmation before run each file
        if(WARNING_FLAGS){
            printf("|\n");
            //join the current file name and others displays
            char buffer[MAX_TEXT_SIZE];
            expected = snprintf(buffer,MAX_TEXT_SIZE,"run a file: [%s%s%s] (CAUTIONS! THE FILE CAN BE DANGEROUS).", ABSOLUTE_PATH , SLASH ,curr_node->file);

            if(expected >= MAX_TEXT_SIZE){
                *error_code = buffer_overflow;
                return;
            }

            confirmation(buffer,error_code);
            if(*error_code) return;

            printf("|______________________________________\n");
        }

        
        putchar('\n');
        printf(" Running file: %s\n",curr_node->file);
        putchar('\n');

        system(cmd);
        
        memset(cmd,0,size_cmd);

        //advance
        curr_node = curr_node->next;
    }
}


/**
 * setting all value in file passed in data and tracking in data
 * @return
 * - 0 sucess and reach EOF
 * 
 * - -1 error: buffer overflow, malloc error, invalid extension if is file
 */
static int ENV_CONFIG_set_all(ENV_CONFIG_field *data, int *error_code){
    while(!ENV_CONFIG_step_config(data,error_code)){
        if(*error_code) return -1;

        ENV_CONFIG_track_depencity(data,error_code);
        if(*error_code) return -1;

        ENV_CONFIG_clear(data);
    }
    //reach eof, all setting is done
    ENV_CONFIG_clear(data);
    return 0;
}


/**
 * set enviroment file name and enviroment.
 * @return
 * - file name
 * - NULL when error happen
 */
static char* set_enviroment(ENV_CONFIG_field *ENV_data,int *error_code){
    char *ENV_FILE_name = malloc(MAX_VALUE_SIZE+5);

    //store it in temp location to set name for .env, don't sufix if NULL
    char buf[MAX_VALUE_SIZE+1]; 
    buf[0] = '\0';

    ENV_CONFIG_adjust_key(ENVIROMENT_KEY_NAME,ENV_data,error_code);
    if(*error_code) return NULL;

    
    // if value is NULL, don't sufix. else add "." and value
    if(strcmp(ENV_data->value,"NULL")!=0){
        snprintf(buf,MAX_VALUE_SIZE+1,".%s",ENV_data->value);
    }else{
        //copy default value to value, since the data is NULL still
        memcpy(ENV_data->value,ENV_data->original_value,MAX_VALUE_SIZE);
        ENV_CONFIG_ui_prompt(ENV_data,error_code);
        if(*error_code) return NULL;
    }

    snprintf(ENV_FILE_name,MAX_VALUE_SIZE+5,".env%s",buf);

    return ENV_FILE_name;
}


/**
 * find if string matches:
 * 
 * - 0 = false | 1 =  true
 * 
 * - ON = true | OFF = false
 * 
 * - TRUE = true | FALSE = false
 * 
 * @return 0 false | 1 true
 */
static int is_on(char *string,int *error_code){
    char* upper_str = convert_str_to_upper(string,error_code);
    if(upper_str==NULL||*error_code){
        free(upper_str);
        upper_str = NULL;
        
        *error_code = malloc_error;
        return 0;
    }
    int on=1;
    int str_int = convert_str_to_int(upper_str);


    if(
        strcmp(upper_str,"ON")==0 ||
        strcmp(upper_str,"TRUE") == 0 ||
       ( str_int > 0 )
    ) on = 1;
    else if(
        strcmp(upper_str,"OFF")==0 ||
        strcmp(upper_str,"FALSE") == 0 ||
        str_int == 0
    ) on = 0;
    else{
        *error_code = invalid_boolean;
        on = 0;
    }

    free(upper_str);
    upper_str = NULL;

    return on;
}

//set global value WARNING_FLAGS as int(0 false else 1 true), this flag is used to determine if show warning msg for some setting
static void set_warning_flags(ENV_CONFIG_field *data,int *error_code){
    ENV_CONFIG_adjust_key(WARNING_FLAG_NAME,data,error_code);
    if(*error_code) return;

    WARNING_FLAGS = is_on(data->value,error_code);
    if(*error_code) return;

    ENV_CONFIG_clear(data);
}



static int composer_exists(int *error_code){
    char cmd[MAX_BUFFER_SIZE];
    int response;
    int expected;
    expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s %s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar --version",NULL_REDIRECT);
    if(expected >= MAX_BUFFER_SIZE){*error_code = buffer_overflow; return -1;} 
    response = system(cmd);
    if(response){
        *error_code = composer_not_exist;
        return -1;
    }
    return 1;
}


/**
 * if .json has change run composer update else composer install
 * @param expected changed for snprintf
 * @return
 * - 0 sucess
 * 
 * - -1 error: composer_error, buffer overflow
 */
static int update_depencity(int *error_code, int *expected, char *cmd){
    //check is up to date, --strict: Return a non-zero exit code for warnings as well as errors.
    *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar validate --check-lock --no-check-version --strict");
    if(*expected >= MAX_BUFFER_SIZE){*error_code = buffer_overflow; return -1;} 

    int response = system(cmd);

    // 1 = not up to date
    if(response==1){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar update --with-all-dependencies");
    }else if(response==0){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar install");
    }
    else{
        *error_code = composer_error;
        return -1;
    }
    return 0;
}


/**
 * install all depencity in composer.phar
 * 
 * check if .lock exist -> run composer install-> if not up to date -> run composer update
 * 
 * else if not exist->run composer uptade
 * @return
 * - error: ssl_cert_error, ERR_set_enviroment_value, buffer overflow
 */
static void install_depencity(int *error_code){
    composer_exists(error_code);
    if(*error_code) return;

    set_certificate_enviroment(error_code);
    if(*error_code) return;

    const int fexist_composer_lock = access("composer.lock", F_OK);
    char cmd[MAX_BUFFER_SIZE];
    int response;
    int expected;

    //-1 not exist, 0 exist
    if(fexist_composer_lock){
        expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar update");
        if(expected >= MAX_BUFFER_SIZE){*error_code = buffer_overflow; return;}        
    }else{
        update_depencity(error_code, &expected, cmd);
        if(*error_code) return;
    }

    if(expected >= MAX_BUFFER_SIZE){*error_code = buffer_overflow; return;}

    response = system(cmd);
    if(response){
        *error_code = ssl_cert_error;
        return;
    }
}



//print main logic of program, run all steps and return immediately if any error happen and change error code
void start_program(ENV_CONFIG_field *internal_data,ENV_CONFIG_field *ENV_data,int *error_code){
    printf("================Internal Setting================\n");
    printf("|\n");
    printf("| The values you are setting now will not be writen in new .env file.\n");
    printf("|\n");

    

    set_warning_flags(internal_data,error_code);
    if(*error_code) return;

    if(!PHP_exists(internal_data,error_code)){*error_code = php_not_found; return;}

    ENV_CONFIG_set_all(internal_data,error_code);
    if(*error_code) return;

    printf("|\n");
    printf("| All setting is configured in [%s]\n",CONFIG_FILE);
    printf("|\n");
    printf("================Installing depencity================\n");

    install_depencity(error_code);
    if(*error_code) return;

    putchar('\n');
    printf("All depencity have been installed sucessfully!\n");

    printf("================ENV================\n");
    printf("|\n");
    printf("| The values you are setting now will be writen in new .env file.\n");
    printf("|\n");

    char *ENV_FILE_name = set_enviroment(ENV_data,error_code);
    if(*error_code) return;
    ENV_CONFIG_track_depencity(ENV_data,error_code);
    ENV_CONFIG_clear(ENV_data);



    
    ENV_CONFIG_set_all(ENV_data,error_code);
    if(*error_code) return;

    printf("|\n");
    printf("| All setting is configured in [%s]\n",ENV_EXAMPLE);
    printf("|\n");

    printf("===============writing files===============\n"); 
    printf("|\n");
    printf("| Writing file:%s\n",ENV_FILE_name);


    if(WARNING_FLAGS) {
        confirmation("write new file, this can overwrite existing .env file with same name above!",error_code);
        if(*error_code) return;
    }

    ENV_CONFIG_write(ENV_data,ENV_FILE_name,error_code);
    if(*error_code) return;

    // release memory
    free(ENV_FILE_name);
    ENV_FILE_name = NULL;

    printf("|\n");
    printf("| File written sucessfully!\n");
    printf("|\n");
    printf("===============Running files===============\n"); 

    run_all_file(internal_data,error_code);
    if(*error_code) return;

    printf("\n\n");
    printf("ALL files have been executed sucessfully!\n");
}

