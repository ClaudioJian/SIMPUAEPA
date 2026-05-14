//-------------------------Info about this file-----------------------------------
/*
this files is to:
1. download depencity,
2. run script which construct database, 
3. force user to set their .env file
*/

//-----------------------custom includes----------------------------
#include "logic_main.h"

//------------------------------------global variable--------------------------------------------
char *PHP_LOCATION;
int WARNING_FLAGS = 1;
char* ABSOLUTE_PATH;


//-------------------------------------function----------------------------------------------------
static void print_error(int error_code){
    printf("| ERROR FOUND!\n");
        switch(error_code){
            case buffer_overflow:
                printf("| buffer overflow!"); break;
            case fopen_error:
                printf("| cannot open/create file!"); break;
            case fclose_error:
                printf("| some thing happen when writing! please check if is open or it's permission"); break;
            case variable_start_digit:
                printf("| variable name/key in .env.example cannot start with any digit!"); break;
            case variable_start_special_char:
                printf("| variable name/key in .env.example cannot start with any special char(except for file)!");break;
            case ENV_invalid_format:
                printf("| format for variable name/key in .env.example shoud contain \"=\"!"); break;
            case ENV_empty_value:
                printf("| Values with skip flag in configuration file cannot be empty!"); break;
            case invalid_path:
                printf("| format for path contains invalid characters(note: some invalid character are not included)!"); break;
            case fseek_error:
                printf("| format for variable name/key in .env.example shoud contain \"=\"!"); break;
            case extension_invalid:
                printf("| file type is not supported!"); break;
            case malloc_error:
                printf("| cannot allocate memory for malloc()!"); break;
            case php_not_found:
                printf("| cannot find php!"); break;
            case too_many_options:
                printf("| max option can be used is %i!",MAX_OPTIONS); break;
            case ERR_file_creation:
                printf("| cannot create file! Please check if you have permission and isn't open!"); break;
            case premition_denied:
                printf("| Permission denied!"); break;
            case invalid_boolean:
                printf("| invalid boolean value! only accept \"on\", \"off\", \"true\", \"false\", integer 0(false) and positive integer 1(true)!"); break;
            case pclose_error:
                printf("| Command not found or exited with error status!"); break;
            case no_abs_path:
                printf("| cannot get absolute path of project root!\n"); break;
            case composer_not_exist:
                printf("| composer.phar not found in project root! Please check if you have it and try again!"); break;
            case composer_error:
                printf("| Dependency solving error code in composer!"); break;
            case ERR_set_enviroment_value:
                printf("| some thing went wrong when set enviroment variable!"); break;
            case ssl_cert_error:
                printf("| SSL certificate problem!\n");
                printf("| Please check if certification exist and are up to date!\n");
                printf("| Also verify in read me to more possible reason...");
                break;
            case test_sucess:
                printf("| test went sucessfull!"); break;
        }
}

//----------------------------------------main---------------------------------------------------------------------
#include <stdlib.h>

int main(){
    printf("PLEASE CHECK LICENSE FILE IN PROJECT ROOT!!!\n\n");

    //moving to root of project
    if(chdir(ROOT)){
        printf("Cannot move to root directory\n");
        stop();
        return 1;
    };



    //setting guide
    printf("\nWelcome to server setup app!\n");
    printf("This app is used to set initial configuration for sensitive data and common setting.\n");
    printf("These data/settings usually change between machine to have better security.\n");
    printf("For example, database password.\n");
    printf("To add or change values, consult in .env.example\n\n");
    printf("Current support: Windows\n\n");

    printf("To using default value: input nothing and press enter key\n");
    printf("Maxium lenght allowed: input = %i; value = %i char; key = %i char\n",MAX_INPUT_SIZE,MAX_VALUE_SIZE,MAX_KEY_SIZE);

    printf("\n\n");

    //----------------------variable--------------------------------
    int error_code = 0;

    //----------------------start program---------------------------
    set_abs_path(&error_code);
    if(error_code){
        print_error(error_code);
        putchar('\n');
        stop();
        return 1;        
    }

    ENV_CONFIG_field *internal_data = ENV_init_config_struct(CONFIG_FILE,&error_code);
    ENV_CONFIG_field *ENV_data = ENV_init_config_struct(ENV_EXAMPLE,&error_code);

    if(!error_code) start_program(internal_data,ENV_data,&error_code);

    printf("===================Result===================\n");
    printf("|\n");
    printf("| Cleaning datas...\n");

    
    free_global_value();
    ENV_CONFIG_destroy(&internal_data);
    ENV_CONFIG_destroy(&ENV_data);

    printf("| All data have been cleaned!\n");
    printf("|\n");


    //error handle
    if(error_code) {
        print_error(error_code);
        putchar('\n');
    }
    else{
        printf("| All files have been executed!\n");
    }

    printf("|______________________________________\n");
    putchar('\n');

    //stop the cmd until user quit or type 'q' so they can see what is going
    while(1){
        char answer[40];
        int i = 0;

        printf("Do you want exit(enter \"q\" to quit program)?");
        scanf("%39s",answer);

        while (answer[i] == ' ' || answer[i] == '\t') i++;
        if(answer[i]=='q'||answer[i]=='Q') break;
    }

    return 0;
}