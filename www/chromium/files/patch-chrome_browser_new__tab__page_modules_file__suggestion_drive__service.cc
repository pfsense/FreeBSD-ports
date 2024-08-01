--- chrome/browser/new_tab_page/modules/file_suggestion/drive_service.cc.orig	2024-05-21 18:07:39 UTC
+++ chrome/browser/new_tab_page/modules/file_suggestion/drive_service.cc
@@ -32,7 +32,7 @@
 #include "services/network/public/cpp/resource_request.h"
 
 namespace {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 constexpr char kPlatform[] = "LINUX";
 #elif BUILDFLAG(IS_WIN)
 constexpr char kPlatform[] = "WINDOWS";
