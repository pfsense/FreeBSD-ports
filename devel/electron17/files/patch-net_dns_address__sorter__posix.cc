--- net/dns/address_sorter_posix.cc.orig	2022-05-11 07:16:53 UTC
+++ net/dns/address_sorter_posix.cc
@@ -17,6 +17,7 @@
 #include <ifaddrs.h>
 #include <net/if.h>
 #include <netinet/in_var.h>
+#include <netinet6/in6_var.h>
 #include <string.h>
 #include <sys/ioctl.h>
 #endif
