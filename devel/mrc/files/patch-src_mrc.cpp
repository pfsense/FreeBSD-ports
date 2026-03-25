-- https://github.com/mhekkel/mrc/issues/18

--- src/mrc.cpp.orig	2026-03-22 04:35:33 UTC
+++ src/mrc.cpp
@@ -47,6 +47,7 @@
 #include <fcntl.h>
 #include <sys/stat.h>
 #include <sys/types.h>
+#include <unistd.h>
 
 #include <mcfp/mcfp.hpp>
 
