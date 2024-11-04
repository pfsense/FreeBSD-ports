--- ntpd/ntp_io.c.orig	2024-05-07 11:21:17 UTC
+++ ntpd/ntp_io.c
@@ -1921,11 +1921,11 @@ update_interfaces(
 		}
 		else {
 			DPRINT_INTERFACE(3,
-				(ep, "updating ", " new - FAILED"));
+				(ep2, "updating ", " new - FAILED"));
 
 			msyslog(LOG_ERR,
 				"cannot bind address %s",
-				stoa(&ep->sin));
+				stoa(&ep2->sin));
 		}
 		free(ep2);
 	}
