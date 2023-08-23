--- src/3rdparty/chromium/ui/ozone/platform/x11/ozone_platform_x11.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/ui/ozone/platform/x11/ozone_platform_x11.cc
@@ -195,7 +195,7 @@ class OzonePlatformX11 : public OzonePlatform,
       properties->supports_global_application_menus = true;
       properties->app_modal_dialogs_use_event_blocker = true;
       properties->fetch_buffer_formats_for_gmb_on_gpu = true;
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
       properties->supports_vaapi = true;
 #endif
 
