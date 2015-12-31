--- src/bund.c.orig	2013-06-11 09:00:00 UTC
+++ src/bund.c
@@ -891,7 +891,7 @@ BundUpdateParams(Bund b)
 
     } else if (!b->peer_mrru) {		/* If no multilink, use peer MRU */
 	mtu = MIN(b->links[the_link]->lcp.peer_mru,
-		  b->links[the_link]->type->mtu);
+		  PhysGetMtu(b->links[the_link], 0));
 
     } else {	  	/* Multilink, use peer MRRU */
         mtu = MIN(b->peer_mrru, MP_MAX_MRRU);
