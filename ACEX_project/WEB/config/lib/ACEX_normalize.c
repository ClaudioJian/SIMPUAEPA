#include "ACEX_normalize.h"


int convert_str_to_int(const char* string){
    int is_int=1;
    int op = 0;

    //check if is int, break instantly if contain any non digit
    for(int i=0;string[i]!='\0';i++){
        if(isdigit(string[i])){
            //convert into int
            op = (op*10) + abs(string[i] - '0');
        }else{
            is_int = 0;
            break;
        }
    }

    if(is_int) return op; else return -1;
}


char *convert_str_to_upper(const char *string,int *error_code){
    char *buffer = malloc(strlen(string)+1);
    if(!buffer){ 
        free(buffer);
        buffer = NULL;

        *error_code = ERR_malloc;
        return NULL;
    }

    for(int i=0;string[i]!='\0';i++){
        buffer[i] = (char)toupper((char)string[i]);
    }
    buffer[strlen(string)] = '\0';
    return buffer;
}







int valid_path_chr(const char chr){
    const char invalid_chr[] = {'<','>','"','|','*','?','\0'};
    if(chr>=0 && chr<=31) return -1;
    

    for(int i=0;invalid_chr[i]!='\0';i++){
        if(chr==invalid_chr[i]){
            return -1;
        }
    }
    return 0;
}







int is_ignorable_chr(const char chr){
    if(chr>=0 && chr<=31 && chr !='\n' && chr !='\0') return 1;
    if(chr == '\r' || chr == '\t') return 1;
    return 0;
}



char* normalize_value(char *dst,char **src_ptr,const int max_size,const char delimiter,int *error_code){
    char *start = dst;
    int count = 0;
    
    //skip header
    while(**src_ptr!='\0'&&(**src_ptr==' '|| is_ignorable_chr(**src_ptr))) (*src_ptr)++;


    while((**src_ptr)!='\n' && (**src_ptr) !='\0' && (**src_ptr)!= delimiter){
        //skip
        if(is_ignorable_chr(**src_ptr)) {
            (*src_ptr)++;continue;
        }

        dst[count] = **src_ptr;
        (*src_ptr)++;

        count++;
        if(count >= max_size) {
            *error_code = buffer_overflow;
            return NULL;
        }
    }
    (*src_ptr)++;

    //back until hit non ignorable value
    while(count>=0){
        char prev = dst[count-1];
        if(prev!= ' ' && !is_ignorable_chr(prev)) break;
        count--;
    }

    dst[count] = '\0';
    return start;
}




char* normalize_path(const char* path,int *error_code){
    if(!path) return NULL;

    if(strstr(path,".." SLASH) != NULL){
        printf("%p\n",strstr(path,".." SLASH));
        *error_code = ERR_PATH_invalid;
        return NULL;
    }

    char *buffer = malloc(MAX_INPUT_SIZE);

    if(!buffer) {
        free(buffer);
        buffer = NULL;

        *error_code = ERR_malloc;
        return NULL;
    }

    char *buf_ptr = buffer; //copy and point to buffer

    int i = 0;

    while(path[i]== ' '||is_ignorable_chr(path[i])) i++;

    for(; i<MAX_INPUT_SIZE && path[i] != '\n' && path[i] != '\0';i++){

        if(valid_path_chr(path[i])) return NULL;
        //avoid escaping root dir


        if(path[i]=='\\' || path[i]=='/') *buf_ptr = SLASH_CHR;
        else *buf_ptr = path[i];
        buf_ptr++;
    }

    if(i>=MAX_INPUT_SIZE) {
        free(buffer);
        buffer = NULL;

        *error_code = buffer_overflow;
        return NULL;
    };

    *buf_ptr = '\0';
    return buffer;
}


int valid_extension(char *extension,int *error_code){
    char buffer[MAX_VALUE_SIZE];
    int start_extension = 0;
    int j=0;

    // normalize the path
    char *right_path = normalize_path(extension,error_code);
    if(right_path==NULL||*error_code) return -1;

    strcpy(extension,right_path);

    free(right_path);
    right_path = NULL;

    //check for extension
    for (int i = 0; i < (int)strlen(extension) && (extension[i]!= '\0'||extension[i]!= '\n'||extension[i]!= '\r'); i++)
    {
        if(start_extension) {
            buffer[j] = (char)toupper((char)extension[i]);
            j++;
        }        
        if(extension[i]=='.') {
            if(start_extension) {
                //reset
                memset(buffer,0,MAX_VALUE_SIZE);
                j = 0;
            }
            else start_extension = 1;
        }
    }
    buffer[j] = '\0';


    if(strcmp(buffer,"PHP")==0) return 1;
    else return 0;
}





void stop(){
    //stop the cmd until user quit or type 'q' so they can see what is going
    while(1){
        char answer[40];

        printf("Do you want exit(enter \"q\" to quit program)?");
        scanf("%39s",answer);

        if(answer[0]=='q'||answer[0]=='Q') break;
    }
}