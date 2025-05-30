--- media/video/fake_gpu_memory_buffer.h.orig	2025-05-07 06:48:23 UTC
+++ media/video/fake_gpu_memory_buffer.h
@@ -13,7 +13,7 @@
 
 namespace media {
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // This method is used by tests to create a fake pixmap handle instead of
 // creating a FakeGpuMemoryBuffer. Once all tests are converted to use it,
 // FakeGpuMemoryBuffer will be removed and this file will be renamed
