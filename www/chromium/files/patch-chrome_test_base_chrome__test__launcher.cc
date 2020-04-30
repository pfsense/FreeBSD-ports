--- chrome/test/base/chrome_test_launcher.cc.orig	2020-04-06 07:46:40 UTC
+++ chrome/test/base/chrome_test_launcher.cc
@@ -59,7 +59,7 @@
 #endif
 
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 #include "chrome/browser/first_run/scoped_relaunch_chrome_browser_override.h"
 #include "testing/gtest/include/gtest/gtest.h"
 #endif
@@ -221,7 +221,7 @@ int LaunchChromeTests(size_t parallel_jobs,
   }
 
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   // Cause a test failure for any test that triggers an unexpected relaunch.
   // Tests that fail here should likely be restructured to put the "before
   // relaunch" code into a PRE_ test with its own
