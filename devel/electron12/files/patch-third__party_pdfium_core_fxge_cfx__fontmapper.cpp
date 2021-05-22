--- third_party/pdfium/core/fxge/cfx_fontmapper.cpp.orig	2021-01-07 00:39:34 UTC
+++ third_party/pdfium/core/fxge/cfx_fontmapper.cpp
@@ -154,13 +154,13 @@ const AltFontFamily g_AltFontFamilies[] = {
     {"ForteMT", "Forte"},
 };
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_ASMJS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_ASMJS) || defined(OS_BSD)
 const char kNarrowFamily[] = "LiberationSansNarrow";
 #elif defined(OS_ANDROID)
 const char kNarrowFamily[] = "RobotoCondensed";
 #else
 const char kNarrowFamily[] = "ArialNarrow";
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_ASMJS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_ASMJS) || defined(OS_BSD)
 
 ByteString TT_NormalizeName(const char* family) {
   ByteString norm(family);
