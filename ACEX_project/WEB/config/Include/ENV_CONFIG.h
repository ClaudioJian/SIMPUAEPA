#ifndef ENV_CONFIG
#define ENV_CONFIG

#include "general_includes.h"

/**
 * structure set files to run when setting
 *
 * int extension:
 * - 0 = invalid
 * 
 * - 1 = .php
 * 
 * - 2 = .sql
*/ 
typedef struct file_node{
    char original_val[MAX_VALUE_SIZE];
    char file[MAX_VALUE_SIZE];
    int extension;
    struct file_node *next;
}file_node;

//struct store setting alredy set, so it can ignore that value
typedef struct config_node{
    char key[MAX_KEY_SIZE];
    char value[MAX_VALUE_SIZE];
    struct config_node *next;
}config_node;

typedef struct option_node{
    char option[MAX_OPTION_SIZE];
    struct option_node *next;
}option_node;


// uses for store information about line.
//
// is_file = 0 or 1
//
// is_EOF = stop any function to run if set to 1
//
//
// file_list = list contain all executable files(won't be written)
//
// option_list = temporary list that store all options
//
// mode: 0 = optional value, 1 = required value, -1 = skip value, 2 = user can select to ignore in run time
//
// start_offset: shortcut to first non comment line.
//
// depencity list will be written to new file, but excluted_list won't
typedef struct{
    int mode;
    int is_file;
    int is_EOF;
    int quant_option;
    char original_value[MAX_VALUE_SIZE];
    char value[MAX_VALUE_SIZE];

    char label[MAX_TEXT_SIZE];
    char key[MAX_KEY_SIZE];
    
    long start_offset;

    config_node *depencity_list;
    config_node *last_depencity_node;

    file_node *file_list;
    file_node *last_file_list;

    option_node *option_list;
    option_node *last_option;

    FILE *fp;
}ENV_CONFIG_field;

//----------------------------------functions-------------------------------------------
/**  
 * set data and open file, saving into structure
 * @param error_code change address of it, check content
 * @return 
 * - sucess: struct and empty datas
 * 
 * - error: NULL pointer - error_code -> malloc error, fopen error
*/
ENV_CONFIG_field* ENV_init_config_struct(char *file_name,int *error_code);

// free and set all option node to null
void ENV_CONFIG_clear_option(ENV_CONFIG_field *data);

//reset value, key, label, required and clean option list
void ENV_CONFIG_clear(ENV_CONFIG_field *data);

//reset data and make it start from last non comment line, this is for throught from start and free after find target. also reset is_EOF
void ENV_CONFIG_rewind(ENV_CONFIG_field *data);

//release all memory of data and close file
void ENV_CONFIG_destroy(ENV_CONFIG_field **data);

//printing the data structure, only for debug
void print_data(ENV_CONFIG_field *data);

#endif
