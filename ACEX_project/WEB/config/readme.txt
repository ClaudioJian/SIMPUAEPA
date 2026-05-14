1. What this folder does:
    This folder is for automation install all depencity, run some script, create new .env file, for example: script create database, setting server configurations.
    IMPORTANT:
        When you are trying add new setting to use for future, don't just change .env or setting enviroment value directly, 
        you should change .env.example to other developers have same setting as your computer.
        If you are uptading database structure, please list the script/file that uptade database inside internal.cfg like example below:
            #l: file to ...
            #f:
            v3_name_schema.sql

        when setup.exe runs, it will go to project's root!



2. How to use:
    Double click setup.exe, after that, it will start ask you to set some value in to put in .env. and some internal use configs.
    If has some error see in 6. troubleshooting
    

3. What others folders and files do:
    - [.env]: This file contain all common setting and sensitive data that are only avaible in your computer, 
              others one and real server will contain diferent values for security reason.
              Will be written in project root.
              Important: do not send .env file to github.
    A) config folder:
        Recommend to see instruction in .env.example or in internal.cfg if you are changing/add new key name/default values.
        - [.env.example]: All setting inside this file will be written in new .env(sitting in project root) to use in other applications.
                          Example: #l:new values
                                   NEW_VALUE = 12345
        - [internal.cfg]: All setting used for setup.exe. You should add inside area "[FILES]" new files to run if you need.
                          Example: #l:test file
                                   test.php
    B) src folder:
        - database folder: here put all script to setting up database like create table, user and Etc.
        - server folder: here put all script set setting for server.
        setup.c: source code for setup.exe
    C) other folders:
        - [lib]: source code for both dll and .o files
        - [Include]: header file for .c code.
                     You can change some setting to change setup.exe behaviour like key size in ENV_const.h too if you want use less memory/perfomance(re-compile after change)
        - others: put compiled files
    [makefile] : script to compile setup.exe. you need have gcc and makefile installed to use. You don't need if you never need re-compile
    (you can also download binary file directly in github if some uptade drop for setup.exe)


4. Restrictions:
    This may fail on other computer/enviroment and that is not tested.
    Only tested in Windows 10.

    When you are adding new/modify env values that is used in other files, please change key name and value of .env.example for others developers use.
    If you are changing folder location inside this folder, please change constant in Include/ENV_const.h and notice others developers about this change

    Only support .sql and php script to run. The database only supported is mysql.
    You can use makefile to recompile, only necessary if you are changing any script except inside: src/database , src/server, all files in config

    If that fail, please see instruction for "x. mannually setting".

    

5. Manually setting:
    If .env file didn't created, mean you need go configure manually:
        1. copy file with name .env.example to project root
        2. change file name to .env (additionally, you can change to .env.production for different situation)
        3. change mostly of value inside .env
    .env should store sensitive data(WARNING: ONLY IN BACKEND .env) and usual configure
    do not move .exe to other place, if you want move/change .c or other file(like install_composer.php) to other place, please configure makefile and setup.c to match changes and uptade .exe


    
6. troubleshooting
    If show error similar to "SSL certificate problem" : 
        1. system clock desync
        2. certificate out data, uptade in https://curl.se/docs/caextract.html
        3. antivirus fault, disable it TEMPORARY to check. also
        see in https://getcomposer.org/doc/articles/troubleshooting.md#ssl-certificate-problem-unable-to-get-local-issuer-certificate

6. To uptade setup.exe(skip this if setup.c or other file need compiles never changed):
    Assumming you have gcc installed
    
    run in cmd: 
    (add debug to show what command is running, or change to uptade in position of all, which delete all existing file and compile again)
    mingw32-make -C path\to\ACEX_project\WEB\config all
    or: 
    cd path/to/this/folder   +    mingw32-make all