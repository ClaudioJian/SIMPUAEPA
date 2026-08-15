#ifndef error
#define error


#include "ENV_const.h"
#include "display_info.h"
#include "error_type.h"


typedef struct config_states{
    global_values *flags;
    error_details *err;
} config_states;

//===============================Function==============================

int ERR_snprintf(const int expected,const size_t max_size,error_details *err);
void print_error(config_states *states);

int track_error(error_details *err, const char *file,const char *funct_name, const unsigned int line);
void ERR_details_destroy(error_details **err);

extern trace_error emergency_node;
//===============================Function==============================



#endif