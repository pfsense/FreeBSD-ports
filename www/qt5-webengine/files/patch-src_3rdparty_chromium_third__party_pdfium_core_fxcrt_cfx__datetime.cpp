--- src/3rdparty/chromium/third_party/pdfium/core/fxcrt/cfx_datetime.cpp.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/third_party/pdfium/core/fxcrt/cfx_datetime.cpp
@@ -9,7 +9,7 @@
 #include "build/build_config.h"
 #include "core/fxcrt/fx_system.h"
 
-#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || \
+#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD) || \
     defined(OS_APPLE) || defined(OS_ASMJS)
 #include <sys/time.h>
 #include <time.h>
