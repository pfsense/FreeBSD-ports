--- ui/gfx/x/generated_protos/dri3.cc.orig	2022-03-28 18:11:04 UTC
+++ ui/gfx/x/generated_protos/dri3.cc
@@ -44,6 +44,8 @@
 #include <xcb/xcb.h>
 #include <xcb/xcbext.h>
 
+#include <unistd.h>
+
 #include "base/logging.h"
 #include "base/posix/eintr_wrapper.h"
 #include "ui/gfx/x/xproto_internal.h"
