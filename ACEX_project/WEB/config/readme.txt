Project Setup & Configuration Guide
    This automation tool streamlines the installation of project dependencies, handles initial server configuration, 
    runs initialization scripts (such as database structural setups), and generates local environment baselines.

1. Directory Purpose & Automation Overview
    When executed, setup.exe automatically processes the environment setup relative to the project root directory.

    Automated Steps:
    Generates a .env file in the project root to store shared configuration settings and sensitive credentials (e.g., database keys, application tokens).

    Generates a vendor/ directory in the project root and automatically installs all required third-party project dependencies.

    Technical References:
    Environment Parsing: To learn how the application interacts with the generated environment variables, review the vlucas/phpdotenv Repository. (https://github.com/vlucas/phpdotenv)

    Package Management: To add new project dependencies, update your configurations inside composer.json. See the Composer Basic Usage Guide for details.(https://getcomposer.org/doc/01-basic-usage.md)

    !!! CRITICAL DEVELOPMENT RULE:
    Do not modify local .env values or local environment state directly when creating new features for future deployment.
    You must mirror those structural changes inside .env.example so other developers can sync their local environments.

    If you are updating or creating database schemas, you must use version-controlled PHP migration scripts. 
    Failure to use explicit database version controls will result in the setup script overwriting existing data tables entirely. 
    List your active database update scripts(consider these scripts start form project root) inside internal.cfg under the file tracker lines using this exact syntax:
            #l: file to ...
            #f:
            v3_name_schema.php

        


2. Usage Instructions
    Navigate to your project root directory and double-click setup.exe.(e.g ACEX_project/WEB/config)

    The interactive terminal interface will ask you to enter specific values for your .env configuration, followed by your internal setup parameters.

    Legacy Data Resolution: If pre-existing configuration data(e.g .env) is detected in the project root, you can choose to either overwrite the entire data or append missing changes.

    In the event of a key conflict between old data and incoming defaults(that cannot be changed), the program will ask you to explicitly select which value to retain before proceeding.

    If has some error see in 6. Troubleshooting Diagnostics and 5. Manual Configuration Fallback.




3. Architecture & File Registry
    Core Environment Core
    .env — Contains specialized system flags and highly sensitive access credentials unique to your machine. 
    Development instances and production servers will use entirely different data blocks for security safety. NEVER commit any file contain .env to GitHub repositories.

    A) The config/ Directory
    Consult the inline documentation inside .env.example or internal.cfg if you need to modify default keys or introduce new structural key-value sets.
    .env.example — The baseline blueprints. All keys defined inside this framework are automatically translated into your newly generated root .env file.
    internal.cfg — Houses runtime directives for setup.exe. Append new execution files inside the defined [FILES] block to declare structural dependencies.

    B) The src/ Directory
    src/database/ — Houses configuration scripts dedicated to constructing databases, establishing user tables, handling access controls, and assigning operational permissions.
    src/server/ — Storage vector for custom core initialization parameters(e.g htacess, php.ini).
    setup.c — The primary source code driving the setup.exe runtime workflow.

    C) System Utilities & Resource Directories
    lib/ — source files for compiling internal .o binary objects and dynamic-link libraries (.dll).

    Include/ — header directory mapping out core .c logic. 
                If you need to scale down parameters to optimize processing workloads (e.g., reducing KEY_SIZE limits inside ENV_const.h), 
                change these constants and recompile the application binary.
                See also in 7.Core Binary Recompilation (For Core Developers)

    makefile — Compilation automation script maps. 
                Running this requires local instances of gcc and make tools. 
                If you are using pre-compiled binaries downloaded directly from GitHub, you can safely ignore this script.

    
    
4. Operational Restrictions
    Portability: This engine has been exhaustively tested exclusively on Windows 10. Unverified environments may produce unexpected errors.
    Path Preservation: If you alter core folder topologies, you must update the location paths in Include/ENV_const.h and push those updates out to the development team.
    System Assets: composer.phar is required for proper operation and must never be deleted from the system matrix. Otherwise you need download it in official site.
    Engine Limits: Only support PHP scripts to communicates natively with MySQL databases.
    Compilation Thresholds: Recompiling the application binary via the makefile is only required if you make structural changes to system source files. 
                            Modifications to files strictly within src/database/, src/server/, or the config/ directory do not require recompilation.



5. Manual Configuration Fallback
    If the automated setup utility fails to generate your working environment files, execute the initialization steps manually using the following procedure:

    Phase I: Replicating Environment Files
        Duplicate .env.example and place the copy directly into the project root directory.
        Rename the duplicate file to .env (or create a distinct target context file, such as .env.production).
        Open the file and customize the configuration variables manually.

    Phase II: Manual Dependency Management
        Ensure composer.phar is accessible. If missing, retrieve it from the Official Composer Download Portal.(https://getcomposer.org/download/)
        Fire up your terminal window and navigate to your project root directory where composer.phar lives:

        cd path/to/project/root
        Check for an existing composer.lock file. If it does not exist, run a global update:

        php composer.phar update
        If a composer.lock file is present, trigger a native deployment sync instead:

        php composer.phar install
        If you encounter lock synchronization alerts stating “Warning: The lock file is not up to date...”, force a complete tree reconciliation:

        php composer.phar update --with-all-dependencies

    Phase III: Database Verification
        Verify that your manual structural scripts do not overwrite live staging tables.
        Manually execute the files declared inside internal.cfg (note: only run missing version of script for database),
        or inspect src/database/ and src/server/ to select files safely.



6. Troubleshooting Diagnostics
    Issue A: SSL Certificate Verification Failure
        System Clock Sync: Verify that your system's date, time, and timezone are correctly synchronized with internet time servers.
        Expired CA Bundles: Update your local security certificate assets manually via cURL CA Extract Documentation. (https://curl.se/docs/caextract.html)
        Antivirus: Security proxy filters can intercept TLS handshakes. 
            Temporarily deactivate local firewalls or antivirus software to narrow down the issue. Refer to the Composer SSL Troubleshooting Guide for extended details.
            https://getcomposer.org/doc/articles/troubleshooting.md#ssl-certificate-problem-unable-to-get-local-issuer-certificate

    Issue B: Advanced Program Debugging
        To safely debug runtime execution blockages, change the SHOW_DEBUG_INFO variable to "ON".
        If errors persist, switch DISPLAY_ERROR_LINE to "ON" and forward the crash logs directly to the development team.



7. Core Binary Recompilation (For Core Developers)
    To compile or update structural features inside setup.exe using a local MinGW or GCC chain, run your command prompt and execute either command pattern below:


    Method A: Target-driven make execution pointing directly to the config source vector
    mingw32-make -C path\to\ACEX_project\WEB\config update

    Method B: Inline directory translation execution pattern
    cd path/to/this/folder
    mingw32-make update

(Note: To output real-time GCC logs during target construction phases, activate the local debug echo parameters, e.g ...update debug)