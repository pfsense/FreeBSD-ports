--- media/capture/video/video_capture_buffer_pool_impl.cc.orig	2019-09-10 11:14:08 UTC
+++ media/capture/video/video_capture_buffer_pool_impl.cc
@@ -58,7 +58,7 @@ VideoCaptureBufferPoolImpl::CreateSharedMemoryViaRawFi
     int buffer_id) {
 // This requires platforms where base::SharedMemoryHandle is backed by a
 // file descriptor.
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   base::AutoLock lock(lock_);
 
   VideoCaptureBufferTracker* tracker = GetTracker(buffer_id);
