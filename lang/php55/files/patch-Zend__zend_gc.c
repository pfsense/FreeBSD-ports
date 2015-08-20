--- Zend/zend_gc.c.orig	2014-04-01 20:10:41.000000000 -0300
+++ Zend/zend_gc.c	2014-04-01 20:11:01.000000000 -0300
@@ -22,7 +22,7 @@
 #include "zend.h"
 #include "zend_API.h"
 
-#define GC_ROOT_BUFFER_MAX_ENTRIES 10000
+#define GC_ROOT_BUFFER_MAX_ENTRIES 1000
 
 #ifdef ZTS
 ZEND_API int gc_globals_id;
