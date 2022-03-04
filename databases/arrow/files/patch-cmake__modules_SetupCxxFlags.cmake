--- cmake_modules/SetupCxxFlags.cmake.orig	2021-04-21 16:14:36 UTC
+++ cmake_modules/SetupCxxFlags.cmake
@@ -28,7 +28,7 @@ if(NOT DEFINED ARROW_CPU_FLAG)
     set(ARROW_CPU_FLAG "armv8")
   elseif(CMAKE_SYSTEM_PROCESSOR MATCHES "armv7")
     set(ARROW_CPU_FLAG "armv7")
-  elseif(CMAKE_SYSTEM_PROCESSOR MATCHES "ppc")
+  elseif(CMAKE_SYSTEM_PROCESSOR MATCHES "powerpc|powerpc64|ppc")
     set(ARROW_CPU_FLAG "ppc")
   elseif(CMAKE_SYSTEM_PROCESSOR MATCHES "s390x")
     set(ARROW_CPU_FLAG "s390x")
