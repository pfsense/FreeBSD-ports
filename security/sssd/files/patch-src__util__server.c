--- src/util/server.c.orig	2014-09-17 13:01:37 UTC
+++ src/util/server.c
@@ -322,12 +322,14 @@ static void setup_signals(void)
     BlockSignals(false, SIGTERM);
 
     CatchSignal(SIGHUP, sig_hup);
-
 #ifndef HAVE_PRCTL
-        /* If prctl is not defined on the system, try to handle
-         * some common termination signals gracefully */
-    CatchSignal(SIGSEGV, sig_segv_abrt);
-    CatchSignal(SIGABRT, sig_segv_abrt);
+    /* If prctl is not defined on the system, try to handle
+     * some common termination signals gracefully */
+    (void) sig_segv_abrt; /* unused */
+    /*
+      CatchSignal(SIGSEGV, sig_segv_abrt);
+      CatchSignal(SIGABRT, sig_segv_abrt);
+    */
 #endif
 
 }
