--- includes.h.orig	2017-06-29 04:32:29 UTC
+++ includes.h
@@ -77,6 +77,8 @@
 #endif
 
 #include <net/if.h>
+#include <net/if_media.h>
+#include <net/if_mib.h>
 
 #ifdef HAVE_NET_IF_DL_H
 #include <net/if_dl.h>
