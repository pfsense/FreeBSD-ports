--- src/3rdparty/chromium/services/device/serial/serial_io_handler_posix.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/services/device/serial/serial_io_handler_posix.cc
@@ -37,6 +37,10 @@ struct termios2 {
 
 #endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
 
+#if defined(OS_BSD)
+#include <sys/serial.h>
+#endif
+
 #if defined(OS_MAC)
 #include <IOKit/serial/ioss.h>
 #endif
@@ -67,7 +71,7 @@ bool BitrateToSpeedConstant(int bitrate, speed_t* spee
     BITRATE_TO_SPEED_CASE(9600)
     BITRATE_TO_SPEED_CASE(19200)
     BITRATE_TO_SPEED_CASE(38400)
-#if !defined(OS_MAC)
+#if !defined(OS_MAC) && !defined(OS_BSD)
     BITRATE_TO_SPEED_CASE(57600)
     BITRATE_TO_SPEED_CASE(115200)
     BITRATE_TO_SPEED_CASE(230400)
