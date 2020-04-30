--- media/gpu/linux/platform_video_frame_utils.cc.orig	2019-12-12 12:39:49 UTC
+++ media/gpu/linux/platform_video_frame_utils.cc
@@ -121,7 +121,7 @@ gfx::GpuMemoryBufferHandle CreateGpuMemoryBufferHandle
   DCHECK(video_frame);
 
   gfx::GpuMemoryBufferHandle handle;
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   handle.type = gfx::NATIVE_PIXMAP;
 
   std::vector<base::ScopedFD> duped_fds =
@@ -136,7 +136,7 @@ gfx::GpuMemoryBufferHandle CreateGpuMemoryBufferHandle
   }
 #else
   NOTREACHED();
-#endif  // defined(OS_LINUX)
+#endif  // defined(OS_LINUX) || defined(OS_BSD)
   return handle;
 }
 
