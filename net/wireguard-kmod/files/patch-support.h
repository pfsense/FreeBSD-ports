From 20584d1c329ed2a71893375fa11ca4c56ed9f642 Mon Sep 17 00:00:00 2001
From: "Jason A. Donenfeld" <Jason@zx2c4.com>
Date: Sun, 4 Sep 2022 19:06:00 +0200
Subject: support: account for protosw change

e7d02be19 ("protosw: refactor protosw and domain static declaration and
load") changed the way this function should be invoked.

Link: https://github.com/freebsd/freebsd-src/commit/e7d02be19d40063783d6b8f1ff2bc4c7170fd434
Reported-by: Michael Pro <michael.adm@gmail.com>
Signed-off-by: Jason A. Donenfeld <Jason@zx2c4.com>
--- support.h.orig	2022-06-15 14:11:06 UTC
+++ support.h
@@ -37,7 +37,11 @@ sogetsockaddr(struct socket *so, struct sockaddr **nam
 	int error;
 
 	CURVNET_SET(so->so_vnet);
+#if __FreeBSD_version < 1400066
 	error = (*so->so_proto->pr_usrreqs->pru_sockaddr)(so, nam);
+#else
+	error = so->so_proto->pr_sockaddr(so, nam);
+#endif
 	CURVNET_RESTORE();
 	return (error);
 }
