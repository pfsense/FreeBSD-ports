--- src/3rdparty/chromium/ui/native_theme/native_theme_base.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/native_theme/native_theme_base.cc
@@ -255,7 +255,7 @@ void NativeThemeBase::Paint(cc::PaintCanvas* canvas,
     case kCheckbox:
       PaintCheckbox(canvas, state, rect, extra.button, color_scheme);
       break;
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) || defined(OS_BSD)) && !defined(OS_CHROMEOS)
     case kFrameTopArea:
       PaintFrameTopArea(canvas, state, rect, extra.frame_top_area,
                         color_scheme);
