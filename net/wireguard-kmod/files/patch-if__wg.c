--- if_wg.c.orig	2022-10-14 12:16:23 UTC
+++ if_wg.c
@@ -693,7 +693,7 @@ wg_socket_init(struct wg_softc *sc, in_port_t port)
 	if (rc)
 		goto out;
 
-	rc = udp_set_kernel_tunneling(so4, (udp_tun_func_t)wg_input, NULL, sc);
+	rc = udp_set_kernel_tunneling(so4, wg_input, NULL, sc);
 	/*
 	 * udp_set_kernel_tunneling can only fail if there is already a tunneling function set.
 	 * This should never happen with a new socket.
@@ -704,7 +704,7 @@ wg_socket_init(struct wg_softc *sc, in_port_t port)
 	rc = socreate(AF_INET6, &so6, SOCK_DGRAM, IPPROTO_UDP, cred, td);
 	if (rc)
 		goto out;
-	rc = udp_set_kernel_tunneling(so6, (udp_tun_func_t)wg_input, NULL, sc);
+	rc = udp_set_kernel_tunneling(so6, wg_input, NULL, sc);
 	MPASS(rc == 0);
 #endif
 
