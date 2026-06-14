#include "logic_main.h"

void free_states(config_states **states){
    if(*states == NULL) return;

    global_values *gv = (*states)->flags;
    error_details *err = (*states)->err;

    ERR_details_destroy(&err);

    free(gv->ABSOLUTE_PATH);
    free(gv->PHP_LOCATION);
    gv->ABSOLUTE_PATH = NULL;
    gv->PHP_LOCATION = NULL;

    free(gv);
    gv = NULL;

    free(*states);
    *states = NULL;
}







int set_abs_path(config_states *state){
    error_details *err = state->err;
    global_values *gv = state->flags;

    gv->ABSOLUTE_PATH = malloc(MAX_PATH_LEN);

    if(!(gv->ABSOLUTE_PATH)){
        err->code = ERR_malloc;
        strcpy(err->description, "Out of memory! fail to set global value for current directory");
        return -1;
    }

    //get absolute path of current folder
    if(strcmp(OS,"Windows") == 0){
        FILE *fp = popen("cd","r");
        fgets(gv->ABSOLUTE_PATH,MAX_PATH_LEN,fp);

        if (pclose(fp)) {
            err->code = ERR_pclose;
            return -1;
        }
    }else{
        gv->ABSOLUTE_PATH = getcwd(gv->ABSOLUTE_PATH,MAX_PATH_LEN);
    }

    if(gv->ABSOLUTE_PATH == NULL){
        err->code = ERR_PATH_get_curr_abs;
        strcpy(err->description, strerror(errno));
        return -1;
    }else{
        char *pos; //position of \n and \r in the end of path
        //remove \n and \r in the end of path
        pos = strchr(gv->ABSOLUTE_PATH,'\n');
        if(pos!=NULL) *pos = '\0';

        pos = strchr(gv->ABSOLUTE_PATH,'\r');
        if(pos!=NULL) *pos = '\0';
    }
    return 0;
}

/**
 * set temporary environment variable SSL_CERT_FILE in current process
 * @return 0 if sucess else -1
 */
static int set_certificate_environment(config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    char abs_path[MAX_PATH_LEN];
    char abs_dir[MAX_PATH_LEN];

    int sucess = 0;
    int expected;
    int res;

    expected = snprintf(abs_path,MAX_PATH_LEN,"%s%c%s%c%s", gv->ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR, SLASH_CHR , CERTIFICATES_FILE_NAME);
    res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;

    snprintf(abs_dir,MAX_PATH_LEN,"%s%c%s", gv->ABSOLUTE_PATH, SLASH_CHR , CERTIFICATES_DIR);
    res = catch_err(ERR_snprintf(expected,MAX_PATH_LEN,err));
    if(res) return -1;

    sucess = setenv("SSL_CERT_FILE", abs_path);
    if(!sucess) sucess = setenv("SSL_CERT_DIR", abs_dir);
    

    if(sucess){
        err->code = ERR_set_EnvVal;
        strcpy(err->description, "Failed to set environment value of certificate for composer SSL certificate");
        return -1;
    }

    return sucess;
}

