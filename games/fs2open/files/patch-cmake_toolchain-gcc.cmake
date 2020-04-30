--- cmake/toolchain-gcc.cmake.orig	2020-01-25 16:45:09 UTC
+++ cmake/toolchain-gcc.cmake
@@ -10,8 +10,8 @@ option(GCC_ENABLE_ADDRESS_SANITIZER "Enable -fsanitize
 option(GCC_ENABLE_SANITIZE_UNDEFINED "Enable -fsanitize=undefined" OFF)
 
 # These are the default values
-set(C_BASE_FLAGS "-march=native -pipe")
-set(CXX_BASE_FLAGS "-march=native -pipe")
+set(C_BASE_FLAGS "${CMAKE_C_FLAGS_RELEASE}" )
+set(CXX_BASE_FLAGS "${CMAKE_CXX_FLAGS_RELEASE}")
 
 # For C and C++, the values can be overwritten independently
 if(DEFINED ENV{CFLAGS})
@@ -107,8 +107,6 @@ set(CMAKE_C_FLAGS_RELEASE ${COMPILER_FLAGS_RELEASE})
 
 set(CMAKE_CXX_FLAGS_DEBUG ${COMPILER_FLAGS_DEBUG})
 set(CMAKE_C_FLAGS_DEBUG ${COMPILER_FLAGS_DEBUG})
-
-set(CMAKE_EXE_LINKER_FLAGS "")
 
 IF (MINGW)
 	SET(CMAKE_EXE_LINKER_FLAGS "${CMAKE_EXE_LINKER_FLAGS} -static -static-libgcc -static-libstdc++ -Wl,--enable-auto-import")
