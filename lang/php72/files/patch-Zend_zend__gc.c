--- Zend/zend_gc.c.orig	2018-04-23 16:59:00 UTC
+++ Zend/zend_gc.c
@@ -73,7 +73,7 @@
 #include "zend_API.h"
 
 /* one (0) is reserved */
-#define GC_ROOT_BUFFER_MAX_ENTRIES 10001
+#define GC_ROOT_BUFFER_MAX_ENTRIES 1000
 
 #define GC_HAS_DESTRUCTORS  (1<<0)
 
