# compiling setup.c also others .c/.d/.lib/.dll files if necessary
# if it need link or something, this won't work for now, you need to add more rules and variables to specify the link or something


#-----------------info-----------------

# EXE_id[n]_NAME : final exe name with id [n]
# EXE_id[n]_CFILE : name of .c file generating final .exe with id [n]
# EXE_id[n]_LIBS_IDS : contain all library(.so/.dll) for exe with id [n]

# LIB_id[n]_NAME : final dll name  with id [n]
# LIB_id[n]_CFILE : name of .c file generating final .dll/.so with id [n]

# example:
# EXE_id1_NAME <- EXE_id1_CFILE 
# EXE_id1_NAME <- EXE_id1_LIBS_IDS <- LIB_id1_NAME <- LIB_id1_CFILE



#------------------variables-----------------

#======================================================================================

# Hiding logic
# if debug is not in the goals, then hide the commands and info, otherwise show them
# DISPLAY will prevent function to run
# Q mean quiet mode, hides function syntax
# when var = ... inside ifeq, define or some similar, it will be treated as function
ifneq ($(filter debug,$(MAKECMDGOALS)),debug)
	Q = @
	# normally will print info about entering/exiting folder, this disables it
	MAKEFLAGS += --no-print-directory
	DISPLAY = 
else
	Q = 
	DISPLAY = #
endif


#windows
ifeq ($(OS),Windows_NT)
    #/Q is quiet, /F is force
	
	RM_CMD = del /Q /F

    # Convert forward slashes to backslashes for the 'del' command
    FIX_PATH = $(subst /,\,$(1))
	SHELL := cmd.exe

	#-----style--------
	PIPE_L = ^|
	RIGHT = 
	LEFT =

	# 1. check if exist by using cmd, if so, print info and delete it, otherwise do nothing
	define Clean_template
		$(Q)if exist $(call FIX_PATH,$(1)) (echo ^|  ^|__ Removing $(1)... && del /Q /F $(call FIX_PATH,$(1)) 2>nul) &
    endef
else
	# unix-like system, use rm command
    RM_CMD = rm -f

	#inside ifeq, this is indenfied as function
	# $(1) will take first arg and do nothing in case(default in code is /)
    FIX_PATH = $(1)

	#-----style--------
	PIPE_L = "|
	RIGHT = "
	LEFT = "

	# 1. check if exist by using cmd, if so, print info and delete it, otherwise do nothing
	define Clean_template
	# -f check if file exist. you need separate [ and ] with whitespace. if statement need be closed by fi
		@if [ -f $(1) ];then \
		$(DISPLAY)@echo "|  |__ Removing $(1)...";\
		$(Q)rm -f $(call FIX_PATH,$(1));\
		fi
	endef
endif

#--------------------------------------------------------------------------------------------------------------------------------------



#-----------Folder mappings--------------
#where to put the .exe
TARGETS_DIRS = $(call FIX_PATH,.)

#shortcut for .c codes
SRCDIRS = $(call FIX_PATH,./src)


# location for .lib
LIB_FINAL_LOCATION = $(call FIX_PATH,./bin)
LIB_SOURCE_LOCATION = $(call FIX_PATH,./lib)

OFILE_FINAL_LOCATION = $(call FIX_PATH,./build)
OFILE_SOURCE_LOCATION = $(call FIX_PATH,./lib)

# for .d
DFILES_FINAL_LOCATION = $(call FIX_PATH,./build)
Include_Dirs = $(call FIX_PATH,./include)



#-----------Flags---------------------

# -L find where .a/.lib is. $(foreach i,$(Include_Dirs),-I$(i)) -> search in all include folder
# example: -I./include -I./include/anything -wall -Wextra -L.
CFLAGS = $(foreach i,$(Include_Dirs),-I$(i)) -O3 -Wall -Wextra -Wpedantic -Wconversion -Wsign-conversion -Wshadow -L$(LIB_FINAL_LOCATION)

# example: -shared --out-implib=./libENV_CONFIG.lib
DLLFLAGS = -shared -Wl,--out-implib=$(call FIX_PATH,$(LIB_FINAL_LOCATION)/lib)

#generate current .dll's .d
HCHECK = -MD -MP -MF $(call FIX_PATH,$(DFILES_FINAL_LOCATION)/)


