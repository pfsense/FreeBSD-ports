--- chrome/common/chrome_paths.h.orig	2025-04-15 08:30:07 UTC
+++ chrome/common/chrome_paths.h
@@ -56,7 +56,7 @@ enum {
                      // to set policies for chrome. This directory
                      // contains subdirectories.
 #endif
-#if BUILDFLAG(IS_CHROMEOS) || \
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD) || \
     (BUILDFLAG(IS_LINUX) && BUILDFLAG(CHROMIUM_BRANDING)) || BUILDFLAG(IS_MAC)
   DIR_USER_EXTERNAL_EXTENSIONS,  // Directory for per-user external extensions
                                  // on Chrome Mac and Chromium Linux.
@@ -65,7 +65,7 @@ enum {
                                  // create it.
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   DIR_STANDALONE_EXTERNAL_EXTENSIONS,  // Directory for 'per-extension'
                                        // definition manifest files that
                                        // describe extensions which are to be
@@ -112,7 +112,7 @@ enum {
 
 #endif
 #if BUILDFLAG(ENABLE_EXTENSIONS) && \
-    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_MAC))
+    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD))
   DIR_NATIVE_MESSAGING,       // System directory where native messaging host
                               // manifest files are stored.
   DIR_USER_NATIVE_MESSAGING,  // Directory with Native Messaging Hosts
