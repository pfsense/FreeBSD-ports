--- src/daemon/protocols/sonmp.c.orig	2020-10-13 21:25:17 UTC
+++ src/daemon/protocols/sonmp.c
@@ -262,6 +262,8 @@ sonmp_send(struct lldpd *global,
 	PEEK_DISCARD(ETHER_ADDR_LEN - 1); /* Modify the last byte of the MAC address */
 	(void)POKE_UINT8(1);
 
+	sleep(1);
+
 	if (interfaces_send_helper(global, hardware,
 		(char *)packet, end - packet) == -1) {
 		log_warn("sonmp", "unable to send second SONMP packet on real device for %s",
