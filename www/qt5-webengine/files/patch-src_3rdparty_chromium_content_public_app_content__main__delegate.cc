--- src/3rdparty/chromium/content/public/app/content_main_delegate.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/content/public/app/content_main_delegate.cc
@@ -24,12 +24,12 @@ int ContentMainDelegate::RunProcess(
   return -1;
 }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 void ContentMainDelegate::ZygoteStarting(
     std::vector<std::unique_ptr<ZygoteForkDelegate>>* delegates) {}
 
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 int ContentMainDelegate::TerminateForFatalInitializationError() {
   CHECK(false);
