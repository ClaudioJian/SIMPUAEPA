#include "ENV_read.h"

/**
 * validate if variable's first character is invalid for normal non file variable
 * @return
 * 
 * - -1 error (variable_start_digit or variable_start_special_char)
 * 
 * - 0 sucess
 */
static int ENV_CONFIG_validate_first_char(const char first, error_details *err){
    //mean first finded should not be number
    if(!isalpha(first)){
        // enconter special char or digit: error if is not file else mean is file and allow first char be .  
        if(isdigit(first)) err->code = ERR_variable_start_digit;
        else err->code = ERR_variable_start_special_char;
        
        //description of err
        const int expected = snprintf(err->description,sizeof(err->description),"invalid char :[%c]",first);
        const int res = catch_err(ERR_snprintf(expected,sizeof(err->description),err));
        if(res) return -1;

        return -1;
    }
    return 0;
}




/**
 * Read the line and set value and key(excluding heading space) temporary in data.
 * 
 * Set original value, if is file, key name become [FILE]
 *  
 * @param error_code change address of it, check content
 * @return
 * - error when: buffer overflow, empty value when flag skip is set, malloc error, first char of variable is invalid
*/ 
static void ENV_CONFIG_parse_line(char **curr_ptr, ENV_CONFIG_field *data, error_details *err){
    if(err->code) return;

    // change key to "[FILE]" if is file
    if(!(data->is_file)) {
        catch_err(ENV_CONFIG_validate_first_char(**curr_ptr,err));
        if(err->code) return;
        catch_err(normalize_value(data->key,curr_ptr,MAX_KEY_SIZE,'=',err));
        if(err->code) return;
    }else{
        strcpy(data->key,"[FILE]");
    }


    catch_err(normalize_value(data->value,curr_ptr,MAX_VALUE_SIZE,'\0',err));

    if(err->code) return;
    if(data->mode==-1 && data->value[0]=='\0'){
        err->code = ERR_ENV_empty_value;
        return;
    }
    memcpy(data->original_value,data->value,MAX_VALUE_SIZE);
}




//check if mode supported
static int is_option(const char op){
    if(op == 'l' || op =='r' ||  op=='s' || op=='f' || op == 'o' || op == 'e') return 1;
    else return 0;
}








/**
 * Store options in data, each option is separated by separator passed.
 * 
 * The values are trimmed and stored in option list, if the option is empty, it will be ignored and not stored.
 * @param curr_chr pointer of chr in line, it will be changed to next char after delimiter or end of line after this function.
 * @return
 * 
 * - Error when: malloc error, buffer overflow.
 */ 
static void store_option(char **curr_chr,ENV_CONFIG_field *data,const char delimiter,error_details *err){
    option_node* new_node = (option_node*)malloc(sizeof(option_node));
    if(!new_node) {
        free(new_node);
        new_node = NULL;

        err->code = ERR_malloc;
        return;
    }


    catch_err(normalize_value(new_node->option,curr_chr,MAX_OPTION_SIZE,delimiter,err));
    

    if(err->code || new_node->option[0]=='\0') {
        free(new_node);
        new_node = NULL;
        return;
    }

    new_node->next = NULL;


    // if last setting is not set, attract to new_node created
    // Update STRUCT storage
    if (data->option_list == NULL) {
        data->option_list = new_node;
    } else {
        data->last_option->next = new_node;
    }
    data->last_option = new_node;
}




/**
 * read line and create new node for options every time string hit '|'.
 * 
 * The max quantity of option cannot exceed MAX_OPTIONS(defined in ENV_const.h). Return error if exceed.
*/
static void option_logic(char **curr_chr,ENV_CONFIG_field *data,error_details *err){
    int n_option=0;   

    
    while(**curr_chr!='\n' && **curr_chr!='\0'){  
        catch_err(store_option(curr_chr,data,OPTIONS_DELIMITERS_CHR,err));
        n_option++;
        if(n_option>MAX_OPTIONS){
            err->code = ERR_too_many_options;
            return;
        }
    }
    data->quant_option = n_option;
}





