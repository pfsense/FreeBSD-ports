--- prefixconf.c.orig	2007-03-21 09:52:55 UTC
+++ prefixconf.c
@@ -192,7 +192,7 @@ update_prefix(ia, pinfo, pifc, dhcpifp, 
 	/* update the prefix according to pinfo */
 	sp->prefix.pltime = pinfo->pltime;
 	sp->prefix.vltime = pinfo->vltime;
-	dprintf(LOG_DEBUG, FNAME, "%s a prefix %s/%d pltime=%lu, vltime=%lu",
+	dprintf(LOG_INFO, FNAME, "%s a prefix %s/%d pltime=%lu, vltime=%lu",
 	    spcreate ? "create" : "update",
 	    in6addr2str(&pinfo->addr, 0), pinfo->plen,
 	    pinfo->pltime, pinfo->vltime);
