--- components/constrained_window/constrained_window_views.cc.orig	2025-09-10 13:22:16 UTC
+++ components/constrained_window/constrained_window_views.cc
@@ -380,7 +380,7 @@ bool SupportsGlobalScreenCoordinates() {
 }
 
 bool PlatformClipsChildrenToViewport() {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return true;
 #else
   return false;
