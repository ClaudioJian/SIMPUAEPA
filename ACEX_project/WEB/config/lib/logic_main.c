#include "logic_main.h"

void free_global_value(){
    if(PHP_LOCATION){
        free(PHP_LOCATION);
        PHP_LOCATION = NULL;        
    }
    free(ABSOLUTE_PATH);
    ABSOLUTE_PATH = NULL;
}



int set_abs_path(error_details *err){
    ABSOLUTE_PATH = malloc(MAX_PATH_LEN);

    if(!ABSOLUTE_PATH){
        err->code = ERR_malloc;
        strcpy(err->description, "fail to set global value for current directory");
        return -1;
    }

    //get absolute path of current folder
    if(strcmp(OS,"Windows") == 0){
        FILE *fp = popen("cd","r");
        fgets(ABSOLUTE_PATH,MAX_PATH_LEN,fp);

        if (pclose(fp)) {
            err->code = ERR_pclose;
            return -1;
        }
    }else{
        ABSOLUTE_PATH = getcwd(ABSOLUTE_PATH,MAX_PATH_LEN);
    }

    if(ABSOLUTE_PATH == NULL){
        err->code = ERR_PATH_get_curr_abs;
        strcpy(err->description, strerror(errno));
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
static int set_certificate_enviroment(error_details *err){
    char abs_path[MAX_PATH_LEN];
    char abs_dir[MAX_PATH_LEN];

    int sucess = 0;
    int expected;
    int res;
    expected = snprintf(abs_path,MAX_PATH_LEN,"%s%c%s%c%s", ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR, SLASH_CHR , CERTIFICATES_FILE_NAME);
    res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;

    snprintf(abs_dir,MAX_PATH_LEN,"%s%c%s", ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR);
    res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;

    sucess = setenv("SSL_CERT_FILE", abs_path);
    if(!sucess) sucess = setenv("SSL_CERT_DIR", abs_dir);
    

    if(sucess){
        err->code = ERR_set_EnvVal;
        strcpy(err->description, "cannot set enviroment value for certificate for composer");
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
 * 
 * - -1 = error: buffer_overflow, enconding error
*/ 
static int confirmation(char *action,error_details *err){
    char answer[MAX_INPUT_SIZE];

    while(1){
        char text[MAX_TEXT_SIZE];
        int snptintf_res;

        const int expected = snprintf(text,MAX_TEXT_SIZE,"| WARNING: This program is trying to %s",action);
        snptintf_res = catch_err(ERR_snprintf(expected,MAX_TEXT_SIZE,err));
        if(snptintf_res) return -1;
        
        text[strlen(text)] = '\0';


        display_wrapped_text(text,"|",0,MAX_LINE_CHR); putchar('\n');

        printf("| Are you sure about that?\n");
        printf("|  |__[Y/N]:");
        scanf("%39s",answer);
        if(answer[0]!='y'&& answer[0]!='Y'){
            err->code = ERR_permition_denied;
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
static int PHP_exists(ENV_CONFIG_field *data, error_details *err){
    const size_t size_cmd = MAX_BUFFER_SIZE + 13;
    char cmd[size_cmd];

    int exit_code;
    
    //if not finded php path, loop until find
    do{
        //find where is php path
        const int find = catch_err(ENV_CONFIG_adjust_key(PHP_path_envKeyName,data,err));
        if(find){
            // 0 mean key name error and negative mean error like malloc
            if(err->code) return -1;

            char *right_path = catch_err(normalize_path(data->value,err));
            if(err->code) return -1;

            if(right_path==NULL) err->code = ERR_PATH_invalid;
            if(err->code) return -1;

            
            strcpy(data->value,right_path);

            free(right_path);
            right_path = NULL;

            if(strcmp(OS,"Windows")==0){
                // join real php path
                const int expected = snprintf(cmd,size_cmd,"%s\\php.exe",data->value);
                // check buffer overflow
                const int res = catch_err(ERR_snprintf(expected,size_cmd,err));
                if(res) return -1;

                exit_code = access(cmd,F_OK);
            }

            //not find, tell to user
            if(exit_code) {
                printf("| php is not finded, perhabs you didn't install php or path[%s] provided was wrong!\n",data->value);
                printf("|\n");
            }            
        }else{
            // when hit eof or is skippable and not find
            if(data->mode == -1) {
                err->code = ERR_PHP_not_found;
                
                //skippable + not find
                const int expected = snprintf(err->description,sizeof(err->description),"php is not finded, key name for search [%s] don't match with internal.cfg",PHP_path_envKeyName);
                // check buffer overflow
                const int res = catch_err(ERR_snprintf(expected,sizeof(err->description),err));
                if(res) return -1;
            }

            ENV_CONFIG_clear(data);
            if(err->code) return -1;
            return 0;
        }
    }while(exit_code);

    printf("| php found!\n");
    printf("|\n");

    //only track when find
    catch_err(ENV_CONFIG_track_depencity(data,err));
    if(err->code) return -1;

    //store php location in global value
    PHP_LOCATION = malloc(strlen(data->value)+1);
    strcpy(PHP_LOCATION, data->value);
    ENV_CONFIG_clear(data);

    if(PHP_LOCATION == NULL) {
        err->code = ERR_malloc;
        return -1;
    }

    ENV_CONFIG_track_depencity(data,err);
    ENV_CONFIG_clear(data);
    return 1;
}


static int PHP_WinExec(const char *file_name, error_details *err){
    char cmd[MAX_PATH_LEN];
    // absolute_path_to_php/php
    int expected = snprintf(cmd,MAX_PATH_LEN,"%s%cphp.exe %s%c%s%c%s", PHP_LOCATION, SLASH_CHR, ABSOLUTE_PATH, SLASH_CHR, SETUP_FOLDER, SLASH_CHR, file_name);
    const int res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;

    STARTUPINFO si;
    // clean data
    PROCESS_INFORMATION pi;

    //zeromemory = memset
    ZeroMemory( &si, sizeof(si) );
    //size of structure, other flag isn't used
    si.cb = sizeof(si);
    ZeroMemory( &pi, sizeof(pi) );

    // null mean use parent enviroment, lpcurrentdirectory null mean use current directory as caller
    const int state = CreateProcessA(
        NULL,
        cmd,
        0,
        0,
        0,
        CREATE_NO_WINDOW,
        NULL,
        NULL,
        &si,
        &pi
    );

    if(!state) {
        err->code = ERR_WinCreateProcess;
        return -1;
    }

    // Wait until child process exits.
    WaitForSingleObject( pi.hProcess, INFINITE);

    // Close process and thread handles. 
    CloseHandle( pi.hProcess );
    CloseHandle( pi.hThread );

    return res;
}

static void run_all_file(ENV_CONFIG_field *data, error_details *err){
    if(!PHP_LOCATION) {
        err->code = ERR_PHP_not_found;
        return;
    }

    file_node *curr_node = data->file_list;



    //clear depencity list
    while(curr_node != NULL){
        const size_t size_cmd = MAX_BUFFER_SIZE + 13;
        char cmd[size_cmd];

        int expected = 0;

        // if WARNING_FLAGS is set, ask for confirmation before run each file
        if(WARNING_FLAGS){
            printf("|\n");
            //join the current file name and others displays
            char buffer[MAX_TEXT_SIZE];
            expected = snprintf(buffer,MAX_TEXT_SIZE,"run a file: [%s%s%s] (CAUTIONS! THE FILE CAN BE DANGEROUS).", ABSOLUTE_PATH , SLASH ,curr_node->file);
            const int res = catch_err(ERR_snprintf(expected,MAX_TEXT_SIZE,err));
            if(res) return;

            catch_err(confirmation(buffer,err));
            if(err->code) return;

            printf("|______________________________________\n");
        }

        
        putchar('\n');
        printf(" Running file: %s\n",curr_node->file);
        putchar('\n');

        //php
        if(curr_node->extension==1){
            catch_err(ExecPHP_script(curr_node->file,err));
        }else{
            err->code = ERR_invalid_extension;
            return;
        }
        
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
static int ENV_CONFIG_set_all(ENV_CONFIG_field *data, error_details *err){
    int res;
    do{
        res = catch_err(ENV_CONFIG_step_config(data,err));
        if(err->code) return -1;

        if(!res){
            catch_err(ENV_CONFIG_track_depencity(data,err));
            if(err->code) return -1;
        }

        ENV_CONFIG_clear(data);
    }while(!res);
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
static char* set_enviroment(ENV_CONFIG_field *ENV_data,error_details *err){
    char *ENV_FILE_name = malloc(MAX_VALUE_SIZE+5);
    int expected;

    //store it in temp location to set name for .env, don't sufix if NULL
    char buf[MAX_VALUE_SIZE+1]; 
    buf[0] = '\0';

    catch_err(ENV_CONFIG_adjust_key(ENVIROMENT_KEY_NAME,ENV_data,err));
    if(err->code) return NULL;

    
    // if value is NULL, don't sufix. else add "." and value
    if(strcmp(ENV_data->value,"NULL")!=0){
        expected = snprintf(buf,MAX_VALUE_SIZE+1,".%s",ENV_data->value);
        const int res = catch_err(ERR_snprintf(expected,MAX_VALUE_SIZE+1,err));
        if(res) return NULL;
    }else{
        //copy default value to value, since the data is NULL still
        memcpy(ENV_data->value,ENV_data->original_value,MAX_VALUE_SIZE);
        catch_err(ENV_CONFIG_ui_prompt(ENV_data,err));
        if(err->code) return NULL;
    }

    expected = snprintf(ENV_FILE_name,MAX_VALUE_SIZE+5,".env%s",buf);


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
static int is_on(const char *string,error_details *err){
    char* upper_str = catch_err(convert_str_to_upper(string,err));

    if(upper_str==NULL||err->code){
        free(upper_str);
        upper_str = NULL;
        
        err->code = ERR_malloc;
        return 0;
    }

    int on = -1;
    int str_int = convert_str_to_int(upper_str);

    char *ON[] = {on_keyword};
    char *OFF[] = {off_keyword};

    const int ON_count = sizeof(ON)/ sizeof(ON[0]);
    for(int i=0; i < ON_count; i++){
        if(strcmp(upper_str,ON[i]) == 0){
            on = 1;
            break;
        }
    }
    if(str_int > 0 ) on = 1;

    const int OFF_count = sizeof(OFF)/ sizeof(OFF[0]);
    for(int i=0; i< OFF_count; i++){
        if(strcmp(upper_str,OFF[i]) == 0){
            on = 0;
            break;
        }
    }
    if(str_int == 0 ) on = 0;

    if(on<0){
        err->code = ERR_invalid_boolean;
        on = 0;
    }

    free(upper_str);
    upper_str = NULL;

    return on;
}

//set global value WARNING_FLAGS as int(0 false else 1 true), this flag is used to determine if show warning msg for some setting
static void set_warning_flags(ENV_CONFIG_field *data,error_details *err){
    catch_err(ENV_CONFIG_adjust_key(WARNING_FLAG_NAME,data,err));
    if(err->code) return;

    WARNING_FLAGS = catch_err(is_on(data->value,err));
    if(err->code) return;

    ENV_CONFIG_clear(data);
}


static void set_show_debuginfo(ENV_CONFIG_field *data,error_details *err){
    catch_err(ENV_CONFIG_adjust_key(show_debug_KeyName,data,err));
    if(err->code) return;

    SHOW_DEBUG_INFO = catch_err(is_on(data->value,err));
    if(err->code) return;

    ENV_CONFIG_clear(data);
}


static void set_show_ERRlocation(ENV_CONFIG_field *data,error_details *err){
    catch_err(ENV_CONFIG_adjust_key(show_ERR_location,data,err));
    if(err->code) return;

    SHOW_ERR_LINE = catch_err(is_on(data->value,err));
    if(err->code) return;

    ENV_CONFIG_clear(data);
}



static int composer_exists(error_details *err){
    char composer_location[MAX_PATH_LEN];
    const int expected = snprintf(composer_location,MAX_PATH_LEN,"%s%c%s", ABSOLUTE_PATH , SLASH_CHR , "composer.phar");
    const int res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;    

    if(access(composer_location, F_OK)){
        err->code = ERR_COMPOSER_not_found;
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
static int update_depencity(error_details *err,int *expected, char *cmd){
    int res =0;
    //check is up to date, --strict: Return a non-zero exit code for warnings as well as errors.
    *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar validate --check-lock --no-check-version --strict");
    res = catch_err(ERR_snprintf(*expected,MAX_BUFFER_SIZE,err));
    if(res) return -1;  

    const int response = system(cmd);

    // 1 = not up to date
    if(response==1){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar update --with-all-dependencies");
        res = catch_err(ERR_snprintf(*expected,MAX_BUFFER_SIZE,err));
        if(res) return -1;  
    }else if(response==0){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar install");
        res = catch_err(ERR_snprintf(*expected,MAX_BUFFER_SIZE,err));
        if(res) return -1;
    }
    else{
        err->code = ERR_COMPOSER_depencity;
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
static void install_depencity(error_details *err){
    catch_err(composer_exists(err));
    if(err->code) return;

    catch_err(set_certificate_enviroment(err));
    if(err->code) return;

    const int fexist_composer_lock = access("composer.lock", F_OK);
    char cmd[MAX_BUFFER_SIZE];

    int expected;

    //-1 not exist, 0 exist
    if(fexist_composer_lock){
        expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", PHP_LOCATION, SLASH_CHR , ABSOLUTE_PATH , SLASH_CHR , "composer.phar update");
        const int res = catch_err(ERR_snprintf(expected,MAX_BUFFER_SIZE,err));
        if(res) return ;   
    }else{
        catch_err(update_depencity(err, &expected, cmd));
        if(err->code) return;
    }

    if(expected >= MAX_BUFFER_SIZE){err->code = buffer_overflow;err->description[0]='\0'; return;}

    if(system(cmd)){
        err->code = ERR_ssl_cert;
        err->description[0]='\0';
        return;
    }
}



//print main logic of program, run all steps and return immediately if any error happen and change error code
void start_program(ENV_CONFIG_field *internal_data,ENV_CONFIG_field *ENV_data,error_details *err){
    printf("================Internal Setting================\n");
    printf("|\n");
    printf("| The values you are setting now will not be writen in new .env file.\n");
    printf("|\n");

    

    catch_err(set_warning_flags(internal_data,err));
    if(err->code) return;
    catch_err(set_show_debuginfo(internal_data,err));
    if(err->code) return;
    catch_err(set_show_ERRlocation(internal_data,err));
    if(err->code) return;

    const int php_exists = catch_err(PHP_exists(internal_data,err));
    if(!php_exists){err->code = ERR_PHP_not_found; return;}

    catch_err(ENV_CONFIG_set_all(internal_data,err));
    if(err->code) return;

    printf("|\n");
    printf("| All setting is configured in [%s]\n",CONFIG_FILE);
    printf("|\n");
    printf("================Installing depencity================\n");

    catch_err(install_depencity(err));
    if(err->code) return;

    putchar('\n');
    printf("All depencity have been installed sucessfully!\n");

    printf("================ENV================\n");
    printf("|\n");
    printf("| The values you are setting now will be writen in new .env file.\n");
    printf("|\n");

    char *ENV_FILE_name = catch_err(set_enviroment(ENV_data,err));
    if(err->code) return;
    catch_err(ENV_CONFIG_track_depencity(ENV_data,err));
    ENV_CONFIG_clear(ENV_data);
    if(err->code) return;



    
    catch_err(ENV_CONFIG_set_all(ENV_data,err));
    if(err->code) return;

    printf("|\n");
    printf("| All setting is configured in [%s]\n",ENV_EXAMPLE);
    printf("|\n");

    printf("===============writing files===============\n"); 
    printf("|\n");
    printf("| Writing file:%s\n",ENV_FILE_name);


    if(WARNING_FLAGS) {
        catch_err(confirmation("write new file, this can overwrite existing .env file with same name above!",err));
        if(err->code) return;
    }

    catch_err(ENV_CONFIG_write(ENV_data,ENV_FILE_name,err));
    if(err->code) return;

    // release memory
    free(ENV_FILE_name);
    ENV_FILE_name = NULL;

    printf("|\n");
    printf("| File written sucessfully!\n");
    printf("|\n");
    printf("===============Running files===============\n"); 

    catch_err(run_all_file(internal_data,err));
    if(err->code) return;

    printf("\n\n");
    printf("ALL files have been executed sucessfully!\n");
}

