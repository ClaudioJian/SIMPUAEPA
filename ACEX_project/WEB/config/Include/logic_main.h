#ifndef logic_main
#define logic_main

#include "general_includes.h"
#include "ENV_CONFIG.h"
#include "ENV_read.h"
#include "ENV_storage.h"
#include "ENV_UI.h"
#include "ENV_write.h"



void free_global_value();
void start_program(ENV_CONFIG_field *internal_data,ENV_CONFIG_field *ENV_data,int *error_code);

//get absolute path of project root and store in global value ABSOLUTE_PATH
// return 0 sucess else -1: no absolute path find, malloc error, pclose error
int set_abs_path(int *error_code);

extern char* PHP_LOCATION;
extern int WARNING_FLAGS;

#endif