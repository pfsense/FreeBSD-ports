--- src/base/platform/platform-posix.cc.orig	2021-04-22 15:00:28 UTC
+++ src/base/platform/platform-posix.cc
@@ -478,7 +478,7 @@ bool OS::DiscardSystemPages(void* address, size_t size
 
 // static
 bool OS::HasLazyCommits() {
-#if V8_OS_AIX || V8_OS_LINUX || V8_OS_MACOSX
+#if V8_OS_AIX || V8_OS_LINUX || V8_OS_MACOSX || V8_OS_FREEBSD
   return true;
 #else
   // TODO(bbudge) Return true for all POSIX platforms.
@@ -611,6 +611,12 @@ int OS::GetCurrentThreadId() {
   return static_cast<int>(syscall(__NR_gettid));
 #elif V8_OS_ANDROID
   return static_cast<int>(gettid());
+#elif V8_OS_DRAGONFLYBSD || defined(__DragonFly__)
+  return static_cast<int>(lwp_gettid());
+#elif V8_OS_FREEBSD
+  return static_cast<int>(pthread_getthreadid_np());
+#elif V8_OS_NETBSD
+  return static_cast<int>(_lwp_self());
 #elif V8_OS_AIX
   return static_cast<int>(thread_self());
 #elif V8_OS_FUCHSIA
