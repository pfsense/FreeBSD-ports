--- ui/events/event_switches.h.orig	2022-02-28 16:54:41 UTC
+++ ui/events/event_switches.h
@@ -12,7 +12,7 @@ namespace switches {
 
 EVENTS_BASE_EXPORT extern const char kCompensateForUnstablePinchZoom[];
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 EVENTS_BASE_EXPORT extern const char kTouchDevices[];
 EVENTS_BASE_EXPORT extern const char kPenDevices[];
 #endif
