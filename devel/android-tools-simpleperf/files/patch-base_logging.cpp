--- base/logging.cpp.orig	2016-08-08 21:10:17 UTC
+++ base/logging.cpp
@@ -25,7 +25,7 @@
 #include <time.h>
 
 // For getprogname(3) or program_invocation_short_name.
-#if defined(__ANDROID__) || defined(__APPLE__)
+#if !defined(_WIN32) && !defined(__GLIBC__)
 #include <stdlib.h>
 #elif defined(__GLIBC__)
 #include <errno.h>
@@ -35,6 +35,8 @@
 #include <sys/uio.h>
 #endif
 
+#include <cstring> // strrchr
+#include <cstdio>  // fprintf
 #include <iostream>
 #include <limits>
 #include <sstream>
@@ -71,6 +72,16 @@
 #include <unistd.h>
 #elif defined(_WIN32)
 #include <windows.h>
+#elif defined(__DragonFly__) || defined(__FreeBSD__)
+#include <pthread_np.h>
+#elif defined(__NetBSD__)
+#include <lwp.h>
+#elif defined(__OpenBSD__)
+#include <unistd.h>
+#elif defined(__sun)
+#include <thread.h>
+#else // fallback
+#include <stdint.h>
 #endif
 
 #if defined(_WIN32)
@@ -88,6 +97,16 @@ static thread_id GetThreadId() {
   return syscall(__NR_gettid);
 #elif defined(_WIN32)
   return GetCurrentThreadId();
+#elif defined(__DragonFly__) || defined(__FreeBSD__)
+  return pthread_getthreadid_np();
+#elif defined(__NetBSD__)
+  return _lwp_self();
+#elif defined(__OpenBSD__)
+  return getthrid();
+#elif defined(__sun)
+  return thr_self();
+#else // fallback
+  return (intptr_t) pthread_self();
 #endif
 }
 
