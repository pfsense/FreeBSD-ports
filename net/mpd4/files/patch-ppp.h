--- oldsrc/ppp.h	2009-10-21 11:33:20.000000000 +0000
+++ src/ppp.h	2009-10-21 11:33:28.000000000 +0000
@@ -62,6 +62,13 @@
  * DEFINITIONS
  */
 
+/* XXX: Removed definitions */
+#define NG_TYPELEN      (NG_TYPESIZ - 1)
+#define NG_HOOKLEN      (NG_HOOKSIZ - 1)
+#define NG_NODELEN      (NG_NODESIZ - 1)
+#define NG_PATHLEN      (NG_PATHSIZ - 1)
+#define NG_CMDSTRLEN    (NG_CMDSTRSIZ - 1)
+
   /* Do our own version of assert() so it shows up in the logs */
   #define assert(e)	((e) ? (void)0 : DoAssert(__FILE__, __LINE__, #e))
 
