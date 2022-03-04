--- make/common.mak.orig	2013-08-14 23:20:27 UTC
+++ make/common.mak
@@ -370,20 +370,20 @@ ifdef VERSION_FILE
 # If not specified, find the various version components in the VERSION_FILE
 
   ifndef MAJOR_VERSION
-    MAJOR_VERSION:=$(strip $(subst \#define,, $(subst $(MAJOR_VERSION_DEFINE),,\
+    MAJOR_VERSION:=$(strip $(subst #define,, $(subst $(MAJOR_VERSION_DEFINE),,\
                    $(shell grep "define *$(MAJOR_VERSION_DEFINE) *" $(VERSION_FILE)))))
   endif
   ifndef MINOR_VERSION
-    MINOR_VERSION:=$(strip $(subst \#define,, $(subst $(MINOR_VERSION_DEFINE),,\
+    MINOR_VERSION:=$(strip $(subst #define,, $(subst $(MINOR_VERSION_DEFINE),,\
                    $(shell grep "define *$(MINOR_VERSION_DEFINE)" $(VERSION_FILE)))))
   endif
   ifndef BUILD_TYPE
-    BUILD_TYPE:=$(strip $(subst \#define,,$(subst BUILD_TYPE,,\
+    BUILD_TYPE:=$(strip $(subst #define,,$(subst BUILD_TYPE,,\
                 $(subst AlphaCode,alpha,$(subst BetaCode,beta,$(subst ReleaseCode,.,\
                 $(shell grep "define *BUILD_TYPE" $(VERSION_FILE))))))))
   endif
   ifndef BUILD_NUMBER
-    BUILD_NUMBER:=$(strip $(subst \#define,,$(subst $(BUILD_NUMBER_DEFINE),,\
+    BUILD_NUMBER:=$(strip $(subst #define,,$(subst $(BUILD_NUMBER_DEFINE),,\
                   $(shell grep "define *$(BUILD_NUMBER_DEFINE)" $(VERSION_FILE)))))
   endif
 