/**
 * this function find what mode(label, required/skip) for this line. if l set label, r set required(1) else set to -1(skip)
 * 
 * @return
 * - skipped if is not flags avaible or invalid format(must be "a" and followed ":")
 * 
 * - error when: buffer overflow, malloc error, too many options
*/
static void ENV_CONFIG_mode_logic(char **curr_chr, ENV_CONFIG_field *data,error_details *err){
    const char type = **curr_chr; //store l or r or s

    (*curr_chr)++; //skip l or r or s
    if(**curr_chr==':') (*curr_chr)++; //skip :
    else return; //is comment if no :
    
    switch(type){
        case 'r': data->mode = 1; break;
        case 's': data->mode = -1; break;
        case 'e': data->mode = 2; break;        
        case 'f': data->is_file = 1; break;
        case 'l': catch_err(normalize_value(data->label,curr_chr,MAX_TEXT_SIZE,'\0',err)); break;
        case 'o': catch_err(option_logic(curr_chr,data,err)); break;
    }
    if(err->code) return;
    
    return;
}


int ENV_CONFIG_scan_next_data(ENV_CONFIG_field *data, error_details *err){
    if(data->is_EOF) return 0;
    char line[MAX_BUFFER_SIZE];
    
    long old_fp = ftell(data->fp); //start of line of last position
    

    //read line, if fail it will return NULL
    while(fgets(line,sizeof(line),data->fp) != NULL){
        //start at first char by set pointer to line
        char *curr_chr = line;
        
        //skip leading space
        while(*curr_chr==' '||is_ignorable_chr(*curr_chr)) curr_chr++;
        
        
        if(*curr_chr == '\n' || *curr_chr == '\0'|| *curr_chr == '\r') continue; //this line is over

        
        //if find it is not # return immedially and grab data
        if(*curr_chr!='#'){
            catch_err(ENV_CONFIG_parse_line(&curr_chr, data, err));
            if(err->code) {
                //description of err
                const int expected = snprintf(err->description,sizeof(err->description),"invalid char found in line:[%s]",line);
                catch_err(ERR_snprintf(expected,sizeof(err->description),err));

                return -1;
            }
            return 1;
        }else{
            curr_chr++; // skip #
            while(*curr_chr==' '|| is_ignorable_chr(*curr_chr)) curr_chr++; //skip space after #
            //find label l:, required r: or s: skip and continue until find real key=value
            if(is_option(*curr_chr)){
                //make it skip to label/r/s when start from top
                // only assign it when is not initialized(-1)
                if(data->start_offset < 0) data->start_offset = old_fp;
                
                catch_err(ENV_CONFIG_mode_logic(&curr_chr, data, err)); 
                if(err->code) return -1;
                continue;
            }
        }
        curr_chr++;

        //if non comment line(shortcut) is not set yet, track it to start of line, the next loop fp will go to end of line(e.g start of new line)
        if(data->start_offset < 0) old_fp = ftell(data->fp);
        //check EOF or is bufdfer overflow
        if(feof(data->fp)) {
            data->is_EOF = 1;
            return 0;
        }
        if(strchr(line,'\n') == NULL) {
            err->code = buffer_overflow;
            return -1;
        }
    }
    return 0;
}


int ENV_CONFIG_match(const char *setting, ENV_CONFIG_field *data, error_details *err){
    int find = 1;
    if(!setting || strlen(setting) > MAX_KEY_SIZE) {
        err->code = buffer_overflow;
        return -1;
    }


    //avoid loss of track
    const long curr_ptr = ftell(data->fp);

    ENV_CONFIG_rewind(data);


    //always return
    // loop every time ENV_CONFIG_scan_next_data. if sucess, compare to setting passed and set find if finded.
    do{
        ENV_CONFIG_clear(data);
        find = catch_err(ENV_CONFIG_scan_next_data(data,err));

        //eof and find stay 0
        if(!find) break;
        if(err->code) return -1;
    }while(strcmp(data->key,setting) != 0);
    

    //restore to current ptr
    // jmp to shortcut if previous has find
    if(data->start_offset && curr_ptr < data->start_offset) fseek(data->fp,data->start_offset,SEEK_SET);
    if(fseek(data->fp,curr_ptr,SEEK_SET)) {
        err->code = ERR_fseek;
        return -1;
    }    
    
    if(!find) {
        data->is_EOF = 1;
        return 0;
    }

    return 1;
}