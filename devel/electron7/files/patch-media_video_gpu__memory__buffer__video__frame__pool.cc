--- media/video/gpu_memory_buffer_video_frame_pool.cc.orig	2019-12-12 12:39:51 UTC
+++ media/video/gpu_memory_buffer_video_frame_pool.cc
@@ -555,7 +555,7 @@ void GpuMemoryBufferVideoFramePool::PoolImpl::CreateHa
   }
 
   bool is_software_backed_video_frame = !video_frame->HasTextures();
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   is_software_backed_video_frame &= !video_frame->HasDmaBufs();
 #endif
 
