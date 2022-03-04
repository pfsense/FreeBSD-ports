--- extensions/common/stack_frame.cc.orig	2021-05-12 22:05:54 UTC
+++ extensions/common/stack_frame.cc
@@ -8,7 +8,11 @@
 #include <string>
 
 #include "base/strings/utf_string_conversions.h"
+#if defined(OS_BSD)
+#include <re2/re2.h>
+#else
 #include "third_party/re2/src/re2/re2.h"
+#endif
 
 namespace extensions {
 
