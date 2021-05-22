--- chrome/browser/sync/profile_sync_service_factory.cc.orig	2021-04-14 01:08:41 UTC
+++ chrome/browser/sync/profile_sync_service_factory.cc
@@ -217,7 +217,7 @@ KeyedService* ProfileSyncServiceFactory::BuildServiceI
 // in lacros-chrome once build flag switch of lacros-chrome is
 // complete.
 #if defined(OS_WIN) || defined(OS_MAC) || \
-    (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+    (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)) || defined(OS_BSD)
   syncer::SyncPrefs prefs(profile->GetPrefs());
   local_sync_backend_enabled = prefs.IsLocalSyncEnabled();
   UMA_HISTOGRAM_BOOLEAN("Sync.Local.Enabled", local_sync_backend_enabled);
@@ -235,7 +235,7 @@ KeyedService* ProfileSyncServiceFactory::BuildServiceI
 
     init_params.start_behavior = syncer::ProfileSyncService::AUTO_START;
   }
-#endif  // defined(OS_WIN) || defined(OS_MAC) || (defined(OS_LINUX) ||
+#endif  // defined(OS_WIN) || defined(OS_MAC) || (defined(OS_LINUX) || defined(OS_BSD) ||
         // BUILDFLAG(IS_CHROMEOS_LACROS))
 
   if (!local_sync_backend_enabled) {
