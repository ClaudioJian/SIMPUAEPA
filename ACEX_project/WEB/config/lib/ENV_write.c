#include "ENV_write.h"


int ENV_CONFIG_write(ENV_CONFIG_field *data,const char *file_name,int *error_code){
    FILE *fptr = fopen(file_name,"w");

    if(fptr==NULL){
        *error_code = ERR_file_creation;
        return -1;
    }

    // 3 is \0, = and \n
    const size_t buff_size = MAX_VALUE_SIZE + MAX_KEY_SIZE+3;

    //writing
    config_node *curr_node = data->depencity_list;

    while(curr_node!=NULL){
        char buffer[buff_size];
        const int expected = snprintf(buffer,buff_size,"%s=%s\n",curr_node->key,curr_node->value);
        if(ERR_snprintf(expected,buff_size,error_code)) return -1;

        fputs(buffer,fptr);
        //advance
        curr_node = curr_node->next;
    }

    if(fclose(fptr)==EOF){
        *error_code = ERR_fclose;
        return -1;
    }

    return 0;
}