--- base/test/launcher/test_launcher.cc.orig	2023-07-24 14:27:53 UTC
+++ base/test/launcher/test_launcher.cc
@@ -69,6 +69,7 @@
 #include "third_party/libxml/chromium/libxml_utils.h"
 
 #if BUILDFLAG(IS_POSIX)
+#include <signal.h>
 #include <fcntl.h>
 
 #include "base/files/file_descriptor_watcher_posix.h"
