--- components/password_manager/core/browser/sync/password_sync_bridge.cc.orig	2022-04-01 07:48:30 UTC
+++ components/password_manager/core/browser/sync/password_sync_bridge.cc
@@ -185,7 +185,7 @@ bool ShouldRecoverPasswordsDuringMerge() {
   // Delete the local undecryptable copy when this is MacOS only.
 #if BUILDFLAG(IS_MAC)
   return true;
-#elif BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return base::FeatureList::IsEnabled(
       features::kSyncUndecryptablePasswordsLinux);
 #else
@@ -194,7 +194,7 @@ bool ShouldRecoverPasswordsDuringMerge() {
 }
 
 bool ShouldCleanSyncMetadataDuringStartupWhenDecryptionFails() {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return ShouldRecoverPasswordsDuringMerge() &&
          base::FeatureList::IsEnabled(
              features::kForceInitialSyncWhenDecryptionFails);
