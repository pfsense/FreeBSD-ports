--- src/3rdparty/chromium/components/url_matcher/regex_set_matcher.cc.orig	2018-11-13 18:25:11 UTC
+++ src/3rdparty/chromium/components/url_matcher/regex_set_matcher.cc
@@ -11,8 +11,13 @@
 #include "base/logging.h"
 #include "base/strings/string_util.h"
 #include "components/url_matcher/substring_set_matcher.h"
+#if defined(OS_BSD)
+#include <re2/filtered_re2.h>
+#include <re2/re2.h>
+#else
 #include "third_party/re2/src/re2/filtered_re2.h"
 #include "third_party/re2/src/re2/re2.h"
+#endif
 
 namespace url_matcher {
 
