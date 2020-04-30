--- headless/lib/headless_content_main_delegate.cc.orig	2020-03-16 18:39:51 UTC
+++ headless/lib/headless_content_main_delegate.cc
@@ -405,7 +405,7 @@ int HeadlessContentMainDelegate::RunProcess(
 }
 #endif  // !defined(CHROME_MULTIPLE_DLL_CHILD)
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 void SIGTERMProfilingShutdown(int signal) {
   content::Profiling::Stop();
   struct sigaction sigact;
@@ -440,7 +440,7 @@ void HeadlessContentMainDelegate::ZygoteForked() {
   breakpad::InitCrashReporter(process_type);
 #endif
 }
-#endif  // defined(OS_LINUX)
+#endif  // defined(OS_LINUX) || defined(OS_BSD)
 
 // static
 HeadlessContentMainDelegate* HeadlessContentMainDelegate::GetInstance() {
