#ifndef ENV_write
#define ENV_write

#include "general_includes.h"
#include "ENV_CONFIG.h"

//===============================Function==============================

/**
 * write .env file(new or override).
 * 
 * The file name is .env.environment or pure .env depedent user setting.
 * 
 * Loop throught data->depencity list and use its key=value\n to write.
 * @return
 * - 0 = sucess
 * 
 * - -1 = error: cannot create/overwrite file, fclose error, encoding_error, buffer ovwerflow
 */
int ENV_CONFIG_write(ENV_CONFIG_field *data,const char *file_name,error_details *err);

#endif