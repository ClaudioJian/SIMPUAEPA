#include "ENV_UI.h"


//print msg when invalid_file
static void msg_invalid_file(const int valid){
    printf("|  ||--------------ERROR---------------\n");
    if(valid==0) printf("|  || invalid extension!\n");
    else if(valid == -1) printf("|  || invalid path!\n");
            
    printf("|  || Try again!\n");
    printf("|  ||----------------------------------\n");
    printf("|  ||\n");
}




/// @brief check file and path
/// @return positive int=invalid  0=sucess -1 error: 
///
/// - malloc error, buffer overflow, invalid extension
static int valid_file(const char *target,error_details *err,char *filtered){
    //normalize default value or inputed value(store in buffer to free after)
    char* buffer = catch_err(normalize_path(target,err));

    if(err->code) return -1;
    strcpy(filtered,buffer);

    free(buffer);
    buffer = NULL;

    int valid = 1;
    // if path invalid, set to -1
    if(filtered==NULL) valid = -1;    

    //if still valid(positive unchanged), check extension
    if(valid>0){
        valid = catch_err(valid_extension(filtered,err));
        if(err->code) return -1;
    }

    //check if is valid
    if(valid<=0){
        msg_invalid_file(valid);
        return 1;
    }      
    return 0;
}





int ENV_CONFIG_step_config(ENV_CONFIG_field *data, error_details *err){
    if(data->is_EOF) return 1;
    
    int not_EOF = 1;
    // if isn't first time acess data in .env.example, skip to first non comment line
    if(ftell(data->fp) < data->start_offset) fseek(data->fp,data->start_offset,SEEK_SET);

    //loop until find and skip alredy setted value. EOF = 0 = stop
    while(1){
        not_EOF = catch_err(ENV_CONFIG_scan_next_data(data,err));

        if(!not_EOF) {
            data->is_EOF = 1;
            ENV_CONFIG_clear(data);
            return 1;
        }

        if(err->code) return -1;
        // 1 = set 0 = not set = break
        if(!ENV_CONFIG_is_alredy_set(data->is_file ? data->original_value:data->key ,data)) break;
        ENV_CONFIG_clear(data);
    }

    //if flag set to skip, don't let user change it
    if(data->mode >= 0) {catch_err(ENV_CONFIG_ui_prompt(data,err));}
    if(err->code) return -1;
    
    return 0;
}



int ENV_CONFIG_adjust_key(const char *setting, ENV_CONFIG_field *data, error_details *err){
    if(ENV_CONFIG_is_alredy_set(setting,data))return 1;
    const int find = catch_err(ENV_CONFIG_match(setting,data,err));
    
    if(err->code) return -1;

    if(!find) {
        printf("| cannot find setting: %s\n", setting);
        return 0;
    }

    //skip when is labbered as skip
    if(data->mode >= 0) {catch_err(ENV_CONFIG_ui_prompt(data,err));}
    if(err->code) return -1;

    return 1;
}



/**
 * display label explain what value do. switch to new line automatically.
 * 
 * If the work are too long, add "-" at end of word and print rest of it in new line.
 * otherwise, if it just hit max_line_chr, switch to new line and print all of it in new line.
 * 
 * required label will be displayed at end. If e flag(mode=2) is set, display that value can be ignored in run time.
 * @param max_line_chr max number of char can one line hold before switch to new line.(cannot be smaller than 17)
 * @return
 * 
 * -error when: buffer overflow
 */
static void print_label(ENV_CONFIG_field *data,size_t max_line_chr,error_details *err){
    if(max_line_chr<17) max_line_chr = 17;
    if(max_line_chr > MAX_LINE_CHR){
        err->code = buffer_overflow;
        return;
    }

    //initial chr is 17 because of "|  ||  Please set "
    const size_t line_chr = 17;
    printf("|  || Please set ");

    display_wrapped_text(data->label,"|  || ",line_chr,max_line_chr);

    switch (data->mode)
    {
    case 1:
        printf("(required)!");
        break;
    case 2:
        printf("(opticional, enter \"null\" to ignore this value)!");
        break;
    }
    putchar('\n');
}



//loop throught list and print all option
static void print_option(ENV_CONFIG_field *data){
    option_node *curr_node = data->option_list;
    int count = 1;

    printf("|  ||  `--[");
    //skip last one
    while(curr_node->next!=NULL){
        printf("%i. %s | ",count,curr_node->option);
        curr_node = curr_node->next;
        count++;
    }

    printf("%i. %s",count,curr_node->option);
    printf("]: ");
}


//return string for option in position pos, the first option is 1.
static char* find_option(const int pos, ENV_CONFIG_field *data){
    option_node *curr_node = data->option_list;
    for(int i=0;i<pos-1;i++){ curr_node = curr_node->next;}
    return curr_node->option;
}


