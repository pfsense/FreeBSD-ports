--- chrome/common/chrome_switches.cc.orig	2025-01-27 17:37:37 UTC
+++ chrome/common/chrome_switches.cc
@@ -892,7 +892,7 @@ const char kAllowNaClSocketAPI[] = "allow-nacl-socket-
 #endif
 
 #if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_MAC) || \
-    BUILDFLAG(IS_WIN)
+    BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
 const char kEnableNewAppMenuIcon[] = "enable-new-app-menu-icon";
 
 // Causes the browser to launch directly in guest mode.
@@ -902,7 +902,7 @@ const char kForceNtpMobilePromo[] = "force-ntp-mobile-
 const char kForceNtpMobilePromo[] = "force-ntp-mobile-promo";
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
 // Writes open and installed web apps for each profile to the specified file
 // without launching a new browser window or tab. Pass a absolute file path to
 // specify where to output the information. Can be used together with optional
