--- third_party/blink/common/renderer_preferences/renderer_preferences_mojom_traits.cc.orig	2025-08-26 20:49:50 UTC
+++ third_party/blink/common/renderer_preferences/renderer_preferences_mojom_traits.cc
@@ -78,7 +78,7 @@ bool StructTraits<blink::mojom::RendererPreferencesDat
 
   out->send_subresource_notification = data.send_subresource_notification();
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   if (!data.ReadSystemFontFamilyName(&out->system_font_family_name))
     return false;
 #endif