#=============================================================File mappings===================================================================================#
# call will evalute $(any)

#====================================LIB==========================================
# LIB NAME: lib id[x] has name
LIB_id1_NAME = error
LIB_id2_NAME = ACEX_normalize
LIB_id3_NAME = ENV_CONFIG
LIB_id4_NAME = ENV_read
LIB_id5_NAME = ENV_storage
LIB_id6_NAME = ENV_UI
LIB_id7_NAME = ENV_write



#-------------------------------LIB depencity--------------------------------------------
LIB_id1_LIBS_IDS =
LIB_id2_LIBS_IDS = 1
LIB_id3_LIBS_IDS = 1
LIB_id4_LIBS_IDS = 1 2 3
LIB_id5_LIBS_IDS = 1 2 3
LIB_id6_LIBS_IDS = 1 2 3 4 5
LIB_id7_LIBS_IDS = 1 2 3


#------------------------------OFILE ------------------------------------------
OFILE_id1_NAME = logic_main

#------------------------------OFILE depencity--------------------------------------------
OFILE_id1_LIBS_IDS = 1 2 3 4 5 6 7


#==============================EXE================================================

# ----------------------------------NAME------------------------------------------

EXE_id1_NAME = $(call FIX_PATH,./setup.exe)

# ---------------------------------CFILE------------------------------------------

EXE_id1_CFILE = $(call FIX_PATH,$(SRCDIRS)/setup.c)


#---------------------------------OFILE LIST------------------------------------------
EXE_id1_OFILE_IDS = 1


# ---------------------------------LIB LIST---------------------------------------
EXE_id1_LIBS_IDS = 1 2 3 4 5 6 7


#------------------------------shortcut-------------------------------------------
# all id exist for exe(must exist in )
ALL_EXE_IDS = 1
# loop through all id and get their executable name by change variable name(sufix id to desired id) and grab their real path
ALL_EXECUTABLES = $(foreach exeid,$(ALL_EXE_IDS),$(EXE_id$(exeid)_NAME))

ALL_OFILE_IDS = 1
ALL_OFILES = $(foreach ofileid,$(ALL_OFILE_IDS),$(call FIX_PATH,$(OFILE_FINAL_LOCATION)/$(OFILE_id$(ofileid)_NAME).o))

ALL_LIB_IDS = 1 2 3 4 5 6 7
ALL_LIB = $(foreach libid,$(ALL_LIB_IDS),$(call FIX_PATH,$(TARGETS_DIRS)/$(LIB_id$(libid)_NAME).dll))
#=================================================================================















#===============================================================defines===========================================================
define all_OFILES
	$(foreach id,$(EXE_id$(1)_OFILE_IDS),$(call FIX_PATH,$(OFILE_FINAL_LOCATION)/$(OFILE_id$(id)_NAME).o))
endef

define all_LIBS
	$(foreach id,$(EXE_id$(1)_LIBS_IDS),$(call FIX_PATH,$(TARGETS_DIRS)/$(LIB_id$(id)_NAME).dll))
endef

define OFILE_all_LIBS
	$(foreach id,$(OFILE_id$(1)_LIBS_IDS),$(call FIX_PATH,$(TARGETS_DIRS)/$(LIB_id$(id)_NAME).dll))
endef

define LIB_all_LIBS
	$(foreach id,$(LIB_id$(1)_LIBS_IDS),$(call FIX_PATH,$(TARGETS_DIRS)/$(LIB_id$(id)_NAME).dll))
endef


# template to automae general compile rule for each .exe
# id[n].exe : id[n].c list_id[n].dll
define COMPILE_template
# change variable name's id to find what .exe it is, and grab .c file with same id.
# depencity from variable with same id and prefix EXEDEP, grab this value and find real name of that .c file in var with prefix CFILES 
# second part
# third part from depencity: loop through list(EXE_id[n]_LIBS_IDS) and grab its name
$(EXE_id$(1)_NAME) : $(EXE_id$(1)_CFILE) $(call all_OFILES,$(1)) $(call all_LIBS,$(1))
	$(DISPLAY)@echo $(PIPE_L)  $(PIPE_L)__ Compiling $(EXE_id$(1)_NAME)...$(RIGHT)
	$(Q)gcc $(EXE_id$(1)_CFILE) $(CFLAGS) $(HCHECK)$(basename $(EXE_id$(1)_NAME)).d -L$(TARGETS_DIRS) $(call all_OFILES,$(1)) -o $$@ $(foreach id,$(EXE_id$(1)_LIBS_IDS),-l$(LIB_id$(id)_NAME)) -mconsole
