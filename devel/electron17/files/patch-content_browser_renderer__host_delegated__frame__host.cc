--- content/browser/renderer_host/delegated_frame_host.cc.orig	2022-05-11 07:16:51 UTC
+++ content/browser/renderer_host/delegated_frame_host.cc
@@ -288,7 +288,7 @@ void DelegatedFrameHost::EmbedSurface(
 
   if (!primary_surface_id ||
       primary_surface_id->local_surface_id() != local_surface_id_) {
-#if defined(OS_WIN) || defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if defined(OS_WIN) || defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_BSD)
     // On Windows and Linux, we would like to produce new content as soon as
     // possible or the OS will create an additional black gutter. Until we can
     // block resize on surface synchronization on these platforms, we will not
