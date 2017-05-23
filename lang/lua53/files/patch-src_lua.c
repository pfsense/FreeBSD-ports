--- src/lua.c.orig	2017-05-22 18:58:08 UTC
+++ src/lua.c
@@ -9,10 +9,10 @@
 #include "lprefix.h"
 
 
+#include <string.h>
 #include <signal.h>
 #include <stdio.h>
 #include <stdlib.h>
-#include <string.h>
 
 #include "lua.h"
 
