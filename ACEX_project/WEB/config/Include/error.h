#ifndef error
#define error

#include "general_includes.h"

//===============================Function==============================

/**
 * check if snprintf has error and change error code
 * @return
 * - 0 = sucess
 * 
 * - -1 = error: encoding_error, buffer overflow
 */
int ERR_snprintf(const int expected,const size_t max_size,int *error_code);
void print_error(const int error_code);

#endif