endef

# Create a template for libraries
# gcc .c cflags hcheck -share --out-implib=./lib -l -l -l -o .dll
define LIB_template
$(call FIX_PATH,$(TARGETS_DIRS)/$(LIB_id$(1)_NAME).dll) : $(call FIX_PATH,$(LIB_SOURCE_LOCATION)/$(LIB_id$(1)_NAME).c) $(call LIB_all_LIBS,$(1))
	$(DISPLAY)@echo $(PIPE_L)  $(PIPE_L)__ Compiling library $(LIB_id$(1)_NAME).dll...$(RIGHT)
	$(Q)gcc $$< $(CFLAGS) $(HCHECK)$(LIB_id$(1)_NAME).d $(DLLFLAGS)$(LIB_id$(1)_NAME).lib -L$(TARGETS_DIRS) $(foreach id,$(LIB_id$(1)_LIBS_IDS),-l$(LIB_id$(id)_NAME)) -o $$@
endef

define OFILE_template
$(call FIX_PATH,$(OFILE_FINAL_LOCATION)/$(OFILE_id$(1)_NAME).o) : $(call FIX_PATH,$(OFILE_SOURCE_LOCATION)/$(OFILE_id$(1)_NAME).c) $(call OFILE_all_LIBS,$(1))
	$(DISPLAY)@echo $(PIPE_L)  $(PIPE_L)__ Compiling object file $(OFILE_id$(1)_NAME).o...$(RIGHT)
	$(Q)gcc -c $$< $(CFLAGS) $(HCHECK)$(OFILE_id$(1)_NAME).d -o $$@
endef

#===============================================================================================================================

# --- RULES ---
# Execute the template generation by looping all id exist	
$(foreach exeid,$(ALL_EXE_IDS),$(eval $(call COMPILE_template,$(exeid))))


# Evaluate the template for all libraries
$(foreach libid,$(ALL_LIB_IDS),$(eval $(call LIB_template,$(libid))))

# create ofiles
$(foreach ofileid,$(ALL_OFILE_IDS),$(eval $(call OFILE_template,$(ofileid))))



# compile all exe
all : msg_start_compile $(ALL_EXECUTABLES) $(ALL_OFILES)
	@echo $(PIPE_L) All executables compiled successfully!$(RIGHT)

msg_start_compile:
	@echo $(LEFT)Compiling...$(RIGHT)

#show all debug info like what syntax is actually running, what file is being deleted, etc
debug :
	@echo $(LEFT)debug mode enabled!$(RIGHT)









# delete all exe
clean:
	@echo $(LEFT)Cleaning up...$(RIGHT)

	@echo $(PIPE_L)deleting exe...$(RIGHT)
	$(foreach exe,$(ALL_EXECUTABLES),$(call Clean_template,$(exe)))

	@echo $(PIPE_L)deleting object files...$(RIGHT)
	$(foreach ofile,$(ALL_OFILES),$(call Clean_template,$(ofile)))

	@echo $(PIPE_L)deleting dll...$(RIGHT)
	$(foreach lib,$(ALL_LIB),$(call Clean_template,$(lib)))

	@echo $(PIPE_L) Removing .d files...$(RIGHT)
	$(Q)$(RM_CMD) $(call FIX_PATH,$(DFILES_FINAL_LOCATION)/*.d)


	@echo $(PIPE_L) removing .lib files...$(RIGHT)
	$(Q)$(RM_CMD) $(call FIX_PATH,$(LIB_FINAL_LOCATION)/*.lib)
	
	@echo $(PIPE_L) Cleaned up successfully!$(RIGHT)

# delete all exe and compile again, this is useful when you want to make sure everything is up to date, or you want to recompile with different flags, etc
update :
	@echo $(LEFT) Updating...$(RIGHT)
	$(Q)$(MAKE) clean
	$(Q)$(MAKE) all
	@echo $(LEFT)Update successfully!$(RIGHT)

-include $(wildcard $(DFILES_FINAL_LOCATION)/*.d)