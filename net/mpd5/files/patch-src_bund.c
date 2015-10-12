--- src/bund.c	2013-06-11 10:00:00.000000000 +0100
+++ src/bund.c	2015-09-22 20:49:38.000000000 +0100
@@ -891,7 +891,7 @@
 
     } else if (!b->peer_mrru) {		/* If no multilink, use peer MRU */
 	mtu = MIN(b->links[the_link]->lcp.peer_mru,
-		  b->links[the_link]->type->mtu);
+		  PhysGetMtu(b->links[the_link], 0));
 
     } else {	  	/* Multilink, use peer MRRU */
         mtu = MIN(b->peer_mrru, MP_MAX_MRRU);
