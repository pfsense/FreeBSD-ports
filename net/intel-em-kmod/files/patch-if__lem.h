--- if_lem.h.orig	2024-11-26 13:15:58 UTC
+++ if_lem.h
@@ -297,7 +297,7 @@ struct adapter {
 
 	/* FreeBSD operating-system-specific structures. */
 	struct e1000_osdep osdep;
-	struct device	*dev;
+	device_t	dev;
 	struct cdev	*led_dev;
 
 	struct resource *memory;
