--- ui/ozone/platform/wayland/host/wayland_frame_manager.cc.orig	2025-05-05 19:42:33 UTC
+++ ui/ozone/platform/wayland/host/wayland_frame_manager.cc
@@ -461,8 +461,10 @@ std::optional<bool> WaylandFrameManager::ApplySurfaceC
   surface->UpdateBufferDamageRegion(
       gfx::ToEnclosingRectIgnoringError(surface_damage));
 
+#if !BUILDFLAG(IS_BSD)
   if (!config.access_fence_handle.is_null())
     surface->set_acquire_fence(std::move(config.access_fence_handle));
+#endif
 
   bool needs_commit = false;
 
