--- cmake/Macros/PySideModules.cmake.orig	2019-03-21 14:37:54 UTC
+++ cmake/Macros/PySideModules.cmake
@@ -124,6 +124,7 @@ macro(create_pyside_module)
                         COMMAND Shiboken2::shiboken2 ${GENERATOR_EXTRA_FLAGS}
                         "${pyside2_BINARY_DIR}/${module_NAME}_global.h"
                         --include-paths=${shiboken_include_dirs}
+                        --include-paths="${CMAKE_INSTALL_PREFIX}/include"
                         ${shiboken_framework_include_dirs_option}
                         --typesystem-paths=${pyside_binary_dir}${PATH_SEP}${pyside2_SOURCE_DIR}${PATH_SEP}${${module_TYPESYSTEM_PATH}}
                         --output-directory=${CMAKE_CURRENT_BINARY_DIR}
