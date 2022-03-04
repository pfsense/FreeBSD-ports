--- src/3rdparty/chromium/base/sampling_heap_profiler/sampling_heap_profiler.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/base/sampling_heap_profiler/sampling_heap_profiler.cc
@@ -29,6 +29,10 @@
 #include <sys/prctl.h>
 #endif
 
+#if defined(OS_BSD)
+#include <pthread_np.h>
+#endif
+
 #if defined(OS_ANDROID) && BUILDFLAG(CAN_UNWIND_WITH_CFI_TABLE) && \
     defined(OFFICIAL_BUILD)
 #include "base/trace_event/cfi_backtrace_android.h"
@@ -64,6 +68,10 @@ const char* GetAndLeakThreadName() {
 #elif defined(OS_MACOSX)
   int err = pthread_getname_np(pthread_self(), name, kBufferLen);
   if (err == 0 && *name != '\0')
+    return strdup(name);
+#elif defined(OS_BSD) && __FreeBSD__ >= 12
+  pthread_get_name_np(pthread_self(), name, kBufferLen);
+  if (*name != '\0')
     return strdup(name);
 #endif  // defined(OS_LINUX) || defined(OS_ANDROID)
 
