--- chrome/updater/updater.cc.orig	2022-04-01 07:48:30 UTC
+++ chrome/updater/updater.cc
@@ -41,7 +41,7 @@
 #include "chrome/updater/win/win_util.h"
 #elif BUILDFLAG(IS_MAC)
 #include "chrome/updater/app/server/mac/server.h"
-#elif BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "chrome/updater/app/server/linux/server.h"
 #endif
 
