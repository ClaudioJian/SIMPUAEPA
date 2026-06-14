//-------------------------Info about this file-----------------------------------
/*
this files is to:
1. download depencity,
2. run script which construct database, 
3. force user to set their .env file
*/

//-----------------------custom includes----------------------------
#include "logic_main.h"




//----------------------------------------main---------------------------------------------------------------------
#include <stdlib.h>



int main(){
    //moving to root of project
    if(chdir(ROOT)){
        printf("Cannot move to root directory\n");
        stop();
        return 1;
    };

    if(MAX_LINE_CHR<10) {
        printf("MAX_LINE_CHR cannot be lower than 10\n");
        stop();
        return 1;
    }



    //setting guide

    printf("======================================================================\n");
    printf("                  Welcome to the Server Setup Application!             \n");
    printf("======================================================================\n");
    printf("This tool streamlines initial configurations for sensitive data and common\n");
    printf("settings, ensuring consistency across different machines.\n\n");

    printf("Once completed, this program will:\n");
    printf("  1. Create a '.env' file in the project root to store common settings\n");
    printf("     and sensitive credentials (e.g., database passwords).\n");
    printf("  2. Create a 'vendor' folder in the project root containing all\n");
    printf("     required project dependency libraries.\n\n");
    printf("PLEASE CHECK LICENSE FILE IN PROJECT ROOT!!!\n\n");
    printf("CRITICAL SECURITY NOTE:\n");

    printf("Never commit or track '.env' files or the 'vendor' folder in Git!\n\n");
    
    printf("Configuration & Customization Guides:\n");
    printf("  - General program behavior:  Modify '.internal.cfg' (e.g., warning flags)\n");
    printf("  - Core system constraints:   Review 'Include/ENV_const.h' (e.g., buffer sizes)\n");
    printf("  - Environment variables:     Add or modify fields in '.env.example'\n");
    printf("  - Troubleshooting & Details: Refer to 'readme.txt'\n\n");

    printf("Usage Hints:\n");
    printf("  - To accept a default value: Leave the input empty and press ENTER.\n");
    printf("  - Maximum allowed lengths:   Input = %i, Value = %i chars, Key = %i chars.\n\n", MAX_INPUT_SIZE, MAX_VALUE_SIZE, MAX_KEY_SIZE);

    printf("System Support: Windows, MySQL\n");
    printf("======================================================================\n\n");


    //----------------------start program---------------------------
    


    error_details *err = (error_details*) malloc(sizeof(error_details));
    if(!err){printf("out of memory!\n");return 1;}

    err->description[0] = '\0';
    err->code = 0;
    err->err_trace = NULL;
    err->last_error = NULL;

    global_values *gv = global_value_init();
    if(!gv){printf("out of memory!\n");return 1;}

    config_states *states = (config_states*) malloc(sizeof(config_states));
    if(!states){printf("out of memory!\n");return 1;}
    
    states->flags = gv;
    states->err = err;

    catch_err(set_abs_path(states));
    if(err->code){
        print_error(states);
        printf("|\n");
        return 1;        
    }

    ENV_CONFIG_field *internal_data = catch_err(ENV_init_config_struct(CONFIG_FILE,states->err));
    ENV_CONFIG_field *ENV_data = catch_err(ENV_init_config_struct(ENV_EXAMPLE,states->err));
    ENV_CONFIG_field *prev_data;

    if(!err->code){prev_data = catch_err(start_program(internal_data,ENV_data,states));};

    printf("===================Result===================\n");
    printf("|\n");
    printf("| Cleaning datas...\n");

    
    
    ENV_CONFIG_destroy(&internal_data);
    ENV_CONFIG_destroy(&ENV_data);
    if(prev_data != NULL) ENV_CONFIG_destroy(&prev_data);


    printf("| All data have been cleaned!\n");
    printf("|\n");


    //error handle
    if(err->code) {
        print_error(states);
    }
    else{
        printf("| All files have been executed!\n");
    }
    free_states(&states);

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