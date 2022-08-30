--- support.h.orig	2022-06-15 14:11:06 UTC
+++ support.h
@@ -37,7 +37,11 @@ sogetsockaddr(struct socket *so, struct sockaddr **nam
 	int error;
 
 	CURVNET_SET(so->so_vnet);
+#if __FreeBSD_version >= 1400066
+	error = (*so->so_proto->pr_sockaddr)(so, nam);
+#else
 	error = (*so->so_proto->pr_usrreqs->pru_sockaddr)(so, nam);
+#endif
 	CURVNET_RESTORE();
 	return (error);
 }
