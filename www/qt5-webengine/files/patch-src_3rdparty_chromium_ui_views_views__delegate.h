--- src/3rdparty/chromium/ui/views/views_delegate.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/views/views_delegate.h
@@ -134,7 +134,7 @@ class VIEWS_EXPORT ViewsDelegate {
   // environment.
   virtual bool IsWindowInMetro(gfx::NativeWindow window) const;
 #elif BUILDFLAG(ENABLE_DESKTOP_AURA) && \
-  (defined(OS_LINUX) || defined(OS_CHROMEOS))
+  (defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD))
   virtual gfx::ImageSkia* GetDefaultWindowIcon() const;
 #endif
 
