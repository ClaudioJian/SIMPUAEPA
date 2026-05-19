#ifndef ACEX_normalize
#define ACEX_normalize

#include "general_includes.h"
#include "error.h"


/**
 * check if character passed is valid for path.
 * 
 * IMPORTANT: some character and word that is invalid isn't included.
 * @return
 * 
 * - -1 = error
 * 
 * - 0 = valid
*/
int valid_path_chr(const char chr);

//return int else -1 not int
int convert_str_to_int(const char* string);

// return 1 if is non printable(except \0 and \n), \t, \r
int is_ignorable_chr(const char chr);


/**
 * trim header and end space,\t,\r,non printable chr from src_ptr , stop when hit delimeter chr and skip it, 
 * 
 * the pointer passed in src_ptr will be changed to next char after delimeter or end of line.
 * @param int max_size for extracted value
 * 
 * normalized string will be stored in dst.
 * @return start point. NULL if error(buffer overflow)
*/
char* normalize_value(char* dst,char **src_ptr,const int max_size,const char delimiter,error_details *err);

/** 
 * Replace / and \\ to OS specific slash(e.g /).
 * 
 * if path contain invalid chr, throw error(malloc/buffer overflow).
 * @return normalized path or NULL(error/invalid path)
*/
char* normalize_path(const char* path,error_details *err);

/**
 * @returns
 * 
 * - -1 invalid path(buffer overflow, invalid chr, malloc error)
 * 
 * - 0 invalid extension
 * 
 * - 1 php
 */
int valid_extension(char *extension,error_details *err);

//convert all string to upper case and return upper case string
//
// you must free the return value after use it.
//
// return NULL(malloc error)
char *convert_str_to_upper(const char *string,error_details *err);

void stop();
#endif