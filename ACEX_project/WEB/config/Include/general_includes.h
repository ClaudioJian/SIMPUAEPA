

#ifndef general_includes
#define general_includes

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <ctype.h>
#include <errno.h>

#include "error_type.h"
#include "ENV_const.h"
#include "display_info.h"

#include "ACEX_normalize.h"

typedef struct config_states{
    global_values *flags;
    error_details *err;
} config_states;


#endif