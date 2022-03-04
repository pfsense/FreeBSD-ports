--- chrome/browser/sync/device_info_sync_client_impl.cc.orig	2021-06-11 09:31:26 UTC
+++ chrome/browser/sync/device_info_sync_client_impl.cc
@@ -38,7 +38,7 @@ std::string DeviceInfoSyncClientImpl::GetSigninScopedD
 // in lacros-chrome once build flag switch of lacros-chrome is
 // complete.
 #if defined(OS_WIN) || defined(OS_MAC) || \
-    (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+    (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)) || defined(OS_BSD)
   syncer::SyncPrefs prefs(profile_->GetPrefs());
   if (prefs.IsLocalSyncEnabled()) {
     return "local_device";
