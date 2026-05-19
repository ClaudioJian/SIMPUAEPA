#ifndef ENV_storage
#define ENV_storage

#include "general_includes.h"
#include "ENV_CONFIG.h"

//-----------------function----------------------

///@return 1 alredy in data else 0
int ENV_CONFIG_is_alredy_set(const char *setting, ENV_CONFIG_field *data);

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