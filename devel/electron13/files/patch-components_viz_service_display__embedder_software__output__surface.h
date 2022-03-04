--- components/viz/service/display_embedder/software_output_surface.h.orig	2021-04-14 01:08:47 UTC
+++ components/viz/service/display_embedder/software_output_surface.h
@@ -51,7 +51,7 @@ class VIZ_SERVICE_EXPORT SoftwareOutputSurface : publi
   gfx::OverlayTransform GetDisplayTransform() override;
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_BSD)
   void SetNeedsSwapSizeNotifications(
       bool needs_swap_size_notifications) override;
 #endif
@@ -73,7 +73,7 @@ class VIZ_SERVICE_EXPORT SoftwareOutputSurface : publi
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_BSD)
   bool needs_swap_size_notifications_ = false;
 #endif
 
