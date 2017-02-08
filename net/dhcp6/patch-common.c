--- common.c.orig	2016-12-19 08:16:42 UTC
+++ common.c
@@ -3344,7 +3344,7 @@ ifaddrconf(cmd, ifname, addr, plen, plti
 	}
 #endif
 
-	d_printf(LOG_DEBUG, FNAME, "%s an address %s/%d on %s", cmdstr,
+	d_printf(LOG_INFO, FNAME, "%s an address %s/%d on %s", cmdstr,
 	    addr2str((struct sockaddr *)addr), plen, ifname);
 
 	close(s);
