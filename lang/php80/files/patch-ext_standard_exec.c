--- ext/standard/exec.c.orig	2019-03-05 15:13:50 UTC
+++ ext/standard/exec.c
@@ -105,9 +105,21 @@ PHPAPI int php_exec(int type, char *cmd, zval *array, 
 #if PHP_SIGCHILD
 	void (*sig_handler)() = NULL;
 #endif
+#ifdef __FreeBSD__
+	int fd, flags;
+#endif
 
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
