--- components/autofill/core/common/autofill_util.cc.orig	2022-05-11 07:16:50 UTC
+++ components/autofill/core/common/autofill_util.cc
@@ -154,7 +154,7 @@ bool SanitizedFieldIsEmpty(const std::u16string& value
 
 bool ShouldAutoselectFirstSuggestionOnArrowDown() {
 #if defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || \
-    defined(OS_CHROMEOS)
+    defined(OS_CHROMEOS) || defined(OS_BSD)
   return true;
 #else
   return false;
