#ifndef logic_main
#define logic_main

#include "general_includes.h"
#include "error.h"
#include "ENV_CONFIG.h"
#include "ENV_read.h"
#include "ENV_storage.h"
#include "ENV_UI.h"
#include "ENV_write.h"





void free_states(config_states **states);

///print main logic of program, run all steps and return immediately if any error happen and change error code
/// @return previous data even error happens if exist else NULL
ENV_CONFIG_field *start_program(ENV_CONFIG_field *internal_data,ENV_CONFIG_field *ENV_data,config_states *states);

//get absolute path of project root and store in global value ABSOLUTE_PATH
// return 0 sucess else -1: no absolute path find, malloc error, pclose error
int set_abs_path(config_states *state);


#endif