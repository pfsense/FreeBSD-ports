--- remoting/host/me2me_desktop_environment.cc.orig	2019-09-10 11:14:11 UTC
+++ remoting/host/me2me_desktop_environment.cc
@@ -131,7 +131,7 @@ bool Me2MeDesktopEnvironment::InitializeSecurity(
 
   // Otherwise, if the session is shared with the local user start monitoring
   // the local input and create the in-session UI.
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   bool want_user_interface = false;
 #elif defined(OS_MACOSX)
   // Don't try to display any UI on top of the system's login screen as this
