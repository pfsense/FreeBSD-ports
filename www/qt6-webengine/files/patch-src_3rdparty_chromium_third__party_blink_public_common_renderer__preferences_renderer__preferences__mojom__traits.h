--- src/3rdparty/chromium/third_party/blink/public/common/renderer_preferences/renderer_preferences_mojom_traits.h.orig	2024-01-30 07:53:34 UTC
+++ src/3rdparty/chromium/third_party/blink/public/common/renderer_preferences/renderer_preferences_mojom_traits.h
@@ -157,7 +157,7 @@ struct BLINK_COMMON_EXPORT
     return data.send_subresource_notification;
   }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   static const std::string& system_font_family_name(
       const ::blink::RendererPreferences& data) {
     return data.system_font_family_name;
