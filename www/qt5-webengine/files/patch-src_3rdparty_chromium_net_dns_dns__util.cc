--- src/3rdparty/chromium/net/dns/dns_util.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/net/dns/dns_util.cc
@@ -39,6 +39,8 @@ const uint16_t kFlagNamePointer = 0xc000;
 
 }  // namespace
 
+#include <sys/socket.h>
+
 #if defined(OS_POSIX)
 #include <netinet/in.h>
 #if !defined(OS_NACL)
