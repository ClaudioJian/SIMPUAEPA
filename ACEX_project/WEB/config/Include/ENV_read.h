#ifndef ENV_read
#define ENV_read

#include "general_includes.h"
#include "ENV_CONFIG.h"

//-----------------function----------------------

/** find non comment line start from current line and set the label if find in ENV_CONFIG_field.
 * 
 * set start_offset if encounter first label/skip/required.
 * 
 * if the file alredy eof, don't run this unless has used ENV_CONFIG_rewind or manually set flag to 0.
 * @param error_code change address of it, check content
 * @return 
 * - 1=sucess 
 * 
 * - 0=fail(EOF)
 * 
 * - -1 = buffer overflow
*/
int ENV_CONFIG_scan_next_data(ENV_CONFIG_field *data, int *error_code);

/**  
 * find the setting with same name passed(setting), trying to compare the variable name in .env.example to string passed. 
 * 
 * Reading start from 0(skip to first data as shortcut)
 * @param error_code change address of it, check content
 * @return 
 * - 1=finded 
 * 
 * - else=0
 * 
 * - -1 = buffer_overflow, fseek_error
*/
int ENV_CONFIG_match(const char *setting, ENV_CONFIG_field *data, int *error_code);


#endif