--- ui/gl/gl_image_glx_native_pixmap.cc.orig	2022-05-11 07:17:07 UTC
+++ ui/gl/gl_image_glx_native_pixmap.cc
@@ -14,6 +14,8 @@
 #include "ui/gl/buffer_format_utils.h"
 #include "ui/gl/gl_bindings.h"
 
+#include <unistd.h>
+
 namespace gl {
 
 namespace {
