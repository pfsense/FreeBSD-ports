--- components/password_manager/core/browser/features/password_features.cc.orig	2025-01-27 17:37:37 UTC
+++ components/password_manager/core/browser/features/password_features.cc
@@ -35,7 +35,7 @@ BASE_FEATURE(kClearUndecryptablePasswordsOnSync,
 BASE_FEATURE(kClearUndecryptablePasswordsOnSync,
              "ClearUndecryptablePasswordsInSync",
 #if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_IOS) || \
-    BUILDFLAG(IS_WIN)
+    BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
              base::FEATURE_ENABLED_BY_DEFAULT
 #else
              base::FEATURE_DISABLED_BY_DEFAULT
@@ -109,7 +109,7 @@ BASE_FEATURE(kReuseDetectionBasedOnPasswordHashes,
              "ReuseDetectionBasedOnPasswordHashes",
              base::FEATURE_DISABLED_BY_DEFAULT);
 
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 BASE_FEATURE(kRestartToGainAccessToKeychain,
              "RestartToGainAccessToKeychain",
 #if BUILDFLAG(IS_MAC)
