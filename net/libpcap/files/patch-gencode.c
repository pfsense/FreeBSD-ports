--- gencode.c.orig	2021-12-11 15:52:20 UTC
+++ gencode.c
@@ -48,20 +48,6 @@
 #include "pcap-dos.h"
 #endif
 
-#ifdef HAVE_NET_PFVAR_H
-/*
- * In NetBSD <net/if.h> includes <net/dlt.h>, which is an older version of
- * "pcap/dlt.h" with a lower value of DLT_MATCHING_MAX. Include the headers
- * below before "pcap-int.h", which eventually includes "pcap/dlt.h", which
- * redefines DLT_MATCHING_MAX from what this version of NetBSD has to what
- * this version of libpcap has.
- */
-#include <sys/socket.h>
-#include <net/if.h>
-#include <net/pfvar.h>
-#include <net/if_pflog.h>
-#endif /* HAVE_NET_PFVAR_H */
-
 #include "pcap-int.h"
 
 #include "extract.h"
@@ -86,6 +72,21 @@
 #include <linux/if_packet.h>
 #include <linux/filter.h>
 #endif
+
+#ifdef HAVE_NET_PFVAR_H
+/*
+ * In NetBSD <net/if.h> includes <net/dlt.h>, which is an older version of
+ * "pcap/dlt.h" with a lower value of DLT_MATCHING_MAX. Include the headers
+ * below before "pcap-int.h", which eventually includes "pcap/dlt.h", which
+ * redefines DLT_MATCHING_MAX from what this version of NetBSD has to what
+ * this version of libpcap has.
+ */
+#include <sys/socket.h>
+#include <net/if.h>
+#include <net/pfvar.h>
+#include <net/if_pflog.h>
+#endif /* HAVE_NET_PFVAR_H */
+
 
 #ifndef offsetof
 #define offsetof(s, e) ((size_t)&((s *)0)->e)
