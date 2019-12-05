--- zebra/rtadv.c.orig	2019-12-04 21:37:02 UTC
+++ zebra/rtadv.c
@@ -395,7 +395,7 @@ static void rtadv_send_packet(int sock, struct interfa
 		opt->nd_opt_rdnss_lifetime = htonl(
 			rdnss->lifetime_set
 				? rdnss->lifetime
-				: MAX(1, 0.003 * zif->rtadv.MaxRtrAdvInterval));
+				: MAX(1, (int)(0.003 * zif->rtadv.MaxRtrAdvInterval)));
 
 		len += sizeof(struct nd_opt_rdnss);
 
@@ -424,7 +424,7 @@ static void rtadv_send_packet(int sock, struct interfa
 		opt->nd_opt_dnssl_lifetime = htonl(
 			dnssl->lifetime_set
 				? dnssl->lifetime
-				: MAX(1, 0.003 * zif->rtadv.MaxRtrAdvInterval));
+				: MAX(1, (int)(0.003 * zif->rtadv.MaxRtrAdvInterval)));
 
 		len += sizeof(struct nd_opt_dnssl);
 
