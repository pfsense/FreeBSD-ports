--- src/3rdparty/chromium/ui/ozone/common/egl_util.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/ui/ozone/common/egl_util.cc
@@ -26,9 +26,9 @@ const base::FilePath::CharType kDefaultEglSoname[] =
     FILE_PATH_LITERAL("libGLESv2.so");
 #else  // BUILDFLAG(IS_FUCHSIA)
 const base::FilePath::CharType kDefaultEglSoname[] =
-    FILE_PATH_LITERAL("libEGL.so.1");
+    FILE_PATH_LITERAL("libEGL.so");
 const base::FilePath::CharType kDefaultGlesSoname[] =
-    FILE_PATH_LITERAL("libGLESv2.so.2");
+    FILE_PATH_LITERAL("libGLESv2.so");
 #endif
 const base::FilePath::CharType kAngleEglSoname[] =
     FILE_PATH_LITERAL("libEGL.so");
