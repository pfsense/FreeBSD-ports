--- base/allocator/allocator_shim_override_libc_symbols.h.orig	2019-09-10 10:42:26 UTC
+++ base/allocator/allocator_shim_override_libc_symbols.h
@@ -10,7 +10,7 @@
 #endif
 #define BASE_ALLOCATOR_ALLOCATOR_SHIM_OVERRIDE_LIBC_SYMBOLS_H_
 
-#include <malloc.h>
+#include <stdlib.h>
 
 #include "base/allocator/allocator_shim_internals.h"
 