/**  
 * print label, ask user input, print options.
 *  @return
 * - error when: malloc error, buffer overflow
*/ 
static void ui_header(ENV_CONFIG_field *data,char **input,error_details *err){
    catch_err(print_label(data,MAX_LINE_CHR,err));
    if(err->code) return;

    printf("|  || [default = %s]:\n",data->original_value);
    if(data->option_list==NULL) printf("|  ||   `--[New value]: ");
    else print_option(data);

    fgets(*input, MAX_INPUT_SIZE, stdin);
    printf("|  ||\n");
}




// print msg error when input empty and required
static void msg_empty_required(){
    printf("|  ||--------------ERROR---------------\n");
    printf("|  || this setting require you to enter something!\n");
    printf("|  || Try again!\n");
    printf("|  ||----------------------------------\n");
    printf("|  ||\n");    
}
//print msg when both default value and input empty
static void msg_no_input_default_val(){
    printf("|  ||--------------ERROR---------------\n");
    printf("|  || You cannot set empty setting!\n");
    printf("|  || Try again!\n");
    printf("|  ||----------------------------------\n");
    printf("|  ||\n");
}
//print msg when out bound option
static void msg_outbound_option(){
    printf("|  ||--------------ERROR---------------\n");
    printf("|  || Invalid option selected!\n");
    printf("|  || Must been integer number and need exist in one of options!\n");
    printf("|  || Try again!\n");
    printf("|  ||----------------------------------\n");
    printf("|  ||\n");
}



/**
 * Ask user confimation of their setting.
 */
static void ui_footer(ENV_CONFIG_field *data,char *filtered,char *answer){
    printf("|  || Are you sure to use this value?\n");
    printf("|  ||   `--[Current]:%s\n", filtered[0]== '\0' ? data->original_value : filtered);
    printf("|  || [Y/N]: ");
    fgets(answer,MAX_INPUT_SIZE,stdin);
    printf("|  ||----------------------------------\n");    
}





void ENV_CONFIG_ui_prompt(ENV_CONFIG_field *data, error_details *err){
    char *input = malloc(MAX_INPUT_SIZE);
    char filtered[MAX_VALUE_SIZE];
    char *target;

    if(!input){
        err->code = ERR_malloc;
        free(input);
        input = NULL;
        return;
    }

    char *start_ptr_input = input;

    char answer[MAX_INPUT_SIZE];

    printf("|  ||----------------------------------\n");
    printf("|  ||  %s\n",data->key);
    printf("|  ||----------------------------------\n");
    //input for data
    do{
        catch_err(ui_header(data,&input,err));

        catch_err(normalize_value(filtered,&input,MAX_VALUE_SIZE,'\n',err));
        if(err->code) return;
            
        // if has nothing, use default value to filter else use input and normalize
        if(filtered[0]=='\0') {
            //if is required and input is nothing
            if(data->mode==1) {
                msg_empty_required();
                continue;
            }
            // if default value is blank + input is blank(if input isn't blank, even default is blank should be fine)
            if((data->value[0]=='\0' || data->value[0]=='\n')){
                msg_no_input_default_val();
                continue;
            }
            target = data->value;
            filtered[0] = '\0';
        } else {
            if(data->option_list!=NULL){
                const int res = convert_str_to_int(filtered);

                if(res<=0 || res >(data->quant_option)) {msg_outbound_option();continue;}
                else{
                    char* option = find_option(res,data);
                    if((int)strlen(option)>=MAX_VALUE_SIZE) {err->code = buffer_overflow;return;}
                    strcpy(filtered,option);
                }
            }
            target = filtered;
        }

        // if is file, filter both default/input
        if(data->is_file) {
            const int valid_f = catch_err(valid_file(target,err,filtered));
            if(valid_f){
                if(err->code) return;
                continue;
            }
        }

        if(err->code) return;
    
        //ask user if value is right
        //if answer is nothing, use default value
        ui_footer(data,filtered,answer);
        
        if((char)toupper((char)answer[0])=='Y') break;
        else continue;
        
    }while(1);

    if(data->option_list!=NULL) ENV_CONFIG_clear_option(data);

    // if user enter anything
    if(filtered[0]!='\0'){
       
        if(strlen(input) >= (size_t)MAX_VALUE_SIZE) {
            free(start_ptr_input);
            start_ptr_input = NULL;

            err->code = buffer_overflow;
            return;
        }

        memset(data->value,0,MAX_VALUE_SIZE);
        strcpy(data->value,filtered);


        free(start_ptr_input);
        start_ptr_input = NULL;    
    }
    printf("|  || The value is setup sucessfully!\n");

    printf("|  ||__________________________________\n");
    printf("|\n");
}