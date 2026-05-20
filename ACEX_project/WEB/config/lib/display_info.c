#include "display_info.h"

//loop to prevent word is too long to fit a line. return total char added in this loop
static size_t hyphenate_word(const char *word, size_t line_chr, const size_t new_lprefix_len, const char *new_line_prefix , const size_t max_line_chr){
    size_t word_i=0;
    while(word[word_i]!='\0'){
        // only print newline when hit the word it self too big,else will just skip to next newline
        if(word_i >= (max_line_chr-1)){
            printf("-\n%s ", new_line_prefix);
            line_chr = new_lprefix_len + 1;
        }
        putchar(word[word_i++]);
        line_chr++;
    }
    return line_chr;
}



size_t display_wrapped_text(const char *label,const char *new_line_prefix,size_t line_chr,const size_t max_line_chr){
    char word[MAX_TEXT_SIZE];
    int word_size = 0;
    const size_t new_lprefix_len = strlen(new_line_prefix);

    for(size_t label_i=0;; label_i++){
        char curr_chr = label[label_i];

        //if word end, store it
        if(curr_chr == ' '||curr_chr == '\n'||curr_chr == '\0'){
            word[word_size] = '\0';
            if(word[0]=='\0') continue;
            
            //print new line when total text in char is too big, else print directly
            if((size_t)word_size + line_chr >= max_line_chr){
                printf("\n%s ", new_line_prefix);
                line_chr = new_lprefix_len + 1;
            }
            //word -> wor-d if too long
            line_chr = hyphenate_word(word,line_chr,new_lprefix_len,new_line_prefix,max_line_chr);

            //if not end, put space
            if(curr_chr !='\0') {
                line_chr++; //space
                putchar(' ');
            }

            //reset
            word_size = 0;
        }else{
            word[word_size++] = curr_chr;
        }
        //if hit end of label but still have word, continue, after that, will be break because the word size is reset
        if(curr_chr == '\0' && word_size<=0) break;
    }
    return line_chr;
}