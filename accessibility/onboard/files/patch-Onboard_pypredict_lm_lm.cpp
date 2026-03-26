--- Onboard/pypredict/lm/lm.cpp.orig	2025-07-03 16:13:44 UTC
+++ Onboard/pypredict/lm/lm.cpp
@@ -19,7 +19,7 @@
 
 #include <stdlib.h>
 #include <stdio.h>
-#include <error.h>
+#include <err.h>
 #include <algorithm>
 #include <cmath>
 #include <string>
@@ -37,7 +37,7 @@ StrConv::StrConv()
     if (cd_mb_wc == (iconv_t) -1)
     {
         if (errno == EINVAL)
-            error (0, 0, "conversion from UTF-8 to wchar_t not available");
+            err (0, 0, "conversion from UTF-8 to wchar_t not available");
         else
             perror ("iconv_open mb2wc");
     }
@@ -45,7 +45,7 @@ StrConv::StrConv()
     if (cd_wc_mb == (iconv_t) -1)
     {
         if (errno == EINVAL)
-            error (0, 0, "conversion from wchar_t to UTF-8 not available");
+            err (0, 0, "conversion from wchar_t to UTF-8 not available");
         else
             perror ("iconv_open wc2mb");
     }
