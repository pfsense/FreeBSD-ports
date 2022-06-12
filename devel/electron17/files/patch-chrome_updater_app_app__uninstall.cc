--- chrome/updater/app/app_uninstall.cc.orig	2022-05-11 07:16:49 UTC
+++ chrome/updater/app/app_uninstall.cc
@@ -31,7 +31,7 @@
 #include "chrome/updater/win/setup/uninstall.h"
 #elif defined(OS_MAC)
 #include "chrome/updater/mac/setup/setup.h"
-#elif defined(OS_LINUX)
+#elif defined(OS_LINUX) || defined(OS_BSD)
 #include "chrome/updater/linux/setup/setup.h"
 #endif
 
