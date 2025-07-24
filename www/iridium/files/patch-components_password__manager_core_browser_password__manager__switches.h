--- components/password_manager/core/browser/password_manager_switches.h.orig	2025-05-07 06:48:23 UTC
+++ components/password_manager/core/browser/password_manager_switches.h
@@ -9,7 +9,7 @@
 
 namespace password_manager {
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 extern const char kPasswordStore[];
 extern const char kEnableEncryptionSelection[];
 #endif  // BUILDFLAG(IS_LINUX)
