--- ext/standard/exec.c.orig	2014-04-01 20:22:40.000000000 -0300
+++ ext/standard/exec.c	2014-04-01 20:27:55.000000000 -0300
@@ -62,6 +62,9 @@
 	FILE *fp;
 	char *buf;
 	int l = 0, pclose_return;
+#ifdef __FreeBSD__
+	int fd, flags;
+#endif
 	char *b, *d=NULL;
 	php_stream *stream;
 	size_t buflen, bufl = 0;
@@ -73,6 +76,15 @@
 	sig_handler = signal (SIGCHLD, SIG_DFL);
 #endif
 
+#ifdef __FreeBSD__
+	for (fd = 3; fd < 20; fd++) {
+		if ((flags = fcntl(fd, F_GETFD, 0)) < 0)
+			continue;
+		flags |= FD_CLOEXEC;
+		fcntl(fd, F_SETFL, flags);
+	}
+#endif
+
 #ifdef PHP_WIN32
 	fp = VCWD_POPEN(cmd, "rb");
 #else
