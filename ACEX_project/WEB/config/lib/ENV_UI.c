#include "ENV_UI.h"








/// @brief check file and path
/// @return positive int=invalid  0=sucess -1 error: 
///
/// - malloc error, buffer overflow, invalid extension
static int valid_file(const char *target,int *error_code,char *filtered){
    int valid = 1;

    //normalize default value or inputed value(store in buffer to free after)
    char* buffer = normalize_path(target,error_code);

    if(*error_code) return -1;
    strcpy(filtered,buffer);

    free(buffer);
    buffer = NULL;

    // if path invalid, set to -1
    if(filtered==NULL) valid = -1;

    //if still valid(positive unchanged), check extension
    if(valid>0){
        valid = valid_extension(filtered,error_code);
        if(*error_code) return -1;
    }

    //check if is valid
    if(valid<=0){
        printf("|  ||--------------ERROR---------------\n");
        if(valid==0) printf("|  || invalid extension!\n");
        else if(valid == -1) printf("|  || invalid path!\n");
            
        printf("|  || Try again!\n");
        printf("|  ||----------------------------------\n");
        printf("|  ||\n");
        return 1;
    }      
    return 0;
}





int ENV_CONFIG_step_config(ENV_CONFIG_field *data, int *error_code){
    if(data->is_EOF) return 1;
    int not_EOF = 1;
    // if isn't first time acess data in .env.example, skip to first non comment line
    if(ftell(data->fp) < data->start_offset) fseek(data->fp,data->start_offset,SEEK_SET);

    //loop until find and skip alredy setted value. EOF = 0 = stop
    while(1){
        not_EOF = ENV_CONFIG_scan_next_data(data,error_code);

        if(!not_EOF) {
            data->is_EOF = 1;
            ENV_CONFIG_clear(data);
            return 1;
        }

        if(*error_code) return -1;
        // 1 = set 0 = not set = break
        if(!ENV_CONFIG_is_alredy_set(data->is_file ? data->original_value:data->key ,data)) break;
        ENV_CONFIG_clear(data);
    }

    //if flag set to skip, don't let user change it
    if(data->mode >= 0) ENV_CONFIG_ui_prompt(data,error_code);
    if(*error_code) return -1;
    
    return 0;
}



int ENV_CONFIG_adjust_key(const char *setting, ENV_CONFIG_field *data, int *error_code){

    if(ENV_CONFIG_is_alredy_set(setting,data))return 1;
    const int find = ENV_CONFIG_match(setting,data,error_code);
    

    if(*error_code) return -1;
    

    if(!find) {
        printf("| cannot find setting: %s\n", setting);
        return 0;
    }

    //skip when is labbered as skip
    if(data->mode >= 0) ENV_CONFIG_ui_prompt(data,error_code);
    ENV_CONFIG_clear_option(data);
    if(*error_code) return -1;

    return 1;
}


void display_wrapped_text(const char *label,const char *new_line_prefix,size_t line_chr,const size_t max_line_chr){
    char word[MAX_TEXT_SIZE];
    int word_size = 0;
    const size_t new_lprefix_len = strlen(new_line_prefix);

    for(size_t label_i=0;; label_i++){
        char curr_chr = label[label_i];

        if(curr_chr == ' '||curr_chr == '\n'||curr_chr == '\0'){
            word[word_size] = '\0';
            if(word[0]=='\0') continue;
            
            //print new line and set to 6, else print directly
            //if word size is smaller than remain line char, this loop will be ignored completly.
            if((size_t)word_size + line_chr >= max_line_chr){
                printf("\n%s ", new_line_prefix);
                line_chr = new_lprefix_len + 1;
            }
                
            //loop to prevent word is too long to fit a line.
            size_t word_i=0;
            while(word[word_i]!='\0'){
                // only print newline when hit the word it self too big,else will just skip to next newline, 7 is "|  || " and "-"
                if(word_i >= (max_line_chr-1)){
                    printf("-\n%s ", new_line_prefix);
                    line_chr = new_lprefix_len + 1;
                }
                putchar(word[word_i++]);
                line_chr++;
            }
            //if not end, put space
            if(curr_chr !='\0') {
                line_chr++;//space
                putchar(' ');
            }
            //reset
            word_i=0;
            word_size = 0;
        }else{
            word[word_size++] = curr_chr;
        }
        //if hit end of label but still have word, continue, after that, will be break because the word size is reset
        if(curr_chr == '\0' && word_size<=0) break;
    }
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
static void print_label(ENV_CONFIG_field *data,size_t max_line_chr,int *error_code){
    if(max_line_chr<17) max_line_chr = 17;
    if(max_line_chr > MAX_LINE_CHR){
        *error_code = buffer_overflow;
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
    int count=1;
    option_node *curr_node = data->option_list;

    printf("|  ||  `--[");
    //skip last one
    while(curr_node->next!=NULL){
        printf("%i. %s | ",count,curr_node->option);
        count++;
        curr_node = curr_node->next;
    }

    printf("%i. %s",count,curr_node->option);

    printf("]: ");
}


//return string for option in position pos, the first option is 1.
static char* find_option(const int pos, ENV_CONFIG_field *data){
    option_node *curr_node = data->option_list;
    for(int i=0;i<pos-1;i++){
        curr_node = curr_node->next;
    }
    return curr_node->option;
}


/**  
 * print label, ask user input, print options.
 *  @return
 * - error when: malloc error, buffer overflow
*/ 
static void ui_header(ENV_CONFIG_field *data,char **input,int *error_code){
    print_label(data,MAX_LINE_CHR,error_code);
    if(*error_code) return;

    printf("|  || [default = %s]:\n",data->original_value);

    if(data->option_list!=NULL) print_option(data);
    else printf("|  ||   `--[New value]: "); 


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





void ENV_CONFIG_ui_prompt(ENV_CONFIG_field *data, int *error_code){
    char *input = malloc(MAX_INPUT_SIZE);
    char filtered[MAX_VALUE_SIZE];
    char *target;

    if(!input){
        free(input);
        input = NULL;

        *error_code = ERR_malloc;
        return;
    }
    char *start_ptr_input = input;

    char answer[MAX_INPUT_SIZE];

    printf("|  ||----------------------------------\n");
    printf("|  ||  %s\n",data->key);
    printf("|  ||----------------------------------\n");
    //input for data
    do{
        ui_header(data,&input,error_code);

        normalize_value(filtered,&input,MAX_VALUE_SIZE,'\n',error_code);
        if(*error_code) return;
            
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
                    if((int)strlen(option)>=MAX_VALUE_SIZE) {*error_code = buffer_overflow;return;}
                    strcpy(filtered,option);
                }
            }
            target = filtered;
        }

        // if is file, filter both default/input
        if(data->is_file) {
            if(valid_file(target,error_code,filtered)){
                if(*error_code) return;
                continue;
            }
        }

        if(*error_code) return;
    
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

            *error_code = buffer_overflow;
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