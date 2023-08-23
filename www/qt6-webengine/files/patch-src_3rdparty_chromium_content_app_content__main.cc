--- src/3rdparty/chromium/content/app/content_main.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/content/app/content_main.cc
@@ -228,7 +228,7 @@ RunContentProcess(ContentMainParams params,
     base::EnableTerminationOnOutOfMemory();
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
     // The various desktop environments set this environment variable that
     // allows the dbus client library to connect directly to the bus. When this
     // variable is not set (test environments like xvfb-run), the dbus client
