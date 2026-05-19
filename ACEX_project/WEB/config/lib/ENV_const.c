#include "ENV_const.h"

char *PHP_LOCATION;
char* ABSOLUTE_PATH;
int WARNING_FLAGS = 1;

int SHOW_DEBUG_INFO = 1;
int SHOW_ERR_LINE = 1;
//fall back when malloc error
int emergency_in_use = 0;