--- oldsrc/ngfunc.h	2009-06-14 12:21:59.000000000 +0000
+++ src/ngfunc.h	2009-06-14 12:22:13.000000000 +0000
@@ -24,6 +24,10 @@
  * DEFINITIONS
  */
 
+/* XXX: Just to fix the port builidng since these constants are removed from FreeBSD. */
+#define NG_HOOKLEN     (NG_HOOKSIZ - 1)
+#define NG_NODELEN     (NG_NODESIZ - 1)
+#define NG_PATHLEN     (NG_PATHSIZ - 1)
   /*
    * The "bypass" hook is used to read PPP control frames.
    * The "demand" hook is a bit hacky - in closed state it is
