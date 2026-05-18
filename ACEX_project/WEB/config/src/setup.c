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