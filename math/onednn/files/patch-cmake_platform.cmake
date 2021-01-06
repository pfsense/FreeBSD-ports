--- cmake/platform.cmake.orig	2020-10-28 05:50:44 UTC
+++ cmake/platform.cmake
@@ -104,22 +104,22 @@ elseif(UNIX OR MINGW)
         if(DNNL_TARGET_ARCH STREQUAL "AARCH64")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-mcpu=native")
              endif()
         elseif(DNNL_TARGET_ARCH STREQUAL "PPC64")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-mcpu=native")
              endif()
         elseif(DNNL_TARGET_ARCH STREQUAL "S390X")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-march=native")
              endif()
-        elseif(DNNL_TARGET_ARCH STREQUAL "X64")
+        elseif(FALSE AND DNNL_TARGET_ARCH STREQUAL "X64")
              set(DEF_ARCH_OPT_FLAGS "-msse4.1")
         endif()
         # Clang cannot vectorize some loops with #pragma omp simd and gets
@@ -186,24 +186,24 @@ elseif(UNIX OR MINGW)
         if(DNNL_TARGET_ARCH STREQUAL "AARCH64")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-mcpu=native")
              endif()
         elseif(DNNL_TARGET_ARCH STREQUAL "PPC64")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # In GCC, -ftree-vectorize is turned on under -O3 since 2007.
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-mcpu=native")
              endif()
         elseif(DNNL_TARGET_ARCH STREQUAL "S390X")
              set(DEF_ARCH_OPT_FLAGS "-O3")
              # In GCC, -ftree-vectorize is turned on under -O3 since 2007.
              # For native compilation tune for the host processor
-             if (CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
+             if (FALSE AND CMAKE_SYSTEM_PROCESSOR STREQUAL CMAKE_HOST_SYSTEM_PROCESSOR)
                  append(DEF_ARCH_OPT_FLAGS "-march=native")
              endif()
-        elseif(DNNL_TARGET_ARCH STREQUAL "X64")
+        elseif(FALSE AND DNNL_TARGET_ARCH STREQUAL "X64")
              set(DEF_ARCH_OPT_FLAGS "-msse4.1")
         endif()
         # suppress warning on assumptions made regarding overflow (#146)
