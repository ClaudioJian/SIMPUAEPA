#include "ENV_storage.h"

/**
 * find if in passed data storage has set.
 * 
 * pass through list passed, if hit null of config_node->next before it find key with same name , mean not finded 
 * @return
 * - 1 = find
 * 
 * - 0 = not find
 */
static int ENV_scan_storage(const char *setting,config_node *node){
    while(node!=NULL){
        if(strcmp(node->key,setting) == 0) return 1; //find
        node = node->next; //move forward
    }
    return 0;
}





/**
 * find if in passed file storage has set.
 * 
 * pass through list passed, if hit null of file_node->next before it find key with same name , mean not finded 
 * @return
 * - 1 = find
 * 
 * - 0 = not find
 */
static int ENV_scan_file_list(const char *file, file_node *node){
    while(node!=NULL){
        if(strcmp(node->original_val,file) == 0) return 1; //find
        node = node->next; //move forward
    }
    return 0;
}







int delete_alredy_set(ENV_CONFIG_field *data, ENV_CONFIG_field *prev_data){
    if(prev_data == NULL) return 0;
    if(data->depencity_list==NULL) return 0;

    //check storage
    config_node *curr_node = prev_data->depencity_list;
    config_node *prev_node = curr_node;
    
    
    while(curr_node!=NULL){
        if(ENV_CONFIG_is_alredy_set(curr_node->key,data)) {
            ENV_CONFIG_delete_node(prev_data,curr_node,prev_node);
            continue;
        }

        prev_node = curr_node;
        curr_node = curr_node->next; //move forward
    }

    return 1;
}




/// ask to user to choise previous value or new value in .env.example when has conflict
/// @return
/// 
/// - 1 if use previous data
///
/// - 2 if use new value in .env.example
///
/// - -1 if error
static int resolve_config_value_conflict(config_node *curr_node,ENV_CONFIG_field *data,error_details *err){
    int res;
    char prev_data_msg[MAX_TEXT_SIZE];

    res = catch_err(ERR_snprintf(
        snprintf(prev_data_msg,sizeof(prev_data_msg),"|  `--< 1. OLD value[%s]:[%s]>",curr_node->key,curr_node->value),
        sizeof(prev_data_msg),err)
    );

    if(res) return -1;

    char curr_data_msg[MAX_TEXT_SIZE];

    res = catch_err(ERR_snprintf(
        snprintf(curr_data_msg,sizeof(curr_data_msg),"|  `--< 2. in (%s) - NEW value[%s]:[%s] >",data->key,data->value,ENV_EXAMPLE_FILE_NAME),
        sizeof(curr_data_msg),err)
    );

    if(res) return -1;

    char answer[2];

    display_wrapped_text("| Configuration conflict detected:","| ",0,MAX_LINE_CHR); putchar('\n');
    display_wrapped_text("| Please choose which value to keep!","| ",0,MAX_LINE_CHR); putchar('\n');
    display_wrapped_text(prev_data_msg,"| ",0,MAX_LINE_CHR); putchar('\n');
    display_wrapped_text(curr_data_msg,"| ",0,MAX_LINE_CHR); putchar('\n');
    do{
        printf("| "); scanf("%1s",answer);
    }
    while(answer[0]!= '1' && answer[0] != '2');
    return answer[0] - '0';
}






int ENV_CONFIG_cpy_prev_data(ENV_CONFIG_field *data, ENV_CONFIG_field *prev_data,error_details *err){
    if(prev_data == NULL) return 0;
    if(data->depencity_list==NULL) return 0;

    static int deleted = 0;
    if(!deleted) deleted = delete_alredy_set(data,prev_data);



    //check storage
    config_node *curr_node = prev_data->depencity_list;
    config_node *prev_node = curr_node;
    int selected = 1;

    while(curr_node!=NULL){
        if(strcmp(curr_node->key,data->key) == 0) {
            //copy prev data to new data
            //if the value cannot be set by user and have conflict between previous data and curr data
            if(data->mode<0 && strcmp(curr_node->value,data->value)!=0){
                selected = catch_err(resolve_config_value_conflict(curr_node,data,err));
                if(err->code) return -1;
            }
            if(selected == 1) memcpy(data->value,curr_node->value,sizeof(data->value));

            ENV_CONFIG_delete_node(prev_data,curr_node,prev_node);
            
            return 1;
        }
        prev_node = curr_node;
        curr_node = curr_node->next; //move forward
    }

    return 0;
}




int ENV_CONFIG_is_alredy_set(const char *setting, ENV_CONFIG_field *data){
    if(!setting) return 0;

    if(data->is_file){
        if(data->file_list == NULL) return 0;
        //check storage
        if(ENV_scan_file_list(setting,data->file_list)) return 1;
    }else{
        if(data->depencity_list==NULL) return 0;
        //check storage
        if(ENV_scan_storage(setting,data->depencity_list)) return 1;
    }
    //no find
    return 0;
}


/**
 *  verify if extension supported, if true, store that information in file_list
 * @return
 * - 0 = sucess
 * 
 * - -1 = error when: malloc/buffer overflow/extension_invalid
*/
static int track_file(ENV_CONFIG_field *data, error_details *err){
    const int extension = catch_err(valid_extension(data->value,err));
    if(err->code) return -1;

    //0 = invalid/not supported file type
    if(!extension) {
        err->code = ERR_invalid_extension;
        return -1;
    }

    file_node *new_node = (file_node*)malloc(sizeof(file_node));
    if(!new_node) {
        free(new_node);
        new_node = NULL;

        err->code = ERR_malloc;
        return -1;
    }

    strcpy(new_node->file,data->value);
    memcpy(new_node->original_val,data->original_value,MAX_VALUE_SIZE);
    new_node->extension = extension;
    new_node->next = NULL;
    
    if(data->file_list == NULL){
        data->file_list = new_node;
    }else{
        data->last_file_list->next = new_node;
    }
    data->last_file_list = new_node;
    return 0;
}



/**
 * store setting in data->depencity_list
 * @return
 * - 0 = sucess
 * 
 * - -1 = error when: malloc error
 */
static int track_setting(ENV_CONFIG_field *data, error_details *err){
    config_node* new_node = (config_node*)malloc(sizeof(config_node));
    if(!new_node) {
        err->code = ERR_malloc;
        return -1;
    }

    //set new node to attract
    strcpy(new_node->key,data->key);
    strcpy(new_node->value,data->value);
    new_node->next = NULL;


    // if last setting is not set, attract to new_node created
    // Update STRUCT storage
    if (data->depencity_list == NULL) {
        data->depencity_list = new_node;
    } else {
        data->last_depencity_node->next = new_node;
    }
    data->last_depencity_node = new_node;    
    return 0;
}






int ENV_CONFIG_track_depencity(ENV_CONFIG_field *data, error_details *err){
    // if mode is 2 and value is "null", don't track it, just return
    if(data->mode == 2){
        char* upper_input = catch_err(convert_str_to_upper(data->value,err));

        if(err->code || upper_input == NULL) {
            free(upper_input);
            upper_input = NULL;
            err->code = ERR_malloc;
            return -1;
        }
        
        if(strcmp(upper_input,"NULL")==0){
            free(upper_input);
            upper_input = NULL;
            return 0;
        } 
    }


    if(data->is_file) {catch_err(track_file(data,err));}
    else {catch_err(track_setting(data,err));}

    if(err->code) return -1;
    return 0;
}