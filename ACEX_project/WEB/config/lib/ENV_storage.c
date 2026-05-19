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