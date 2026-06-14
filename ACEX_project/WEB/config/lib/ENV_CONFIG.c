#include "ENV_CONFIG.h"

//initialize by setting value to 0 or null
static void ENV_CONFIG_init_values(ENV_CONFIG_field *new_node){
    new_node->mode = 0;
    new_node->affected_data = 0;
    new_node->total_data = 0;
    new_node->is_file = 0;
    new_node->file_quant = 0;
    new_node->is_EOF = 0;
    new_node->quant_option = 0;
    new_node->start_offset = -1;
    new_node->depencity_list = NULL;
    new_node->last_depencity_node = NULL;
    new_node->file_list = NULL;
    new_node->last_file_list = NULL;
    new_node->option_list = NULL;
    new_node->last_option = NULL;
}


ENV_CONFIG_field *ENV_init_config_struct(const char* file_name,error_details *err){
    ENV_CONFIG_field* new_node = (ENV_CONFIG_field*) malloc(sizeof(ENV_CONFIG_field));
    if(!new_node) {
        free(new_node);
        new_node = NULL;

        err->code = ERR_malloc;
        return NULL;
    };

    FILE *fptr = fopen(file_name,"rb");

    if(fptr==NULL){
        free(new_node);
        new_node = NULL;

        char err_msg[MAX_TEXT_SIZE];


        catch_err(ERR_snprintf(snprintf(err_msg,MAX_TEXT_SIZE,"Cannot open file %s\n",file_name),MAX_TEXT_SIZE,err));
        if(err->code) return NULL;

        err->code = ERR_fopen;
        strcpy(err->description,err_msg);
        return NULL;
    }

    //values
    ENV_CONFIG_init_values(new_node);

    //set pointer
    new_node->fp = fptr;
    return new_node;
}


void ENV_CONFIG_delete_node(ENV_CONFIG_field *data,config_node *curr_node,config_node *prev_node){
    data->total_data--;
    config_node *next_node = NULL;
    if(curr_node == data->depencity_list && data->depencity_list->next !=NULL) {
        //replace next value to initial pointer without releasing start pointer. release only the next value(used data) and don't move since it has alredy replaced with next node.
        memcpy(curr_node->value,curr_node->next->value,sizeof(curr_node->value));
        memcpy(curr_node->key,curr_node->next->key,sizeof(curr_node->key));

        next_node = curr_node->next->next;
        free(curr_node->next);

        curr_node->next = next_node;

        return;
    }
    prev_node->next = curr_node->next;

    if(curr_node == data->depencity_list) data->depencity_list = NULL;
    //delete current node
    free(curr_node);
    curr_node = NULL;

    return;
}



void ENV_CONFIG_clear(ENV_CONFIG_field *data){
    data->mode = 0;
    data->is_file = 0;

    // make all value become none
    memset(data->label,0,MAX_TEXT_SIZE);
    memset(data->value,0,MAX_VALUE_SIZE);
    memset(data->original_value,0,MAX_VALUE_SIZE);
    memset(data->key,0,MAX_KEY_SIZE);
    ENV_CONFIG_clear_option(data);
}






void ENV_CONFIG_rewind(ENV_CONFIG_field *data){
    data->is_EOF = 0;
    //set current pointer to target
    const long lptr = data->start_offset;

    //reset to init of file if sdhortcut not finded yet else just use it
    if(lptr < 0) rewind(data->fp);
    else fseek(data->fp,data->start_offset,SEEK_SET);

    ENV_CONFIG_clear(data);
}



void ENV_CONFIG_clear_option(ENV_CONFIG_field *data){
    option_node *curr_node = data->option_list;

    while(curr_node!=NULL){
        option_node *prev_node = curr_node;
        curr_node = curr_node->next;

        free(prev_node);
        prev_node = NULL;
    }

    data->option_list = NULL;
    data->last_option = NULL;
    data->quant_option = 0;
}

static void ENV_CONFIG_clear_depencity_list(ENV_CONFIG_field *data){
    config_node *curr_node = data->depencity_list;

    //clear depencity list
    while(curr_node != NULL){
        config_node *prev_node = curr_node;
        curr_node = curr_node->next;

        free(prev_node);
        prev_node = NULL;
    }
    data->depencity_list = NULL;
    data->last_depencity_node = NULL;
}


static void ENV_CONFIG_clear_file_node(ENV_CONFIG_field *data){
    file_node *curr_node = data->file_list;
    //clear file list
    while(curr_node != NULL){
        file_node *prev_node = curr_node;
        curr_node = curr_node->next;

        free(prev_node);
        prev_node = NULL;
    }

    data->file_list = NULL;
    data->last_file_list = NULL;
}



void ENV_CONFIG_destroy(ENV_CONFIG_field **data){
    if(*data==NULL) return;
    //clean all data
    //lopp through list and clean them all until hit null
    ENV_CONFIG_clear_depencity_list(*data);
    ENV_CONFIG_clear_file_node(*data);
    ENV_CONFIG_clear_option(*data);

    if((*data)->fp){
        fclose((*data)->fp);
        (*data)->fp = NULL;
    }
    
    free(*data);
    *data = NULL;
}


void print_data(ENV_CONFIG_field *data){
    printf("=======debug=======\n");
    printf("data->label:%s\n",data->label);
    printf("data->value:%s\n",data->value);
    printf("data->or_value:%s\n",data->original_value);
    printf("data->key:%s\n",data->key);
    printf("data->is_file:%i\n",data->is_file);
    printf("data->mode:%i\n",data->mode);
    printf("data->is_EOF:%i\n",data->is_EOF);
    printf("data->quant_option:%i\n",data->quant_option);

    printf("data->start_offset:%ld\n",data->start_offset);
    printf("\n");

    printf("------------data->depencity_list------------\n\n");
    config_node *curr_node = data->depencity_list;
    while(curr_node!=NULL){
        printf("-------NODE-----\n");
        printf("key:%s\n value:%s ptr next:%p\n",curr_node->key,curr_node->value,(void*)curr_node->next);
        curr_node = curr_node->next;
    }
    printf("\n");
    printf("-------NODE-----\n");
    printf("last depencity node:%s\n",data->last_depencity_node==NULL ? "NULL" : data->last_depencity_node->key);

    printf("------------data->file_list------------\n\n");
    file_node *curr_fnode = data->file_list;
    while(curr_fnode!=NULL){
        printf("-------NODE-----\n");
        printf("file:%s\n original_val:%s\n extension:%i\n",curr_fnode->file,curr_fnode->original_val,curr_fnode->extension);
        curr_fnode = curr_fnode->next;
    }
    printf("\n");
    printf("-------NODE-----\n");
    printf("last file node:%s\n",data->last_file_list==NULL ? "NULL" : data->last_file_list->file);

    printf("------------data->option_list------------\n\n");

    option_node *curr_onode = data->option_list;
    while(curr_onode!=NULL){
        printf("-------NODE-----\n");
        printf("option:%s\n",curr_onode->option);
        curr_onode = curr_onode->next;
    }
    printf("\n");
    printf("-------NODE-----\n");

    printf("last option node:%s\n",data->last_option==NULL ? "NULL" : data->last_option->option);

    printf("==================\n");
}