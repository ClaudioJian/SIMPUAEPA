#ifndef ENV_UI
#define ENV_UI

#include "general_includes.h"
#include "ENV_CONFIG.h"
#include "ENV_read.h"
#include "ENV_storage.h"

//-----------------function----------------------

/**  
 * start from the latest posision to grab value. if alredy set, skip that value. stop only when find new value that isn't set.
 * 
 * @param mode (r_mode)0 = read mode ,(w_mode)1 will ask to set, (internal_mode)2 mark file read as internal
 * 
 * REMEMBER to clear data by using ENV_CONFIG_clear(data)!!!
 * if the file alredy eof, don't run this unless has used ENV_CONFIG_rewind or manually set flag to 0. 
 * 
 * if previous data is passed(not null), then it will try to write same data in new file.
 * 
 * Add affected_data if has new setting is set.
 * 
 * Set value depedends what user inputed(or use default value if nothing inputed finded) in env_data.
 * 
 * display label to explain what this value does.
 * @return 1=fail(EOF) 0=sucess -1 error = buffer overflow/malloc error
 */
int ENV_CONFIG_step_config(ENV_CONFIG_field *data,ENV_CONFIG_field *prev_data,const int mode,config_states *states);


/**  
 * display label to explain what this value do.
 * 
 * Ask confimation of their setting. if user enter nothing, use default value.
 * 
 * if has options, ask user to enter number for option. if input is invalid, ask user to enter again until valid.
 * 
 * if is file, check if file exist and valid. if invalid, ask user to enter again until valid.
 * 
 * Add affected_data if has new setting is set.
 * 
 * Receive user input and filtering that. the filtered data will be copied in data.
 * @return
 * - error when: malloc error, buffer overflow
 * 
 * - change variable NEWLINE_BETWEEN_VARIABLES: 1 if not simplified display info or is simplified display with option else 0
*/
void ENV_CONFIG_ui_prompt(ENV_CONFIG_field* data, config_states *states);

/**
 * Set key indicated to data.
 * 
 * REMEMBER to clear data by using ENV_CONFIG_clear(data)!!!
 * 
 * if previous data is passed(not null), then it will try to write same data in new file
 * 
 * Try to find in all .env.example the key contain exactly same name passed in argument.
 * Cannot be used to set file.
 * @param mode (r_mode)0 = read mode ,(w_mode)1 will ask to set, (internal_mode)2 mark file read as internal [don't display internal data with flag 's']
 * @returns int
 * - 1 = true if sucess 
 * 
 * - 0 = false if key cannot finded 
 * 
 * - -1 = buffer overflow, fseek error
 */
int ENV_CONFIG_adjust_key(const char *setting,ENV_CONFIG_field *data, ENV_CONFIG_field *prev_data,const int mode,config_states *states);

#endif

