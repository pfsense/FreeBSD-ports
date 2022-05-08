--- if_wg.c.orig	2021-11-05 15:40:17.000000000 +0100
+++ if_wg.c	2022-05-03 17:48:32.135472000 +0200
@@ -377,7 +377,7 @@
 static int wg_queue_both(struct wg_queue *, struct wg_queue *, struct wg_packet *);
 static struct wg_packet *wg_queue_dequeue_serial(struct wg_queue *);
 static struct wg_packet *wg_queue_dequeue_parallel(struct wg_queue *);
-static void wg_input(struct mbuf *, int, struct inpcb *, const struct sockaddr *, void *);
+static bool wg_input(struct mbuf *, int, struct inpcb *, const struct sockaddr *, void *);
 static void wg_peer_send_staged(struct wg_peer *);
 static int wg_clone_create(struct if_clone *, int, caddr_t);
 static void wg_qflush(struct ifnet *);
@@ -1946,7 +1946,7 @@
 	return (pkt);
 }
 
-static void
+static bool
 wg_input(struct mbuf *m, int offset, struct inpcb *inpcb,
     const struct sockaddr *sa, void *_sc)
 {
@@ -1965,7 +1965,7 @@
 	m = m_unshare(m, M_NOWAIT);
 	if (!m) {
 		if_inc_counter(sc->sc_ifp, IFCOUNTER_IQDROPS, 1);
-		return;
+		return (true);
 	}
 
 	/* Caller provided us with `sa`, no need for this header. */
@@ -1974,13 +1974,13 @@
 	/* Pullup enough to read packet type */
 	if ((m = m_pullup(m, sizeof(uint32_t))) == NULL) {
 		if_inc_counter(sc->sc_ifp, IFCOUNTER_IQDROPS, 1);
-		return;
+		return (true);
 	}
 
 	if ((pkt = wg_packet_alloc(m)) == NULL) {
 		if_inc_counter(sc->sc_ifp, IFCOUNTER_IQDROPS, 1);
 		m_freem(m);
-		return;
+		return (true);
 	}
 
 	/* Save send/recv address and port for later. */
@@ -2027,11 +2027,11 @@
 	} else {
 		goto error;
 	}
-	return;
+	return (true);
 error:
 	if_inc_counter(sc->sc_ifp, IFCOUNTER_IERRORS, 1);
 	wg_packet_free(pkt);
-	return;
+	return (true);
 }
 
 static void
