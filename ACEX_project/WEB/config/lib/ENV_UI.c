#include "ENV_UI.h"


//print msg when invalid_file
static void msg_invalid_file(const int valid){
    if(SIMPLIFIED_DISPLAY) {
        size_t line_chr = display_wrapped_text("| Error: Invalid file or path!", "| ", 0, MAX_LINE_CHR);
        if(valid==0) display_wrapped_text("file!", "| ", line_chr, MAX_LINE_CHR);
        else if(valid == -1) display_wrapped_text("path!", "| ", line_chr, MAX_LINE_CHR);
        putchar('\n');
        return;
    }

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



void display_r_only_val(ENV_CONFIG_field *data, error_details *err){
    char msg[MAX_TEXT_SIZE];
    const int res = catch_err(ERR_snprintf(
            SIMPLIFIED_DISPLAY 
            ? snprintf(msg,sizeof(msg),"| %s(Read only) = %s",data->key,data->value)
            : snprintf(msg,sizeof(msg),"| Default value: [%s] = [%s]",data->key,data->value)
            ,
            sizeof(msg),err)
        );
    if(res) return;

    if(NEWLINE_BETWEEN_VARIABLES) printf("|\n");
    display_wrapped_text(msg,"| ",0,MAX_LINE_CHR); putchar('\n');
    NEWLINE_BETWEEN_VARIABLES = 0;
}



int ENV_CONFIG_step_config(ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,const int mode, error_details *err){
    if(data->is_EOF) return 1;
    if(mode != w_mode && mode != r_mode && mode != internal_mode){
        err->code = ERR_invalid_mode;
        return -1;
    }
    
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

    //if prev data isn't null, change value to alredy registered data
    // previous data will never have file registered, so skip if is file
    //if flag set to skip, don't let user change it
    const int has_prev_data = catch_err(ENV_CONFIG_cpy_prev_data(data,prev_data,err));
    if(err->code) return -1;

    else if(!has_prev_data && mode != r_mode) {
        // variable newline between variabçe switch to 0 after display_r_only_val and change to 1 when ENV_CONFIG_ui_prompt, this is because between variable with
        // mode == -1, don't need newline, but when previous isn't, newline is needed
        if(data->mode >= 0) {catch_err(ENV_CONFIG_ui_prompt(data,err));}
        else if(SHOW_READ_ONLY && mode != internal_mode) display_r_only_val(data,err);
    }

    if(err->code) return -1;
    return 0;
}



int ENV_CONFIG_adjust_key(const char *setting, ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,const int mode, error_details *err){
    if(mode != w_mode && mode != r_mode && mode != internal_mode){
        err->code = ERR_invalid_mode;
        return -1;
    }

    if(ENV_CONFIG_is_alredy_set(setting,data)) return 1;

    const int find = catch_err(ENV_CONFIG_match(setting,data,err));
    
    if(err->code) return -1;

    if(!find) {
        printf("| cannot find setting: [%s]\n", setting);
        return 0;
    }

    const int has_prev_data = catch_err(ENV_CONFIG_cpy_prev_data(data,prev_data,err));
    if(err->code) return -1;
    if(data->mode == -1 && SHOW_READ_ONLY) {display_r_only_val(data,err);}
    if(!has_prev_data) {
        // variable newline between variabçe switch to 0 after display_r_only_val and change to 1 when ENV_CONFIG_ui_prompt, this is because between variable with
        // mode == -1, don't need newline, but when previous isn't, newline is needed
        if(data->mode >= 0) {catch_err(ENV_CONFIG_ui_prompt(data,err));}
        else if(SHOW_READ_ONLY && mode != internal_mode) display_r_only_val(data,err);
    }

    if(err->code) return -1;
    return 1;
}



/**
 * display label explain what value do. switch to new line automatically.
 * 
 * If SIMPLIFIED_DISPLAY = true, then explanation for data won`t be displayed
 * 
 * If the word are too long, add "-" at end of word and print rest of it in new line.
 * otherwise, if it just hit max_line_chr, switch to new line and print all of it in new line.
 * 
 * required label will be displayed at end. If e flag(mode=2) is set, display that value can be ignored in run time.
 * @param max_line_chr max number of char can one line hold before switch to new line.(cannot be smaller than 17)
 * @return
 * 
 * -error when: buffer overflow
 */
static void print_label(ENV_CONFIG_field *data,const char *newline_break_str,size_t max_line_chr,error_details *err){
    if(max_line_chr<17) max_line_chr = 17;
    if(max_line_chr > MAX_LINE_CHR){
        err->code = buffer_overflow;
        return;
    }
    size_t line_chr = 0;

    //ignoring
    if(!SIMPLIFIED_DISPLAY){
        //initial chr is 17 because of "|  ||  Please set "
        printf("|  || Please set ");

        line_chr = display_wrapped_text(data->label,"|  || ",17,max_line_chr);
    }else{
        //become "| key(required)"
        printf("| %s",data->key);
    }

    switch (data->mode)
    {
    case 1:
        display_wrapped_text("(required)",newline_break_str,line_chr,max_line_chr);
        break;
    case 2:
        const char *optional_msg = SIMPLIFIED_DISPLAY ? "(optional)" : "(optional, type \"null\" to skip)";
        display_wrapped_text(optional_msg ,newline_break_str,line_chr,max_line_chr);
        break;
    }
}



//loop throught list and print all option
static void print_option(ENV_CONFIG_field *data,error_details *err){
    option_node *curr_node = data->option_list;
    int count = 1;

    size_t line_chr = 0;
    char op[MAX_OPTION_SIZE];
    int res;

    if(SIMPLIFIED_DISPLAY) line_chr = display_wrapped_text("| [","| ",line_chr,MAX_LINE_CHR);
    else line_chr = display_wrapped_text("|  ||  `--[","|  || ",line_chr,MAX_LINE_CHR);

    //skip last one
    while(curr_node->next!=NULL){
        res = catch_err(ERR_snprintf(
            snprintf(op,sizeof(op),"%i. %s | ",count,curr_node->option),
            sizeof(op),err)
        );
        if(res) return;

        //skip to same space as [...]
        if(SIMPLIFIED_DISPLAY) line_chr = display_wrapped_text(op,"|  ",line_chr,MAX_LINE_CHR);
        else line_chr = display_wrapped_text(op,"|  ||     ",line_chr,MAX_LINE_CHR);

        curr_node = curr_node->next;
        count++;
    }

    res = catch_err(ERR_snprintf(
        snprintf(op,sizeof(op),"%i. %s]:",count,curr_node->option),
        sizeof(op),err)
    );
    if(res) return;

    if(SIMPLIFIED_DISPLAY) {
        display_wrapped_text(op,"|  ",line_chr,MAX_LINE_CHR);
        putchar('\n');
    }
    else display_wrapped_text(op,"|  ||      ",line_chr,MAX_LINE_CHR);
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
static void ui_header(ENV_CONFIG_field *data, const char *newline_break_str,char **input,error_details *err){
    char msg[MAX_TEXT_SIZE];
    const int res = catch_err(ERR_snprintf(
        snprintf(msg,sizeof(msg),"%s[default = %s]:",
            SIMPLIFIED_DISPLAY ? "" : "|  || ",
            data->original_value
        ),
        sizeof(msg),err)
    );
    if(res) return;

      
    //         |  ||  `--[new value] or [1.options|...]:    
    if(SIMPLIFIED_DISPLAY && data->option_list!=NULL) print_option(data,err); // became: | [1.x|2.x]\n

    //         | key(mode) or |  || please set ...(mode)\n
    catch_err(print_label(data,newline_break_str,MAX_LINE_CHR,err));
    if(err->code) return;
    if(!SIMPLIFIED_DISPLAY) putchar('\n');

    //          [default=val]: or |  || [default=val]:\n
    display_wrapped_text(msg,newline_break_str,0,MAX_LINE_CHR); 

    //          |  ||   `--[New value]: 
    if(!SIMPLIFIED_DISPLAY){
        putchar('\n');
        if(data->option_list==NULL) display_wrapped_text("|  ||   `--[New value]: ","|  || ",0,MAX_LINE_CHR);
        else print_option(data,err);
    }

    fgets(*input, MAX_INPUT_SIZE, stdin);
}




// print msg error when input empty and required
static void msg_empty_required(const char *newline_break_str){
    if(SIMPLIFIED_DISPLAY) {
        display_wrapped_text("| Error: Input required",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
    }else{
        printf("|  ||\n");
        printf("|  ||--------------ERROR---------------\n");
        display_wrapped_text("|  || this setting require you to enter something!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  || Try again!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        printf("|  ||----------------------------------\n");
    }
    printf("%s\n",newline_break_str);    
}

//print msg when both default value and input empty
static void msg_no_input_default_val(const char *newline_break_str){
    if(SIMPLIFIED_DISPLAY) {
        display_wrapped_text("| Error: No default or user input provided.",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
    }else{
        printf("|  ||\n");
        printf("|  ||--------------ERROR---------------\n");
        display_wrapped_text("|  || No value or default value found!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  || When the \"s\" flag is active, a value or a default option is required",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  || Try again!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        printf("|  ||----------------------------------\n");
    }
    printf("%s\n",newline_break_str);
}
//print msg when out bound option
static void msg_outbound_option(const char *newline_break_str){
    if(SIMPLIFIED_DISPLAY) {
        display_wrapped_text("| Error: Invalid option selected!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
    }else{
        printf("|  ||\n");
        printf("|  ||--------------ERROR---------------\n");
        display_wrapped_text("|  || Invalid option selected!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  || Input must be an integer matching one of the available options.",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        display_wrapped_text("|  || Try again!",newline_break_str,0,MAX_LINE_CHR); putchar('\n');
        printf("|  ||----------------------------------\n");
    }
    printf("%s\n",newline_break_str);
}



/**
 * Ask user confimation of their setting.
 */
static void ui_footer(ENV_CONFIG_field *data,char *filtered,char *answer){
    printf("|  ||\n");
    printf("|  || Are you sure to use this value?\n");
    printf("|  ||   `--[Current]:%s\n", filtered[0]== '\0' ? data->original_value : filtered);
    printf("|  || [Y/N]: ");
    fgets(answer,MAX_INPUT_SIZE,stdin);
    printf("|  ||----------------------------------\n");    
}





void ENV_CONFIG_ui_prompt(ENV_CONFIG_field *data, error_details *err){
    char *input = malloc(MAX_INPUT_SIZE);
    char filtered[MAX_VALUE_SIZE];
    char *newline_break_str = SIMPLIFIED_DISPLAY ? "| " : "|  || ";
    char *target;

    if(!input){
        err->code = ERR_malloc;
        free(input);
        input = NULL;
        return;
    }

    char *start_ptr_input = input;

    char answer[MAX_INPUT_SIZE];

    if(!SIMPLIFIED_DISPLAY || NEWLINE_BETWEEN_VARIABLES || data->option_list != NULL) printf("|\n");
    if(!SIMPLIFIED_DISPLAY){
        printf("|  ||----------------------------------\n");
        printf("|  ||  %s\n",data->key);
        printf("|  ||----------------------------------\n");
    }
    //input for data
    do{
        catch_err(ui_header(data,newline_break_str,&input,err));

        catch_err(normalize_value(filtered,&input,MAX_VALUE_SIZE,'\n',err));
        if(err->code) return;
            
        // if has nothing, use default value to filter else use input and normalize
        if(filtered[0]=='\0') {
            //if is required and input is nothing
            if(data->mode==1) {
                msg_empty_required(newline_break_str);
                continue;
            }
            // if default value is blank + input is blank(if input isn't blank, even default is blank should be fine)
            if((data->value[0]=='\0' || data->value[0]=='\n')){
                msg_no_input_default_val(newline_break_str);
                continue;
            }
            target = data->value;
            filtered[0] = '\0';
        } else {
            if(data->option_list!=NULL){
                const int res = convert_str_to_int(filtered);

                if(res<=0 || res >(data->quant_option)) {msg_outbound_option(newline_break_str);continue;}
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
        if(!SIMPLIFIED_DISPLAY){
            ui_footer(data,filtered,answer);
            
            if((char)toupper((char)answer[0])!='Y') continue;
        }
        break;
    }while(1);


    if(SIMPLIFIED_DISPLAY) {
        NEWLINE_BETWEEN_VARIABLES = data->option_list == NULL ? 0 : 1;
    }else{
        printf("|  || The value is setup sucessfully!\n");
        printf("|  ||__________________________________\n");
    }


    //cleaning
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
}