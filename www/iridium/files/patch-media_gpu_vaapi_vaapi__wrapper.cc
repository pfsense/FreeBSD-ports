--- media/gpu/vaapi/vaapi_wrapper.cc.orig	2023-07-24 14:27:53 UTC
+++ media/gpu/vaapi/vaapi_wrapper.cc
@@ -1609,7 +1609,7 @@ VADisplayStateHandle VADisplayStateSingleton::GetHandl
     return {};
   }
 
-#if BUILDFLAG(IS_OZONE) && BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_OZONE) && (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD))
   // TODO(crbug.com/1116701): add vaapi support for other Ozone platforms on
   // Linux. See comment in OzonePlatform::PlatformProperties::supports_vaapi
   // for more details. This will also require revisiting everything that's
