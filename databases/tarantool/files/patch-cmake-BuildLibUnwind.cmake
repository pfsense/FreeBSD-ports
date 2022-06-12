--- cmake/BuildLibUnwind.cmake.orig	2022-05-21 03:40:03.175123000 +0300
+++ cmake/BuildLibUnwind.cmake	2022-05-21 03:40:11.327052000 +0300
@@ -18,6 +18,11 @@
   The paths to the libunwind libraries.
 #]========================================================================]
 
+set(SYSTEM_ARCH ${CMAKE_SYSTEM_PROCESSOR})
+if(CMAKE_SYSTEM_NAME STREQUAL FreeBSD AND SYSTEM_ARCH STREQUAL amd64)
+	set(SYSTEM_ARCH x86_64)
+endif()
+
 macro(libunwind_build)
     set(LIBUNWIND_SOURCE_DIR ${PROJECT_SOURCE_DIR}/third_party/libunwind)
     set(LIBUNWIND_BUILD_DIR ${PROJECT_BINARY_DIR}/build/libunwind)
@@ -81,12 +86,12 @@
     add_library(bundled-libunwind-platform STATIC IMPORTED GLOBAL)
     set_target_properties(bundled-libunwind-platform PROPERTIES
                           IMPORTED_LOCATION
-                          ${LIBUNWIND_INSTALL_DIR}/lib/libunwind-${CMAKE_SYSTEM_PROCESSOR}.a)
+			  ${LIBUNWIND_INSTALL_DIR}/lib/libunwind-${SYSTEM_ARCH}.a)
     add_dependencies(bundled-libunwind-platform bundled-libunwind-project)
 
     set(LIBUNWIND_INCLUDE_DIR ${LIBUNWIND_INSTALL_DIR}/include)
     set(LIBUNWIND_LIBRARIES
-        ${LIBUNWIND_INSTALL_DIR}/lib/libunwind-${CMAKE_SYSTEM_PROCESSOR}.a
+	    ${LIBUNWIND_INSTALL_DIR}/lib/libunwind-${SYSTEM_ARCH}.a
         ${LIBUNWIND_INSTALL_DIR}/lib/libunwind.a)
 
     message(STATUS "Using bundled libunwind")
