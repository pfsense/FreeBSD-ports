--- src/3rdparty/chromium/third_party/pdfium/third_party/base/allocator/partition_allocator/page_allocator_internals_posix.h.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/third_party/pdfium/third_party/base/allocator/partition_allocator/page_allocator_internals_posix.h
@@ -16,7 +16,7 @@
 #if defined(OS_ANDROID)
 #include <sys/prctl.h>
 #endif
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 #include <sys/resource.h>
 
 #include <algorithm>
