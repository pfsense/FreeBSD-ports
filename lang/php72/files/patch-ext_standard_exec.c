--- ext/standard/exec.c.orig	2018-04-23 16:57:09 UTC
+++ ext/standard/exec.c
@@ -100,6 +100,9 @@ PHPAPI int php_exec(int type, char *cmd, zval *array, 
 	char *buf;
 	size_t l = 0;
 	int pclose_return;
+#ifdef __FreeBSD__
+	int fd, flags;
+#endif
 	char *b, *d=NULL;
 	php_stream *stream;
 	size_t buflen, bufl = 0;
@@ -109,6 +112,15 @@ PHPAPI int php_exec(int type, char *cmd, zval *array, 
 
 #if PHP_SIGCHILD
 	sig_handler = signal (SIGCHLD, SIG_DFL);
+#endif
+
+#ifdef __FreeBSD__
+	for (fd = 3; fd < 20; fd++) {
+		if ((flags = fcntl(fd, F_GETFD, 0)) < 0)
+			continue;
+		flags |= FD_CLOEXEC;
+		fcntl(fd, F_SETFL, flags);
+	}
 #endif
 
 #ifdef PHP_WIN32
