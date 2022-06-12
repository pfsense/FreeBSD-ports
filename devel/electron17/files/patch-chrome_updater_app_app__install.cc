--- chrome/updater/app/app_install.cc.orig	2022-05-11 07:16:49 UTC
+++ chrome/updater/app/app_install.cc
@@ -155,7 +155,7 @@ void AppInstall::WakeCandidate() {
       update_service_internal, base::WrapRefCounted(this)));
 }
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 // TODO(crbug.com/1276114) - implement.
 void AppInstall::WakeCandidateDone() {
   NOTIMPLEMENTED();
