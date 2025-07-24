--- chrome/browser/feedback/system_logs/about_system_logs_fetcher.cc.orig	2025-04-22 20:15:27 UTC
+++ chrome/browser/feedback/system_logs/about_system_logs_fetcher.cc
@@ -37,7 +37,7 @@
 #include "chrome/browser/ash/system_logs/ui_hierarchy_log_source.h"
 #endif
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/feedback/system_logs/log_sources/ozone_platform_state_dump_source.h"
 #endif
 
@@ -84,7 +84,7 @@ SystemLogsFetcher* BuildAboutSystemLogsFetcher(content
   fetcher->AddSource(std::make_unique<KeyboardInfoLogSource>());
 #endif
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   fetcher->AddSource(std::make_unique<OzonePlatformStateDumpSource>());
 #endif  // BUILDFLAG(IS_LINUX)
 
