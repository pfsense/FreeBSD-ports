--- if_igb.h.orig	2025-10-02 16:58:31 UTC
+++ if_igb.h
@@ -70,6 +70,7 @@
 #include <net/if_arp.h>
 #include <net/if_dl.h>
 #include <net/if_media.h>
+#include <net/if_private.h>
 #ifdef	RSS
 #include <net/rss_config.h>
 #include <netinet/in_rss.h>
