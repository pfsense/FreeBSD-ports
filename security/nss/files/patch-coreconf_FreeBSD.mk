--- coreconf/FreeBSD.mk.orig	2016-08-05 15:43:39 UTC
+++ coreconf/FreeBSD.mk
@@ -5,9 +5,9 @@
 
 include $(CORE_DEPTH)/coreconf/UNIX.mk
 
-DEFAULT_COMPILER	= gcc
-CC			= gcc
-CCC			= g++
+DEFAULT_COMPILER	= $(CC)
+CC			?= gcc
+CCC			= $(CXX)
 RANLIB			= ranlib
 
 CPU_ARCH		= $(OS_TEST)
@@ -17,9 +17,19 @@ endif
 ifeq ($(CPU_ARCH),pc98)
 CPU_ARCH		= x86
 endif
+ifeq ($(CPU_ARCH),armv6)
+CPU_ARCH		= arm
+endif
 ifeq ($(CPU_ARCH),amd64)
 CPU_ARCH		= x86_64
 endif
+ifneq (,$(filter powerpc%, $(CPU_ARCH)))
+CPU_ARCH		= ppc
+endif
+
+ifneq (,$(filter %64, $(OS_TEST)))
+USE_64			= 1
+endif
 
 OS_CFLAGS		= $(DSO_CFLAGS) -ansi -Wall -Wno-switch -DFREEBSD -DHAVE_STRERROR -DHAVE_BSD_FLOCK
 
@@ -38,15 +48,15 @@ endif
 
 ARCH			= freebsd
 
-MOZ_OBJFORMAT		:= $(shell test -x /usr/bin/objformat && /usr/bin/objformat || echo elf)
+ifndef MOZILLA_CLIENT
+DLL_SUFFIX		= so.1
+endif
 
-ifeq ($(MOZ_OBJFORMAT),elf)
-DLL_SUFFIX		= so
+ifneq (,$(filter alpha ia64,$(OS_TEST)))
+MKSHLIB			= $(CC) -Wl,-Bsymbolic -lc $(DSO_LDOPTS)
 else
-DLL_SUFFIX		= so.1.0
+MKSHLIB			= $(CC) -Wl,-Bsymbolic $(DSO_LDOPTS)
 endif
-
-MKSHLIB			= $(CC) $(DSO_LDOPTS)
 ifdef MAPFILE
 	MKSHLIB += -Wl,--version-script,$(MAPFILE)
 endif
@@ -55,4 +65,5 @@ PROCESS_MAP_FILE = grep -v ';-' $< | \
 
 G++INCLUDES		= -I/usr/include/g++
 
-INCLUDES		+= -I/usr/X11R6/include
+USE_SYSTEM_ZLIB		= 1
+ZLIB_LIBS		= -lz
