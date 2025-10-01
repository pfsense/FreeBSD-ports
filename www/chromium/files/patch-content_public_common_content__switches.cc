--- content/public/common/content_switches.cc.orig	2025-09-06 10:01:20 UTC
+++ content/public/common/content_switches.cc
@@ -347,6 +347,8 @@ const char kEnableIsolatedWebAppsInRenderer[] =
 // builds.
 const char kEnableLogging[]                 = "enable-logging";
 
+const char kDisableUnveil[]                 = "disable-unveil";
+
 // Enables the type, downlinkMax attributes of the NetInfo API. Also, enables
 // triggering of change attribute of the NetInfo API when there is a change in
 // the connection type.
@@ -946,7 +948,7 @@ const char kPreventResizingContentsForTesting[] =
     "prevent-resizing-contents-for-testing";
 #endif
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // Allows sending text-to-speech requests to speech-dispatcher, a common
 // Linux speech service. Because it's buggy, the user must explicitly
 // enable it so that visiting a random webpage can't cause instability.
