--- includes.h.orig	2025-01-08 09:52:55.082407000 +0100
+++ includes.h	2025-01-08 09:53:14.368065000 +0100
@@ -80,6 +80,8 @@
 #endif
 
 #include <net/if.h>
+#include <net/if_media.h>
+#include <net/if_mib.h>
 
 #ifdef HAVE_NET_IF_DL_H
 #include <net/if_dl.h>
