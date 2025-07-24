--- third_party/webrtc/rtc_base/physical_socket_server.h.orig	2025-06-19 07:37:57 UTC
+++ third_party/webrtc/rtc_base/physical_socket_server.h
@@ -21,7 +21,7 @@
 #include "rtc_base/third_party/sigslot/sigslot.h"
 
 #if defined(WEBRTC_POSIX)
-#if defined(WEBRTC_LINUX)
+#if defined(WEBRTC_LINUX) && !defined(WEBRTC_BSD)
 // On Linux, use epoll.
 #include <sys/epoll.h>
 
