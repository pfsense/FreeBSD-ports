--- src/3rdparty/chromium/ui/events/event_switches.h.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/ui/events/event_switches.h
@@ -13,7 +13,7 @@ EVENTS_BASE_EXPORT extern const char kTouchSlopDistanc
 EVENTS_BASE_EXPORT extern const char kCompensateForUnstablePinchZoom[];
 EVENTS_BASE_EXPORT extern const char kTouchSlopDistance[];
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 EVENTS_BASE_EXPORT extern const char kTouchDevices[];
 EVENTS_BASE_EXPORT extern const char kPenDevices[];
 #endif
