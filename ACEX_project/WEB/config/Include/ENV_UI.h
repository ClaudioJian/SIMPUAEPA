#ifndef ENV_UI
#define ENV_UI

#include "general_includes.h"
#include "ENV_CONFIG.h"
#include "ENV_read.h"
#include "ENV_storage.h"

//-----------------function----------------------

/**
 * If the words are too long, add "-" at end of word and print rest of it in new line.
 * otherwise, if it just hit max_line_chr, switch to new line and print all of it in new line.
 * 
 * The new line at end of text is missed, please add new line at end manually.
 * 
 * @param label the text to be printed
 * @param new_line_prefix the prefix for new line, for example: "|  || "
 * @param line_chr initial number of char in current line
 */
void display_wrapped_text(const char *label,const char *new_line_prefix,size_t line_chr,const size_t max_line_chr);

/**  
 * start from the latest posision to grab value. if alredy set, skip that value. stop only when find new value that isn't set.
 * 
 * REMEMBER to clear data by using ENV_CONFIG_clear(data)!!!
 * if the file alredy eof, don't run this unless has used ENV_CONFIG_rewind or manually set flag to 0. 
 * 
 * Set value depedends what user inputed(or use default value if nothing inputed finded) in env_data.
 * 
 * display label to explain what this value does.
 * @return 1=fail(EOF) 0=sucess -1 error = buffer overflow/malloc error
 */
int ENV_CONFIG_step_config(ENV_CONFIG_field *data, int *error_code);


/**  
 * display label to explain what this value do.
 * 
 * Ask confimation of their setting. if user enter nothing, use default value.
 * 
 * if has options, ask user to enter number for option. if input is invalid, ask user to enter again until valid.
 * 
 * if is file, check if file exist and valid. if invalid, ask user to enter again until valid.
 * 
 * Receive user input and filtering that. the filtered data will be copied in data.
 * @return
 * - error when: malloc error, buffer overflow
*/
void ENV_CONFIG_ui_prompt(ENV_CONFIG_field* data, int *error_code);

/**
 * Set key indicated to data.
 * 
 * REMEMBER to clear data by using ENV_CONFIG_clear(data)!!!
 * 
 * Try to find in all .env.example the key contain exactly same name passed in argument.
 * Cannot be used to set file.
 * @returns int
 * - 1 = true if sucess 
 * 
 * - 0 = false if key cannot finded 
 * 
 * - -1 = buffer overflow, fseek error
 */
int ENV_CONFIG_adjust_key(const char *setting, ENV_CONFIG_field *data, int *error_code);

#endif

