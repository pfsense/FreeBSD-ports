--- radlib_compat.h.orig	2025-11-11 17:22:09 UTC
+++ radlib_compat.h
@@ -39,7 +39,11 @@ any other GPL-like (LGPL, GPL2) License.
 #endif
 
 #include "php.h"
+#if PHP_VERSION_ID >= 80400
+#include "ext/random/php_random.h"
+#else
 #include "ext/standard/php_rand.h"
+#endif
 #include "ext/standard/php_standard.h"
 
 #define MPPE_KEY_LEN    16
