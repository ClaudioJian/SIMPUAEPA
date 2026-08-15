#include "ENV_const.h"

int emergency_in_use = 0;

global_values* global_value_init(){
    global_values *gv = (global_values*) malloc(sizeof(global_values));
    if(!gv){
        free(gv);
        gv = NULL;
        return NULL;
    }

    gv->ABSOLUTE_PATH = NULL;
    gv->PHP_LOCATION = NULL;
    gv->WARNING_FLAGS = 1;
    gv->SHOW_DEBUG_INFO = 1;
    gv->SHOW_ERR_LINE = 1;
    gv->SHOW_READ_ONLY = 0;
    gv->NEWLINE_BETWEEN_VARIABLES = 1;
    gv->SIMPLIFIED_DISPLAY = 0;

    return gv;
}