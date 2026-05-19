#ifndef display_info
#define display_info


#include "general_includes.h"

//===============================Function==============================


/**
 * If the words are too long, add "-" at end of word and print rest of it in new line.
 * otherwise, if it just hit max_line_chr, switch to new line and print all of it in new line.
 * 
 * The new line at end of text is missed, please add new line at end manually.
 * 
 * @param label the text to be printed
 * @param new_line_prefix the prefix for new line, for example: "|  || "
 * @param line_chr initial number of char in current line
 */
size_t display_wrapped_text(const char *label,const char *new_line_prefix,size_t line_chr,const size_t max_line_chr);

//===============================Function==============================



#endif