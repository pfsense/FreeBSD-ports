--- gpu/ipc/service/gpu_memory_buffer_factory.cc.orig	2021-01-07 00:36:35 UTC
+++ gpu/ipc/service/gpu_memory_buffer_factory.cc
@@ -12,7 +12,7 @@
 #include "gpu/ipc/service/gpu_memory_buffer_factory_io_surface.h"
 #endif
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA) || defined(OS_BSD)
 #include "gpu/ipc/service/gpu_memory_buffer_factory_native_pixmap.h"
 #endif
 
@@ -34,7 +34,7 @@ GpuMemoryBufferFactory::CreateNativeType(
   return std::make_unique<GpuMemoryBufferFactoryIOSurface>();
 #elif defined(OS_ANDROID)
   return std::make_unique<GpuMemoryBufferFactoryAndroidHardwareBuffer>();
-#elif defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA)
+#elif defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA) || defined(OS_BSD)
   return std::make_unique<GpuMemoryBufferFactoryNativePixmap>(
       vulkan_context_provider);
 #elif defined(OS_WIN)
