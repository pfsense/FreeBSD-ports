--- src/3rdparty/chromium/extensions/common/stack_frame.cc.orig	2018-11-13 18:25:11 UTC
+++ src/3rdparty/chromium/extensions/common/stack_frame.cc
@@ -7,7 +7,11 @@
 #include <string>
 
 #include "base/strings/utf_string_conversions.h"
+#if defined(OS_BSD)
+#include <re2/re2.h>
+#else
 #include "third_party/re2/src/re2/re2.h"
+#endif
 
 namespace extensions {
 
