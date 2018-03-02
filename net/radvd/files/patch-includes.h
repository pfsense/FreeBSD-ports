--- includes.h.orig	2018-01-26 02:47:06 UTC
+++ includes.h
@@ -77,6 +77,7 @@
 #endif
 
 #include <net/if.h>
+#include <net/if_media.h>
 
 #ifdef HAVE_NET_IF_DL_H
 #include <net/if_dl.h>
