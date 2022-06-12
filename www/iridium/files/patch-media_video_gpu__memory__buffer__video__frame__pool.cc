--- media/video/gpu_memory_buffer_video_frame_pool.cc.orig	2022-03-28 18:11:04 UTC
+++ media/video/gpu_memory_buffer_video_frame_pool.cc
@@ -720,7 +720,7 @@ void GpuMemoryBufferVideoFramePool::PoolImpl::CreateHa
   }
 
   bool is_software_backed_video_frame = !video_frame->HasTextures();
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   is_software_backed_video_frame &= !video_frame->HasDmaBufs();
 #endif
 
