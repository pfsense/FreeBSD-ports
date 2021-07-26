--- services/authzone.c.orig	2021-07-26 17:07:57 UTC
+++ services/authzone.c
@@ -5210,7 +5210,7 @@ xfr_transfer_init_fetch(struct auth_xfer* xfr, struct 
 	/* perform AXFR/IXFR */
 	/* set the packet to be written */
 	/* create new ID */
-	xfr->task_transfer->id = (uint16_t)(ub_random(env->rnd)&0xffff);
+	xfr->task_transfer->id = GET_RANDOM_ID(env->rnd);
 	xfr_create_ixfr_packet(xfr, env->scratch_buffer,
 		xfr->task_transfer->id, master);
 
@@ -6060,7 +6060,7 @@ xfr_probe_send_probe(struct auth_xfer* xfr, struct mod
 	/* create new ID for new probes, but not on timeout retries,
 	 * this means we'll accept replies to previous retries to same ip */
 	if(timeout == AUTH_PROBE_TIMEOUT)
-		xfr->task_probe->id = (uint16_t)(ub_random(env->rnd)&0xffff);
+		xfr->task_probe->id = GET_RANDOM_ID(env->rnd);
 	xfr_create_soa_probe_packet(xfr, env->scratch_buffer, 
 		xfr->task_probe->id);
 	/* we need to remove the cp if we have a different ip4/ip6 type now */
