#include "display_info.h"

size_t display_wrapped_text(const char *label,const char *new_line_prefix,size_t line_chr,const size_t max_line_chr){
    char word[MAX_TEXT_SIZE];
    int word_size = 0;
    const size_t new_lprefix_len = strlen(new_line_prefix);

    for(size_t label_i=0;; label_i++){
        char curr_chr = label[label_i];

        if(curr_chr == ' '||curr_chr == '\n'||curr_chr == '\0'){
            word[word_size] = '\0';
            if(word[0]=='\0') continue;
            
            //print new line and set to 6, else print directly
            //if word size is smaller than remain line char, this loop will be ignored completly.
            if((size_t)word_size + line_chr >= max_line_chr){
                printf("\n%s ", new_line_prefix);
                line_chr = new_lprefix_len + 1;
            }
                
            //loop to prevent word is too long to fit a line.
            size_t word_i=0;
            while(word[word_i]!='\0'){
                // only print newline when hit the word it self too big,else will just skip to next newline, 7 is "|  || " and "-"
                if(word_i >= (max_line_chr-1)){
                    printf("-\n%s ", new_line_prefix);
                    line_chr = new_lprefix_len + 1;
                }
                putchar(word[word_i++]);
                line_chr++;
            }
            //if not end, put space
            if(curr_chr !='\0') {
                line_chr++;//space
                putchar(' ');
            }
            //reset
            word_i=0;
            word_size = 0;
        }else{
            word[word_size++] = curr_chr;
        }
        //if hit end of label but still have word, continue, after that, will be break because the word size is reset
        if(curr_chr == '\0' && word_size<=0) break;
    }
    return line_chr;
}