--- src/util/signal.c.orig	2014-09-17 13:01:37 UTC
+++ src/util/signal.c
@@ -28,45 +28,6 @@
  * @brief Signal handling
  */
 
-/****************************************************************************
- Catch child exits and reap the child zombie status.
-****************************************************************************/
-
-static void sig_cld(int signum)
-{
-	while (waitpid((pid_t)-1,(int *)NULL, WNOHANG) > 0)
-		;
-
-	/*
-	 * Turns out it's *really* important not to
-	 * restore the signal handler here if we have real POSIX
-	 * signal handling. If we do, then we get the signal re-delivered
-	 * immediately - hey presto - instant loop ! JRA.
-	 */
-
-#if !defined(HAVE_SIGACTION)
-	CatchSignal(SIGCLD, sig_cld);
-#endif
-}
-
-/****************************************************************************
-catch child exits - leave status;
-****************************************************************************/
-
-static void sig_cld_leave_status(int signum)
-{
-	/*
-	 * Turns out it's *really* important not to
-	 * restore the signal handler here if we have real POSIX
-	 * signal handling. If we do, then we get the signal re-delivered
-	 * immediately - hey presto - instant loop ! JRA.
-	 */
-
-#if !defined(HAVE_SIGACTION)
-	CatchSignal(SIGCLD, sig_cld_leave_status);
-#endif
-}
-
 /**
  Block sigs.
 **/
@@ -125,22 +86,4 @@ void (*CatchSignal(int signum,void (*handler)(int )))(
 	/* FIXME: need to handle sigvec and systems with broken signal() */
 	return signal(signum, handler);
 #endif
-}
-
-/**
- Ignore SIGCLD via whatever means is necessary for this OS.
-**/
-
-void CatchChild(void)
-{
-	CatchSignal(SIGCLD, sig_cld);
-}
-
-/**
- Catch SIGCLD but leave the child around so it's status can be reaped.
-**/
-
-void CatchChildLeaveStatus(void)
-{
-	CatchSignal(SIGCLD, sig_cld_leave_status);
 }
