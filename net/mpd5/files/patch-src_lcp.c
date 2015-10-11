--- src/lcp.c	2013-06-11 10:00:00.000000000 +0100
+++ src/lcp.c	2015-09-22 20:49:38.000000000 +0100
@@ -226,10 +226,10 @@
     lcp->peer_reject = 0;
 
     /* Initialize normal LCP stuff */
-    lcp->peer_mru = l->conf.mtu;
-    lcp->want_mru = l->conf.mru;
-    if (l->type && (lcp->want_mru > l->type->mru))
-	lcp->want_mru = l->type->mru;
+    lcp->peer_mru = PhysGetMtu(l, 1);
+    lcp->want_mru = PhysGetMru(l, 1);
+    if (l->type && (lcp->want_mru > PhysGetMru(l, 0)))
+	lcp->want_mru = PhysGetMru(l, 0);
     lcp->peer_accmap = 0xffffffff;
     lcp->want_accmap = l->conf.accmap;
     lcp->peer_acfcomp = FALSE;
@@ -793,7 +793,7 @@
 
     /* If we have got request, forget the previous values */
     if (mode == MODE_REQ) {
-	lcp->peer_mru = l->conf.mtu;
+	lcp->peer_mru = PhysGetMtu(l, 1);
 	lcp->peer_accmap = 0xffffffff;
 	lcp->peer_acfcomp = FALSE;
 	lcp->peer_protocomp = FALSE;
