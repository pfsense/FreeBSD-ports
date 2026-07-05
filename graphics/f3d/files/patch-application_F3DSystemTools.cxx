--- application/F3DSystemTools.cxx.orig	2025-07-21 09:00:00 UTC
+++ application/F3DSystemTools.cxx
@@ -15,6 +15,10 @@
 #ifdef __APPLE__
 #include <mach-o/dyld.h>
 #endif
+#ifdef __FreeBSD__
+#include <sys/types.h>
+#include <sys/sysctl.h>
+#endif

 namespace fs = std::filesystem;

@@ -44,7 +48,15 @@ fs::path GetApplicationPath()
 #else
   try
   {
-#if defined(__FreeBSD__)
+#if defined(__FreeBSD__)
+    int mib[4] = { CTL_KERN, KERN_PROC, KERN_PROC_PATHNAME, -1 };
+    char buf[PATH_MAX];
+    size_t len = sizeof(buf);
+    if (sysctl(mib, 4, buf, &len, nullptr, 0) == 0)
+    {
+      return fs::path(buf);
+    }
+    // Fallback to procfs if sysctl fails
     return fs::canonical("/proc/curproc/file");
 #else
     return fs::canonical("/proc/self/exe");
