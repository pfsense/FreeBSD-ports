--- src/3rdparty/chromium/third_party/perfetto/include/perfetto/base/thread_utils.h.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/third_party/perfetto/include/perfetto/base/thread_utils.h
@@ -35,6 +35,7 @@ __declspec(dllimport) unsigned long __stdcall GetCurre
 #include <sys/syscall.h>
 #include <sys/types.h>
 #include <unistd.h>
+#include <pthread.h>
 #else
 #include <pthread.h>
 #endif
@@ -46,6 +47,11 @@ inline PlatformThreadId GetThreadId() {
 using PlatformThreadId = pid_t;
 inline PlatformThreadId GetThreadId() {
   return gettid();
+}
+#elif PERFETTO_BUILDFLAG(PERFETTO_OS_BSD)
+using PlatformThreadId = uint64_t;
+inline PlatformThreadId GetThreadId() {
+  return reinterpret_cast<uint64_t>(pthread_self());
 }
 #elif PERFETTO_BUILDFLAG(PERFETTO_OS_LINUX)
 using PlatformThreadId = pid_t;