/**
 * print confirmation msg:
 * 
 * @param action printed after: "| action:"\n "| confirmation text"
 * @param confirmation_text
 * 
 * - if confirmation_text passed as NULL-> "Are you sure about that?"
 * 
 * - if confirmation_text passed as "" -> text skipped
 * @return
 * 
 * - 0 = denied
 * 
 * - 1 = accepted
 * 
 * - -1 = error: buffer_overflow, enconding error
*/ 
static int confirmation(const char *action,const char *confirmation_text,error_details *err){
    char answer[MAX_INPUT_SIZE];
    char text[MAX_TEXT_SIZE];
    
    int res;

    res = catch_err(ERR_snprintf(snprintf(text,MAX_PATH_LEN,"| %s", action),sizeof(text),err));
    if(res) return -1;

    char confirmation_txt[MAX_TEXT_SIZE];

    if(confirmation_text != NULL && confirmation_text[0]!= '\0'){
        res = catch_err(ERR_snprintf(snprintf(confirmation_txt,MAX_PATH_LEN,"| %s", confirmation_text),sizeof(confirmation_txt),err));
        if(res) return -1;
    }

    while(1){
        display_wrapped_text(text,"| ",0,MAX_LINE_CHR); putchar('\n');
        if(confirmation_text !=NULL) {
            if(confirmation_text[0]!= '\0'){display_wrapped_text(confirmation_txt,"| ",0,MAX_LINE_CHR); putchar('\n');}
        }
        else {display_wrapped_text("| Are you sure about that?","| ",0,MAX_LINE_CHR); putchar('\n');}

        printf("|  |__[Y/N]:");
        scanf("%39s",answer);
        if(answer[0]!='y'&& answer[0]!='Y'){
            return 0;
        }else return 1;
    }
    return 0;
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
static int PHP_exists(ENV_CONFIG_field *data, config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    const size_t size_cmd = MAX_BUFFER_SIZE + 13;
    char cmd[size_cmd];

    int exit_code;
    int res;
    
    //if not finded php path, loop until find
    do{
        //find where is php path
        const int find = catch_err(ENV_CONFIG_adjust_key(PHP_path_envKeyName,data,NULL,internal_mode,states));
        if(err->code) return -1;
        // 0 mean key name error and negative mean error like malloc
        if(find){
            char *right_path = catch_err(normalize_path(data->value,err));
            if(err->code) return -1;

            if(right_path==NULL) {
                err->code = ERR_PATH_invalid;
                return -1;
            }

            
            strcpy(data->value,right_path);

            free(right_path);
            right_path = NULL;

            if(strcmp(OS,"Windows")==0){
                // join real php path
                const int expected = snprintf(cmd,size_cmd,"%s\\php.exe",data->value);
                // check buffer overflow
                res = catch_err(ERR_snprintf(expected,size_cmd,err));
                if(res) return -1;

                exit_code = access(cmd,F_OK);
            }

            //not find, tell to user
            if(exit_code) {
                char msg[MAX_TEXT_SIZE];
                // check buffer overflow
                res = catch_err(ERR_snprintf(
                    snprintf(msg,size_cmd,"| php was not found. Please ensure PHP is installed and provided path[%s] is correct!",data->value),
                    sizeof(msg),err));
    
                if(res) return -1;
                display_wrapped_text(msg,"| ",0,MAX_LINE_CHR); putchar('\n');
                printf("|\n");
            }            
        }else{
            // when hit eof or is skippable and not find
            if(data->mode == -1) {
                err->code = ERR_PHP_not_found;
                
                // check buffer overflow
                res = catch_err(ERR_snprintf(
                    snprintf(err->description,sizeof(err->description),
                            "Unable to locate PHP path: the search key [%s] for PHP location does not match inside file[%s].",PHP_path_envKeyName,INTERNAL_CONFIG_FILE_NAME),
                    sizeof(err->description),err));
                if(res) return -1;
            }

            ENV_CONFIG_clear(data);
            if(err->code) return -1;
            return 0;
        }
    }while(exit_code);

    printf("|\n");
    display_wrapped_text("| php found!","| ",0,MAX_LINE_CHR); putchar('\n');
    if(gv->SIMPLIFIED_DISPLAY) printf("|\n");

    //only track when find
    catch_err(ENV_CONFIG_track_depencity(data,err));
    if(err->code) return -1;

    //store php location in global value
    gv->PHP_LOCATION = malloc(strlen(data->value)+1);
    strcpy(gv->PHP_LOCATION, data->value);
    ENV_CONFIG_clear(data);

    if(gv->PHP_LOCATION == NULL) {
        err->code = ERR_malloc;
        return -1;
    }

    ENV_CONFIG_track_depencity(data,err);
    ENV_CONFIG_clear(data);
    return 1;
}


static int PHP_WinExec(const char *file_name, config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    int res;
    //pipe is buffer inside OS in somewhere
    //Handle act like pointer to pipe(protected and hidden by OS)
    // index of pipe
    HANDLE hChild_Output_write = NULL;
    // where to write output(also index of pipe)
    HANDLE hChild_Output_read = NULL;
    SECURITY_ATTRIBUTES sa;

    STARTUPINFO si;
    PROCESS_INFORMATION pi;

    //we need security attribute to able inherit handle, in other word, allow child to acess memory(write/read)
    sa.bInheritHandle = 1;
    sa.nLength = sizeof(SECURITY_ATTRIBUTES);
    sa.lpSecurityDescriptor = NULL; //control acess to obj, NULL is default, idk what it does

    //createPipe(hReadPipe,hWritePipe,lpPipeAttributes,nSize)
    if(!CreatePipe(&hChild_Output_read,&hChild_Output_write,&sa,0)){
        err->code = ERR_WinApi;
        res = catch_err(ERR_snprintf(snprintf(err->description,sizeof(err->description),"Cannot create pipe"),MAX_VALUE_SIZE+5,err));
        if(res) return -1;
        return -1;
    }

    //create command to execute php file, like: "absolute_path_to_php/php absolute_path_to_file_to_run"
    char cmd[MAX_PATH_LEN];
    // absolute_path_to_php/php
    res = catch_err(ERR_snprintf(snprintf(
                cmd,MAX_PATH_LEN,"%s%cphp.exe %s%c%s%c%s", gv->PHP_LOCATION, SLASH_CHR, gv->ABSOLUTE_PATH, SLASH_CHR, SETUP_FOLDER, SLASH_CHR, file_name
            ),
        MAX_PATH_LEN,err)
    );
    if(res) return -1;


    // clean data
    //zeromemory = memset
    ZeroMemory( &si, sizeof(si) );
    //size of structure
    si.cb = sizeof(si);
    //dwY, dwX, dwXSize, dwYSize, dwXCountChars, dwYCountChars, dwFillAttribute, lpDesktop and lpTitle is useless due CREATE_NO_WINDOW
    // dwX, dwY need STARTF_USEPOSITION in dwflag; dwXSize, dwYSize need STARTF_USESIZE in dwflag; dwXCountChars, dwYCountChars need STARTF_USECOUNTCHARS in dwflag; dwFillAttribute need STARTF_USEFILLATTRIBUTE in dwflag;

    //STARTF_FORCEONFEEDBACK cursor is force to have loading no matter default setting. STARTF_FORCEOFFFEEDBACK opposide. useless for CREATE_NO_WINDOW
    //STARTF_PREVENTPINNING useless for CREATE_NO_WINDOW
    //STARTF_RUNFULLSCREEN useless for CREATE_NO_WINDOW
    //STARTF_TITLEISAPPID is for taskbar and start menu, useless for CREATE_NO_WINDOW
    //STARTF_TITLEISLINKNAME and STARTF_UNTRUSTEDSOURCE is for security, useless for CREATE_NO_WINDOW
    //STARTF_USECOUNTCHARS, STARTF_USEFILLATTRIBUTE, STARTF_USE, STARTF_USEPOSITION, STARTF_USESHOWWINDOW, STARTF_USESIZE are mostly for GUI, useless for CREATE_NO_WINDOW
    //STARTF_USESTDHANDLES of course is our target since we want to use pipe, |= is to only change byte in dwFlags we want and keep other byte unchanged.
    si.dwFlags |= STARTF_USESTDHANDLES;

    //make createProcessA know where to write the result of process, in this case, write to pipe, and read from pipe in parent process
    si.hStdError = hChild_Output_write;
    si.hStdOutput = hChild_Output_write; //specifing index
    si.hStdInput = NULL; // we don't need new command insert
    ZeroMemory( &pi, sizeof(pi) );




    // null mean use parent environment, lpcurrentdirectory null mean use current directory as caller
    const int state = CreateProcessA(
        NULL,                   // Module
        cmd,                    // Command-line
        0,                      // Process security attributes
        0,                      // Primary thread security attributes
        1,                      // Handles are inherited
        CREATE_NO_WINDOW,       // Creation flags
        NULL,                   // Environment (use parent)
        NULL,                   // Current directory (use parent)
        &si,                    // STARTUPINFO pointer
        &pi                     // PROCESS_INFORMATION pointer
    );

    if(!state) {
        //end handle to prevent deadlock since child process won't close it and parent process need to wait until child process write and close it
        CloseHandle( hChild_Output_write );
        CloseHandle( hChild_Output_read );

        err->code = ERR_WinApi;
        res = catch_err(ERR_snprintf(snprintf(err->description,sizeof(err->description),"Error in CreateProcessA"),MAX_VALUE_SIZE+5,err));
        if(res) return -1;
        return -1;
    }

    //end handle to prevent deadlock since child process won't close it and parent process need to wait until child process write and close it
    CloseHandle( hChild_Output_write );

    int bStatus;
    char buffer[4096];
    DWORD dwRead;

    //read out put and print it, use while because child process can still running, even buffer is maxed, it can still print all of them
    while(1){
        //hFile, file/stream to read, in this case, pipe stdout
        // buffer to be filled with data read, in this case, buffer to store output of process
        // sizeof(buffer)-1 maxium can be read, reserved 1 for \0
        // lpNumberOfBytesRead &dwRead => pointer to the variable of number of bytes read, in this case, store number of bytes read from pipe
        // lpOverlapped NULL mean not use overlapped I/O
        bStatus = ReadFile( hChild_Output_read, buffer, sizeof(buffer)-1, &dwRead, NULL);
        if(!bStatus || dwRead == 0) break; //when read fail or reach end of file, break loop
        buffer[dwRead]= '\0';

        // newline will be included in buffer, so no need to add extra newline when print
        printf("%s",buffer);
    }


    // Wait until child process exits.
    WaitForSingleObject( pi.hProcess, INFINITE );
    CloseHandle( hChild_Output_read );

    // Close process and thread handles. 
    CloseHandle( pi.hProcess );
    CloseHandle( pi.hThread );


    return res;
}

static void run_all_file(ENV_CONFIG_field *data, config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    if(!gv->PHP_LOCATION) {
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
        if(gv->WARNING_FLAGS){
            printf("|\n");
            //join the current file name and others displays
            char buffer[MAX_TEXT_SIZE];
            if(gv->SIMPLIFIED_DISPLAY){
                expected = snprintf(buffer,MAX_TEXT_SIZE,"Warning: Attempting to run [%s%s%s] (Potentially dangerous file).", gv->ABSOLUTE_PATH , SLASH ,curr_node->file);
            }else{
                display_wrapped_text("| CAUTION: This program is attempting to execute a file!","| ",0,MAX_LINE_CHR); putchar('\n');
                display_wrapped_text("| WARNING: Executing unknown files can harm your system.","| ",0,MAX_LINE_CHR); putchar('\n');
                
                expected = snprintf(buffer,MAX_TEXT_SIZE,
                    "Path: [%s%s%s]",
                    gv->ABSOLUTE_PATH , SLASH ,curr_node->file
                );
            }

            const int res = catch_err(ERR_snprintf(expected,MAX_TEXT_SIZE,err));
            if(res) return;

            const int agree = catch_err(confirmation(buffer,NULL,err));

            printf("|______________________________________\n");
            if(!agree){
                err->code = ERR_permition_denied;
                return;
            }
        }

        
        putchar('\n');

        char msg[MAX_TEXT_SIZE + strlen(curr_node->file)];

            
        const int res = catch_err(ERR_snprintf(
            snprintf(msg,MAX_TEXT_SIZE,"Running file: [%s]", curr_node->file),
            sizeof(msg),err));
        if(res) return;

        display_wrapped_text(msg," ",0,MAX_LINE_CHR);
        putchar('\n');

        //php
        if(curr_node->extension==1){
            catch_err(ExecPHP_script(curr_node->file,states));
            printf("\n_______________________________________\n");
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
 * @param mode (r_mode)0 = read mode else (w_mode)1 will ask to set
 * @return
 * - 0 sucess and reach EOF
 * 
 * - -1 error: buffer overflow, malloc error, invalid extension if is file
 */
static int ENV_CONFIG_set_all(ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,const int mode, config_states *states){
    int res;

    error_details *err = states->err;

    do{
        res = catch_err(ENV_CONFIG_step_config(data,prev_data,mode,states));
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
 * set environment file name and environment.
 * @return
 * - file name
 * - NULL when error happen
 */
static char* set_environment(ENV_CONFIG_field *ENV_data,config_states *state){
    error_details *err = state->err;

    char *ENV_FILE_name = malloc(MAX_VALUE_SIZE+5);
    int expected;

    //store it in temp location to set name for .env, don't sufix if NULL
    char buf[MAX_VALUE_SIZE+1]; 
    buf[0] = '\0';

    //selecting file name
    catch_err(ENV_CONFIG_adjust_key(ENVIRONMENT_KEY_NAME,ENV_data,NULL,w_mode,state));
    if(err->code) return NULL;

    
    // if value is NULL, don't sufix. else add "." and value
    if(strcmp(ENV_data->value,"NULL")!=0){
        expected = snprintf(buf,MAX_VALUE_SIZE+1,".%s",ENV_data->value);
        const int res = catch_err(ERR_snprintf(expected,MAX_VALUE_SIZE+1,err));
        if(res) return NULL;
    }else{
        //copy default value to value, since the data is still NULL
        memcpy(ENV_data->value,ENV_data->original_value,MAX_VALUE_SIZE);

        //ask again so value set won't be NULL in .env
        if(state->flags->SIMPLIFIED_DISPLAY) {
            printf("|\n");
            display_wrapped_text("| Suffix removed. Configuring base .env file - please re-enter value:", "| ",0,MAX_LINE_CHR); putchar('\n');
        }else{
            printf("|\n");
            printf("|  ||---------------- NOTICE ----------------\n");
            display_wrapped_text("|  || Switched to base .env configuration. (Input was 'null').", "|  || ",0,MAX_LINE_CHR); putchar('\n');
            display_wrapped_text("|  || Please provide a new value for the .env file", "|  || ",0,MAX_LINE_CHR); putchar('\n');
            printf("|  ||----------------------------------------\n");
        }
        catch_err(ENV_CONFIG_ui_prompt(ENV_data,state));
        if(err->code) return NULL;
    }

    expected = snprintf(ENV_FILE_name,MAX_VALUE_SIZE+5,".env%s",buf);
    const int res = catch_err(ERR_snprintf(expected,MAX_VALUE_SIZE+5,err));
    if(res) return NULL;


    char *normalized_path = catch_err(normalize_path(ENV_FILE_name,err));
    memcpy(ENV_FILE_name,normalized_path,MAX_VALUE_SIZE+5);

    free(normalized_path);
    normalized_path = NULL;

    if(err->code) return NULL;

    return ENV_FILE_name;
}

//-1 if not else 1
static int is_on(const char *string,error_details *err){
    char *upper_str = catch_err(convert_str_to_upper(string,err));
    if(err->code) return -1;

    int str_int = convert_str_to_int(string);
    if(str_int > 0) return 1;

    char *ON[] = {on_keyword};
    
    const int ON_count = sizeof(ON)/ sizeof(ON[0]);
    for(int i=0; i < ON_count; i++){
        if(strcmp(upper_str,ON[i]) == 0){
            free(upper_str);
            upper_str = NULL;
            return 1;
        }
    }

    free(upper_str);
    upper_str = NULL;
    return -1;
}

// -1 if not else 0
static int is_off(const char *string,error_details *err){
    char *upper_str = catch_err(convert_str_to_upper(string,err));
    if(err->code) return -1;

    int str_int = convert_str_to_int(string);
    if(str_int == 0 ) return 0;

    char *OFF[] = {off_keyword};
    
    const int OFF_count = sizeof(OFF)/ sizeof(OFF[0]);
    for(int i=0; i< OFF_count; i++){
        if(strcmp(upper_str,OFF[i]) == 0){
            free(upper_str);
            upper_str = NULL;
            return 0;
        }
    }
    free(upper_str);
    upper_str = NULL;

    return -1;
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
 * @return 0 false | 1 true | -1 error
 */
static int find_bool(const char *string,error_details *err){
    char* upper_str = catch_err(convert_str_to_upper(string,err));

    if(upper_str==NULL||err->code){
        free(upper_str);
        upper_str = NULL;
        
        err->code = ERR_malloc;
        return 0;
    }

    int on = -1;

    on = is_on(string,err);
    if(on<0) on = is_off(string,err);

    if(on<0){
        const int res = catch_err(ERR_snprintf(snprintf(err->description,sizeof(err->description),"Invalid boolean value provided for [%s].",string),MAX_VALUE_SIZE+5,err));
        if(res) return -1;
        err->code = ERR_invalid_boolean;
        on = 0;
    }

    free(upper_str);
    upper_str = NULL;

    return on;
}

//set global values(int), can only be internal.cfg's data. pass pointer to global value in state struct.
static void set_global_flags(ENV_CONFIG_field *data,char *key_name,int *gv_target,config_states *states){
    error_details *err = states->err;

    catch_err(ENV_CONFIG_adjust_key(key_name,data,NULL,internal_mode,states));
    if(err->code) return;

    *gv_target = catch_err(find_bool(data->value,err));
    if(err->code) return;

    ENV_CONFIG_clear(data);
}




static int composer_exists(config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    char composer_location[MAX_PATH_LEN];
    const int expected = snprintf(composer_location,MAX_PATH_LEN,"%s%c%s", gv->ABSOLUTE_PATH , SLASH_CHR , "composer.phar");
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
static int update_depencity(config_states *states,int *expected, char *cmd){
    error_details *err = states->err;
    global_values *gv = states->flags;

    int res =0;
    //check is up to date, --strict: Return a non-zero exit code for warnings as well as errors.
    *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", gv->PHP_LOCATION, SLASH_CHR , gv->ABSOLUTE_PATH , SLASH_CHR , "composer.phar validate --check-lock --no-check-version --strict");
    res = catch_err(ERR_snprintf(*expected,MAX_BUFFER_SIZE,err));
    if(res) return -1;  

    const int response = system(cmd);

    // 1 = not up to date
    if(response==1){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", gv->PHP_LOCATION, SLASH_CHR , gv->ABSOLUTE_PATH , SLASH_CHR , "composer.phar update --with-all-dependencies");
        res = catch_err(ERR_snprintf(*expected,MAX_BUFFER_SIZE,err));
        if(res) return -1;  
    }else if(response==0){
        *expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", gv->PHP_LOCATION, SLASH_CHR , gv->ABSOLUTE_PATH , SLASH_CHR , "composer.phar install");
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
 * - error: ssl_cert_error, ERR_set_environment_value, buffer overflow
 */
static void install_depencity(config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;

    catch_err(composer_exists(states));
    if(err->code) return;

    catch_err(set_certificate_environment(states));
    if(err->code) return;

    const int fexist_composer_lock = access("composer.lock", F_OK);
    char cmd[MAX_BUFFER_SIZE];

    int expected;

    //-1 not exist, 0 exist
    if(fexist_composer_lock){
        expected = snprintf(cmd,MAX_BUFFER_SIZE,"%s%cphp %s%c%s", gv->PHP_LOCATION, SLASH_CHR , gv->ABSOLUTE_PATH , SLASH_CHR , "composer.phar update");
        const int res = catch_err(ERR_snprintf(expected,MAX_BUFFER_SIZE,err));
        if(res) return ;   
    }else{
        catch_err(update_depencity(states, &expected, cmd));
        if(err->code) return;
    }

    if(expected >= MAX_BUFFER_SIZE){err->code = buffer_overflow; return;}

    if(system(cmd)){
        err->code = ERR_ssl_cert;
        return;
    }
}


// return 1 if overwrite, else use prev data, while asking to user
static int ask_overwrite(const char *file_name,error_details *err){
    char answer[MAX_INPUT_SIZE];
    char buf[MAX_TEXT_SIZE];

    const int expected = snprintf(buf,sizeof(buf),"| Existing Configuration file [%s] found in project root",file_name);
    const int res = catch_err(ERR_snprintf(expected,sizeof(buf),err));
    if(res) return -1;

    do{
        printf("|\n");
        display_wrapped_text(buf,"|",0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("| Select a conflict resolution strategy:","|",0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  `--[1. Overwrite ALL | 2. Append new changes]:","|     ",0,MAX_LINE_CHR);
        fgets(answer, MAX_INPUT_SIZE, stdin);
    }while(answer[0]!='1' && answer[0]!='2');

    return answer[0] - '0';
}



/**
 * find if has alredy data written in selected file.
 * 
 * if true, return previous data inside ENV_CONFIG_field else NULL
 * 
 * for mode = overwrite(1), ONLY ENVIRONMENT VALUE will be registered and need be null after use, for mode = append(2), ALL VALUE will be registered
 */
static ENV_CONFIG_field *has_prev_data(const char *file_name, int *mode_selected, config_states *state){
    error_details *err = state->err;
    global_values *gv = state->flags;

    char abs_path_of_file[MAX_PATH_LEN + 5];
    const int expected = snprintf(abs_path_of_file,sizeof(abs_path_of_file),"%s%c%s",gv->ABSOLUTE_PATH, SLASH_CHR, file_name);
    const int res = catch_err(ERR_snprintf(expected,sizeof(abs_path_of_file),err));
    if(res) return NULL;
    
    if(!(access(abs_path_of_file, F_OK))){
        *mode_selected = catch_err(ask_overwrite(file_name,err));
        if(err->code) return NULL;

        ENV_CONFIG_field *prev_data = catch_err(ENV_init_config_struct(file_name,err));
        if(err->code) return NULL;

        if(*mode_selected==1) {
            return NULL;
        }
        else if(*mode_selected==2){
            catch_err(ENV_CONFIG_set_all(prev_data,NULL,r_mode,state));
        }
        else{
            err->code = ERR_invalid_mode;
            return NULL;
        }
        if(err->code) return NULL;
        return prev_data;
    }else return NULL;
}

//return 1 if user agree else 0
static int ask_keep_value(const char *key_name,const char *val,error_details *err){
    char msg[MAX_TEXT_SIZE];
    const int res = catch_err(ERR_snprintf(snprintf(msg,sizeof(msg),"Do you want keep setting [%s] with value [%s]",key_name,val),sizeof(msg),err));
    if(res) return -1;

    const int agree = catch_err(confirmation(msg,"",err));
    if(err->code) return -1;

    if(agree) return 1;
    return 0;
}

// 1 keep all , 2 keep partially, 3 discart all
static void use_old_value(const int mode,ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,error_details *err){
    if(mode == 3) {
        data->affected_data += prev_data->total_data;
        return;
    }
    
    for(config_node *curr_prev_node = prev_data->depencity_list;
        curr_prev_node!=NULL;
        curr_prev_node = curr_prev_node->next
    ){
        if(mode==2){
            const int keep = catch_err(ask_keep_value(curr_prev_node->key,curr_prev_node->value,err));
            if(!keep) {
                data->affected_data++;
                continue;
            }
        }

        memcpy(data->key,curr_prev_node->key,sizeof(data->key));
        memcpy(data->value,curr_prev_node->value,sizeof(data->value));

        catch_err(ENV_CONFIG_track_depencity(data,err));
        ENV_CONFIG_clear(data);
        if(err->code) return;
    }
}


static void check_remain_prev_data(ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,error_details *err){
    if(prev_data==NULL) return;
    if(prev_data->depencity_list!=NULL){
        data->total_data += prev_data->total_data;
        char answer[MAX_INPUT_SIZE];

        char msg[MAX_TEXT_SIZE];
            const int res = catch_err(ERR_snprintf(
                snprintf(msg,sizeof(msg),"| Found %i values in existing .env file that are not defined in template(.env.example)!", prev_data->total_data),
                sizeof(msg),err)
            );
        if(res) return;

        printf("|\n");
        display_wrapped_text(msg,"| ",0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("| Chose an action:","| ",0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("| `--[1. keep all | 2. keep some | 3. discard all]:","| ",0,MAX_LINE_CHR);
        do{
            fgets(answer,sizeof(answer),stdin);
        }while(answer[0]>'3' || answer[0]<0);

        catch_err(use_old_value(answer[0]-'0',data,prev_data,err));
    }
    return;
}



static char *select_file_name(ENV_CONFIG_field *data,config_states *state){
    error_details *err = state->err;
    while(1){
        char *ENV_FILE_name = catch_err(set_environment(data,state));
        if(err->code) return NULL;

        if(strcmp(ENV_FILE_name,ENV_EXAMPLE_FILE_NAME)== 0){
            char msg[MAX_TEXT_SIZE];
            const int res = catch_err(ERR_snprintf(
                snprintf(msg,sizeof(msg),"| Destination file name cannot be same as the template file name[%s]", ENV_EXAMPLE_FILE_NAME),
                sizeof(msg),err)
            );
            if(res) return NULL;

            display_wrapped_text(msg,"| ",0,MAX_LINE_CHR); putchar('\n');
            printf("| \n");
            continue;
        }else return ENV_FILE_name;
    }
    return NULL;
}


ENV_CONFIG_field *start_program(ENV_CONFIG_field *internal_data,ENV_CONFIG_field *ENV_data,config_states *states){
    error_details *err = states->err;
    global_values *gv = states->flags;
    catch_err(set_global_flags(internal_data,SIMPLIFIED_DISPLAY_NAME,&(gv->SIMPLIFIED_DISPLAY),states));
    if(err->code) return NULL;

    printf("================Selecting Destination File================\n");
    printf("|\n");
    display_wrapped_text("| The values you configure now will be written to your new environment file","| ",0,MAX_LINE_CHR); putchar('\n');

    char *ENV_FILE_name = catch_err(select_file_name(ENV_data,states));
    if(err->code) return NULL;
    
    catch_err(ENV_CONFIG_track_depencity(ENV_data,err));
    if(err->code) return NULL;

    int selected_mode;
    ENV_CONFIG_field *prev_data = catch_err(has_prev_data(ENV_FILE_name,&selected_mode,states));
    if(err->code) return NULL;

    //find key ENVIROMENT in previous data and compare with current inputed data, if same, affected_data -1 since it won't be affected, else keep it since it will be affected
    if(prev_data){
        //check storage
        config_node *curr_node = prev_data->depencity_list;

        while(curr_node!=NULL){
            //comparing previous data env name
            if(strcmp(curr_node->key,ENVIRONMENT_KEY_NAME) == 0) {
                //compare
                if(strcmp(curr_node->value,ENV_data->value) == 0) ENV_data->affected_data--;
                break;
            }
            curr_node = curr_node->next; //move forward
        }
    }
    if(selected_mode == 1) ENV_CONFIG_destroy(&prev_data);

    ENV_CONFIG_clear(ENV_data);

    gv->NEWLINE_BETWEEN_VARIABLES = 1;
    printf("|\n");
    printf("================Internal Setting================\n");
    printf("|\n");
    display_wrapped_text("| The values configured here will not be written to your new environment file.","| ",0,MAX_LINE_CHR); putchar('\n');
    display_wrapped_text("| They are settings required to run the this executable.","| ",0,MAX_LINE_CHR); putchar('\n');

    //setting all global flags to use
    catch_err(set_global_flags(internal_data,WARNING_FLAG_NAME,&(gv->WARNING_FLAGS),states));
    if(err->code) return prev_data;

    catch_err(set_global_flags(internal_data,show_debug_KeyName,&(gv->SHOW_DEBUG_INFO),states));
    if(err->code) return prev_data;

    catch_err(set_global_flags(internal_data,show_ERR_location,&(gv->SHOW_ERR_LINE),states));
    if(err->code) return prev_data;

    catch_err(set_global_flags(internal_data,show_r_only_name,&(gv->SHOW_READ_ONLY),states));
    if(err->code) return prev_data;


    const int php_exists = catch_err(PHP_exists(internal_data,states));
    if(!php_exists){err->code = ERR_PHP_not_found; return prev_data;}

    catch_err(ENV_CONFIG_set_all(internal_data,NULL,internal_mode,states));
    if(err->code) return prev_data;

    printf("|\n");
    printf("| All setting have been configured in [%s]\n",CONFIG_FILE);
    printf("|\n");
    gv->NEWLINE_BETWEEN_VARIABLES = 1;
    printf("================Installing depencity================\n");
    display_wrapped_text("Important: This executable uses Composer to install required packages."," ",0,MAX_LINE_CHR); putchar('\n');
    putchar('\n');

    catch_err(install_depencity(states));
    if(err->code) return prev_data;

    putchar('\n');
    display_wrapped_text("All dependencies have been successfully installed!!","| ",0,MAX_LINE_CHR); putchar('\n');
    putchar('\n');

    gv->NEWLINE_BETWEEN_VARIABLES = 1;
    printf("================ENV================\n");
    printf("|\n");
    printf("| The values you are setting now will be writen in new .env file.\n");


    catch_err(ENV_CONFIG_set_all(ENV_data,prev_data,w_mode,states));
    if(err->code) return prev_data;

    check_remain_prev_data(ENV_data,prev_data,err);
    if(err->code) return prev_data;

    printf("|\n");
    if(ENV_data->affected_data > 0){
        printf("| All setting have been configured in [%s]\n",ENV_FILE_name);
        printf("| Affected data: %i/%i\n",ENV_data->affected_data,ENV_data->total_data);
    }
    else{
        printf("| No new settings were configured.\n");
    }
    printf("|\n");

    gv->NEWLINE_BETWEEN_VARIABLES = 1;

    if(ENV_data->affected_data > 0){
        printf("===============writing files===============\n"); 
        printf("|\n");
        printf("| Writing file:[%s]\n",ENV_FILE_name);


        if(gv->WARNING_FLAGS) {
            const int agree = catch_err(confirmation("This program are trying to write new file, this can overwrite existing .env file with same name above!",NULL,err));
            if(!agree){
                err->code = ERR_permition_denied;
                return prev_data;
            }
        }

        catch_err(ENV_CONFIG_write(ENV_data,ENV_FILE_name,err));
        if(err->code) return prev_data;

        printf("|\n");
        display_wrapped_text("| File have been written sucessfully!","| ",0,MAX_LINE_CHR); putchar('\n');
        printf("|\n");
    }

    // release memory
    free(ENV_FILE_name);
    ENV_FILE_name = NULL;


    gv->NEWLINE_BETWEEN_VARIABLES = 1;

    if(internal_data->file_quant>0){
        printf("===============Running files===============\n"); 
        if(!gv->SIMPLIFIED_DISPLAY) {
            printf("| Total files found for execution: %i\n", internal_data->file_quant);
            printf("=========================================================\n");
        }else{
            printf("| executing %i files\n",internal_data->file_quant);
        }

        catch_err(run_all_file(internal_data,states));
        if(err->code) return prev_data;

        printf("\n\n");
        display_wrapped_text("ALL files have been executed sucessfully!"," ",0,MAX_LINE_CHR);
        putchar('\n');
    }
    

    return prev_data;
}

