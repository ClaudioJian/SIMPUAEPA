#ifndef ENV_storage
#define ENV_storage

#include "general_includes.h"
#include "ENV_CONFIG.h"

//-----------------function----------------------

///@return 1 alredy in data else 0
int ENV_CONFIG_is_alredy_set(const char *setting, ENV_CONFIG_field *data);

/**
 * find the setting passed in previous data. if previous data has alredy set that value, copy that old data's value and copy in new data's value and delete data in previous data.
 * 
 * @return
 * 
 * - 0 not find/ no previous data
 * 
 * - 1 find
 */
int ENV_CONFIG_cpy_prev_data(ENV_CONFIG_field *data, ENV_CONFIG_field *prev_data,error_details *err);

/**
 * store used setting in data'storage or in file storage for future use.
 * 
 * When tracked, setting that is alredy stored will be ignored when finding next setting.
 * 
 * - If is file, check if extension valid and throw error if invalid. set to data->file_list.
 * - If flag is e (mode=2) and data->value is also "null", don't track it
 * @param err change address of it, check content
 * @return
 * - 0 sucess
 * 
 * - -1 = if error occurs: extension_invalid, malloc error
 */
int ENV_CONFIG_track_depencity(ENV_CONFIG_field *data, error_details *err);

#endif