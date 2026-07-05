--- zebra/if_sysctl.c.orig	2024-08-15 18:43:38 UTC
+++ zebra/if_sysctl.c
@@ -126,6 +126,8 @@ void interface_list(struct zebra_ns *zns)
 
 	/* Free sysctl buffer. */
 	XFREE(MTYPE_TMP, ref);
+
+	zebra_dplane_startup_stage(zns, ZEBRA_DPLANE_INTERFACES_READ);
 }
 
 #endif /* !defined(GNU_LINUX) && !defined(OPEN_BSD) */
