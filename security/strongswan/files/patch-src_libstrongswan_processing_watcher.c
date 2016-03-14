--- src/libstrongswan/processing/watcher.c.orig	2015-12-07 20:33:06 UTC
+++ src/libstrongswan/processing/watcher.c
@@ -25,6 +25,7 @@
 #include <unistd.h>
 #include <errno.h>
 #include <fcntl.h>
+#include <sys/ioctl.h>		/* msd@netgate */
 
 typedef struct private_watcher_t private_watcher_t;
 
@@ -275,6 +276,45 @@ static bool entry_ready(entry_t *entry, 
 }
 
 /**
+ * msd@netgate - hack to detect flaw in NONBLOCK on notify pipe
+ */
+static void watcher_force_nonblock (private_watcher_t *this)
+{
+	int flags;
+	static unsigned times = 0;
+
+	/* use non-blocking I/O on read-end of notify pipe */
+	flags = fcntl(this->notify[0], F_GETFL);
+	if (flags == -1) {
+		DBG1(DBG_JOB, " ** watcher_force_nonblock: fcntl(F_GETFL) failed: %d **", errno);
+		return;
+	}
+	if (flags & O_NONBLOCK)
+		return;
+
+	DBG1(DBG_JOB, " %swatcher_force_nonblock: forcing NONBLOCK for the %u-th time%s"
+	  , (times ? "** " : ""), times, (times ? " **" : ""));
+	++times;
+
+	if (fcntl(this->notify[0], F_SETFL, flags | O_NONBLOCK) == -1)
+		DBG1(DBG_JOB, " ** watcher_force_nonblock: fcntl(F_SETFL, NONBLOCK) failed: %d **", errno);
+}
+
+/**
+ * msd@netgate - hack to ask the notify pipe how much can be read, avoiding a
+ *	blocked read() thereon
+ */
+static unsigned watcher_pipe_canread (private_watcher_t *this)
+{
+	auto int ai;
+
+	if (ioctl(this->notify[0], FIONREAD, &ai) != -1 && ai >= 0)
+		return (unsigned) ai;
+	DBG1(DBG_JOB, " ** watcher_pipe_canread: FIONREAD %d failed: %d **", ai, errno);
+	return 0;
+}
+
+/**
  * Dispatching function
  */
 static job_requeue_t watch(private_watcher_t *this)
@@ -359,8 +399,13 @@ static job_requeue_t watch(private_watch
 		{
 			if (pfd[0].revents & POLLIN)
 			{
-				while (TRUE)
+				unsigned nread = watcher_pipe_canread(this);	/* msd@netgate */
+				if (nread == 0)
+					DBG1(DBG_JOB, "** watcher got phantom POLLIN on notify pipe **");
+
+				while (nread--)
 				{
+					watcher_force_nonblock(this);		/* msd@netgate */
 					len = read(this->notify[0], buf, sizeof(buf));
 					if (len == -1)
 					{
@@ -561,6 +606,8 @@ static bool create_notify(private_watche
 
 	if (pipe(this->notify) == 0)
 	{
+#if 0	/* msd@netgate - the reader forces this - we don't do it here to so as to
+	 * cause the first time to fail & grouse, so we know it's working */
 		/* use non-blocking I/O on read-end of notify pipe */
 		flags = fcntl(this->notify[0], F_GETFL);
 		if (flags != -1 &&
@@ -570,6 +617,9 @@ static bool create_notify(private_watche
 		}
 		DBG1(DBG_LIB, "setting watcher notify pipe read-end non-blocking "
 			 "failed: %s", strerror(errno));
+#else	/* msd@netgate */
+		return TRUE;
+#endif	/* msd@netgate */
 	}
 	return FALSE;
 }
