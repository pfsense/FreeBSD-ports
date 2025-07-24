--- base/message_loop/message_pump_glib.cc.orig	2025-04-15 08:30:07 UTC
+++ base/message_loop/message_pump_glib.cc
@@ -8,6 +8,11 @@
 #include <glib.h>
 #include <math.h>
 
+#if BUILDFLAG(IS_BSD)
+#include <pthread.h>
+#include <pthread_np.h>
+#endif
+
 #include "base/logging.h"
 #include "base/memory/raw_ptr.h"
 #include "base/notreached.h"
@@ -52,9 +57,13 @@ int GetTimeIntervalMilliseconds(TimeTicks next_task_ti
 }
 
 bool RunningOnMainThread() {
+#if BUILDFLAG(IS_BSD)
+  return pthread_main_np();
+#else
   auto pid = getpid();
   auto tid = PlatformThread::CurrentId().raw();
   return pid > 0 && tid > 0 && pid == tid;
+#endif
 }
 
 // A brief refresher on GLib:
