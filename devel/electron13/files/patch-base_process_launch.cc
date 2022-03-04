--- base/process/launch.cc.orig	2021-01-07 00:36:18 UTC
+++ base/process/launch.cc
@@ -15,7 +15,7 @@ LaunchOptions::~LaunchOptions() = default;
 
 LaunchOptions LaunchOptionsForTest() {
   LaunchOptions options;
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // To prevent accidental privilege sharing to an untrusted child, processes
   // are started with PR_SET_NO_NEW_PRIVS. Do not set that here, since this
   // new child will be used for testing only.
