--- chrome/test/chromedriver/chrome_launcher.cc.orig	2022-05-11 07:16:49 UTC
+++ chrome/test/chromedriver/chrome_launcher.cc
@@ -65,6 +65,7 @@
 #include <fcntl.h>
 #include <sys/stat.h>
 #include <sys/types.h>
+#include <sys/wait.h>
 #include <unistd.h>
 #elif defined(OS_WIN)
 #include "chrome/test/chromedriver/keycode_text_conversion.h"
