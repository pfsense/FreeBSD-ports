--- third_party/blink/renderer/core/exported/web_view_impl.cc.orig	2025-04-06 11:53:46 UTC
+++ third_party/blink/renderer/core/exported/web_view_impl.cc
@@ -430,7 +430,7 @@ SkFontHinting RendererPreferencesToSkiaHinting(
 #if !BUILDFLAG(IS_MAC) && !BUILDFLAG(IS_WIN)
 SkFontHinting RendererPreferencesToSkiaHinting(
     const blink::RendererPreferences& prefs) {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   if (!prefs.should_antialias_text) {
     // When anti-aliasing is off, GTK maps all non-zero hinting settings to
     // 'Normal' hinting so we do the same. Otherwise, folks who have 'Slight'
@@ -3436,7 +3436,7 @@ void WebViewImpl::UpdateFontRenderingFromRendererPrefs
       gfx::FontRenderParams::SUBPIXEL_RENDERING_NONE);
   WebFontRenderStyle::SetSubpixelPositioning(
       renderer_preferences_.use_subpixel_positioning);
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   if (!renderer_preferences_.system_font_family_name.empty()) {
     WebFontRenderStyle::SetSystemFontFamily(blink::WebString::FromUTF8(
         renderer_preferences_.system_font_family_name));
