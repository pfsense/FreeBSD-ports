--- src/3rdparty/chromium/base/process/process_metrics_posix.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/process/process_metrics_posix.cc
@@ -20,6 +20,8 @@
 
 #if defined(OS_APPLE)
 #include <malloc/malloc.h>
+#elif defined(OS_FREEBSD)
+#include <stdlib.h>
 #else
 #include <malloc.h>
 #endif
@@ -126,7 +128,7 @@ size_t ProcessMetrics::GetMallocUsage() {
 #else
   return minfo.hblkhd + minfo.arena;
 #endif
-#elif defined(OS_FUCHSIA)
+#elif defined(OS_FUCHSIA) || defined(OS_BSD)
   // TODO(fuchsia): Not currently exposed. https://crbug.com/735087.
   return 0;
 #endif
