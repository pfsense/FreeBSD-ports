--- src/igmp.c.orig	2023-02-17 16:29:01 UTC
+++ src/igmp.c
@@ -295,7 +295,7 @@ static void buildIgmp(uint32_t src, uint32_t dst, int 
     igmp->igmp_group.s_addr = group;
     igmp->igmp_cksum        = 0;
     igmp->igmp_cksum        = inetChksum((unsigned short *)igmp,
-                                         IP_HEADER_RAOPT_LEN + datalen);
+                                         IGMP_MINLEN + datalen);
 
 }
 
