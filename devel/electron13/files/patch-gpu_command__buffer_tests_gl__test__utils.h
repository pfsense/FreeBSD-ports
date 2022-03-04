--- gpu/command_buffer/tests/gl_test_utils.h.orig	2021-01-07 00:36:35 UTC
+++ gpu/command_buffer/tests/gl_test_utils.h
@@ -120,7 +120,7 @@ class GpuCommandBufferTestEGL {
     return gfx::HasExtension(gl_extensions_, extension);
   }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // Create GLImageNativePixmap filled in with the given pixels.
   scoped_refptr<gl::GLImageNativePixmap> CreateGLImageNativePixmap(
       gfx::BufferFormat format,
