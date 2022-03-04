--- src/3rdparty/chromium/ui/compositor/compositor.cc.orig	2020-03-16 14:04:24 UTC
+++ src/3rdparty/chromium/ui/compositor/compositor.cc
@@ -681,7 +681,7 @@ void Compositor::OnFrameTokenChanged(uint32_t frame_to
   NOTREACHED();
 }
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 void Compositor::OnCompleteSwapWithNewSize(const gfx::Size& size) {
   for (auto& observer : observer_list_)
     observer.OnCompositingCompleteSwapWithNewSize(this, size);
