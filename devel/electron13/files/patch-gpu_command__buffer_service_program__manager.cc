--- gpu/command_buffer/service/program_manager.cc.orig	2021-01-07 00:36:35 UTC
+++ gpu/command_buffer/service/program_manager.cc
@@ -30,7 +30,11 @@
 #include "gpu/command_buffer/service/program_cache.h"
 #include "gpu/command_buffer/service/shader_manager.h"
 #include "gpu/config/gpu_preferences.h"
+#if defined(OS_BSD)
+#include <re2/re2.h>
+#else
 #include "third_party/re2/src/re2/re2.h"
+#endif
 #include "ui/gl/gl_version_info.h"
 #include "ui/gl/progress_reporter.h"
 
