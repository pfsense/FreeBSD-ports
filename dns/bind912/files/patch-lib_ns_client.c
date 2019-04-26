--- lib/ns/client.c.orig	2019-04-06 01:27:27 UTC
+++ lib/ns/client.c
@@ -61,6 +61,10 @@
 #include <ns/stats.h>
 #include <ns/update.h>
 
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+#include <stdatomic.h>
+#endif
+
 /***
  *** Client
  ***/
@@ -428,11 +432,21 @@ tcpconn_detach(ns_client_t *client) {
 static void
 mark_tcp_active(ns_client_t *client, bool active) {
 	if (active && !client->tcpactive) {
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+		atomic_fetch_add_explicit(&client->interface->ntcpactive, 1,
+					  memory_order_relaxed);
+#else
 		isc_atomic_xadd(&client->interface->ntcpactive, 1);
+#endif
 		client->tcpactive = active;
 	} else if (!active && client->tcpactive) {
 		uint32_t old =
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+			atomic_fetch_add_explicit(&client->interface->ntcpactive, -1,
+						  memory_order_relaxed);
+#else
 			isc_atomic_xadd(&client->interface->ntcpactive, -1);
+#endif
 		INSIST(old > 0);
 		client->tcpactive = active;
 	}
@@ -580,7 +594,12 @@ exit_check(ns_client_t *client) {
 		if (client->mortal && TCP_CLIENT(client) &&
 		    client->newstate != NS_CLIENTSTATE_FREED &&
 		    (client->sctx->options & NS_SERVER_CLIENTTEST) == 0 &&
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+		    atomic_fetch_add_explicit(&client->interface->ntcpaccepting, 0,
+					  memory_order_relaxed) == 0)
+#else
 		    isc_atomic_xadd(&client->interface->ntcpaccepting, 0) == 0)
+#endif
 		{
 			/* Nobody else is accepting */
 			client->mortal = false;
@@ -3326,7 +3345,12 @@ client_newconn(isc_task_t *task, isc_event_t *event) {
 	INSIST(client->naccepts == 1);
 	client->naccepts--;
 
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+	old = atomic_fetch_add_explicit(&client->interface->ntcpaccepting, -1,
+				  memory_order_relaxed);
+#else
 	old = isc_atomic_xadd(&client->interface->ntcpaccepting, -1);
+#endif
 	INSIST(old > 0);
 
 	/*
@@ -3457,7 +3481,12 @@ client_accept(ns_client_t *client) {
 		 * quota is tcp-clients plus the number of listening
 		 * interfaces plus 1.)
 		 */
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+		exit = (atomic_fetch_add_explicit(&client->interface->ntcpactive, 0,
+					  memory_order_relaxed) >
+#else
 		exit = (isc_atomic_xadd(&client->interface->ntcpactive, 0) >
+#endif
 			(client->tcpactive ? 1 : 0));
 		if (exit) {
 			client->newstate = NS_CLIENTSTATE_INACTIVE;
@@ -3516,7 +3545,12 @@ client_accept(ns_client_t *client) {
 	 * listening for connections itself to prevent the interface
 	 * going dead.
 	 */
+#if defined(ISC_PLATFORM_HAVESTDATOMIC)
+	atomic_fetch_add_explicit(&client->interface->ntcpaccepting, 1,
+				  memory_order_relaxed);
+#else
 	isc_atomic_xadd(&client->interface->ntcpaccepting, 1);
+#endif
 }
 
 static void
