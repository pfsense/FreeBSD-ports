--- includes.h.orig	2019-09-21 21:50:05 UTC
+++ includes.h
@@ -81,6 +81,8 @@
 #define IF_NAMESIZE IFNAMSIZ
 #else
 #include <net/if.h>
+#include <net/if_media.h>
+#include <net/if_mib.h>
 #endif
 
 #ifdef HAVE_NET_IF_DL_H
