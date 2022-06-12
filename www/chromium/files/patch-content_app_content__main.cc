--- content/app/content_main.cc.orig	2022-04-21 18:48:31 UTC
+++ content/app/content_main.cc
@@ -260,7 +260,7 @@ RunContentProcess(ContentMainParams params,
     base::allocator::InstallDanglingRawPtrChecks();
     base::EnableTerminationOnOutOfMemory();
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
     // The various desktop environments set this environment variable that
     // allows the dbus client library to connect directly to the bus. When this
     // variable is not set (test environments like xvfb-run), the dbus client
