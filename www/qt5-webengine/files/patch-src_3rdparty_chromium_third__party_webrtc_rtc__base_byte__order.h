--- src/3rdparty/chromium/third_party/webrtc/rtc_base/byte_order.h.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/third_party/webrtc/rtc_base/byte_order.h
@@ -89,7 +89,7 @@
 #endif  // defined(WEBRTC_ARCH_LITTLE_ENDIAN)
 
 #elif defined(WEBRTC_POSIX)
-#include <endian.h>
+#include <sys/endian.h>
 #else
 #error "Missing byte order functions for this arch."
 #endif  // defined(WEBRTC_MAC)
