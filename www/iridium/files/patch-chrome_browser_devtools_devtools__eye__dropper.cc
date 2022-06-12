--- chrome/browser/devtools/devtools_eye_dropper.cc.orig	2022-03-28 18:11:04 UTC
+++ chrome/browser/devtools/devtools_eye_dropper.cc
@@ -186,7 +186,7 @@ void DevToolsEyeDropper::UpdateCursor() {
 // magnified projection only with centered hotspot.
 // Mac Retina requires cursor to be > 120px in order to render smoothly.
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   const float kCursorSize = 63;
   const float kDiameter = 63;
   const float kHotspotOffset = 32;
