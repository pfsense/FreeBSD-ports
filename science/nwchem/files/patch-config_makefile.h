--- config/makefile.h.orig	2020-10-08 04:03:38 UTC
+++ config/makefile.h
@@ -1027,9 +1027,9 @@ ifeq ($(TARGET),MACX)
         FDEBUG = -O0 -g
         FOPTIMIZE  = -O2 -ffast-math -Wuninitialized 
         DEFINES  += -DGFORTRAN
-        GNUMAJOR=$(shell $(_FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
+        GNUMAJOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
         ifdef GNUMAJOR
-        GNUMINOR=$(shell $(_FC) -dM -E - < /dev/null 2> /dev/null | egrep __GNUC_MINOR | cut -c24)
+        GNUMINOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | egrep __GNUC_MINOR | cut -c24)
         GNU_GE_4_6 = $(shell [ $(GNUMAJOR) -gt 4 -o \( $(GNUMAJOR) -eq 4 -a $(GNUMINOR) -ge 6 \) ] && echo true)
         GNU_GE_4_8 = $(shell [ $(GNUMAJOR) -gt 4 -o \( $(GNUMAJOR) -eq 4 -a $(GNUMINOR) -ge 8 \) ] && echo true)
         GNU_GE_6 = $(shell [ $(GNUMAJOR) -ge 6  ] && echo true)
@@ -1195,9 +1195,9 @@ endif
 #
          FOPTIMIZE+= -funroll-all-loops -mtune=native 
          FVECTORIZE=-O3 -ffast-math -mtune=native -mfpmath=sse -msse3 -ftree-vectorize -ftree-vectorizer-verbose=1   -fprefetch-loop-arrays  -funroll-all-loops 
-        GNUMAJOR=$(shell $(_FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
+        GNUMAJOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
 	ifneq ($(strip $(GNUMAJOR)),)
-        GNUMINOR=$(shell $(_FC) -dM -E - < /dev/null 2> /dev/null | egrep __GNUC_MINOR | cut -c24)
+        GNUMINOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | egrep __GNUC_MINOR | cut -c24)
         GNU_GE_4_6 = $(shell [ $(GNUMAJOR) -gt 4 -o \( $(GNUMAJOR) -eq 4 -a $(GNUMINOR) -ge 6 \) ] && echo true)
         GNU_GE_4_8 = $(shell [ $(GNUMAJOR) -gt 4 -o \( $(GNUMAJOR) -eq 4 -a $(GNUMINOR) -ge 8 \) ] && echo true)
         GNU_GE_6 = $(shell [ $(GNUMAJOR) -ge 6  ] && echo true)
@@ -1378,7 +1378,7 @@ ifeq ($(TARGET),$(findstring $(TARGET),LINUX CYGNUS CY
         FOPTIONS   = # -Wextra -Wunused  
         FOPTIMIZE  += -ffast-math -Wuninitialized
         DEFINES  += -DGFORTRAN
-        GNUMAJOR=$(shell $(_FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
+        GNUMAJOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | grep __GNUC__ |cut -c18-)
         ifdef GNUMAJOR
           GNUMINOR=$(shell $(FC) -dM -E - < /dev/null 2> /dev/null | egrep __VERS | cut -c24)
           GNU_GE_4_6 = $(shell [ $(GNUMAJOR) -gt 4 -o \( $(GNUMAJOR) -eq 4 -a $(GNUMINOR) -ge 6 \) ] && echo true)
