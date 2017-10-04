--- config.m4.orig	2017-10-04 12:41:14 UTC
+++ config.m4
@@ -58,12 +58,6 @@ if test "$PHP_RRD" != "no"; then
   old_LDFLAGS=$LDFLAGS
   LDFLAGS="$LDFLAGS -L$RRDTOOL_LIBDIR"
 
-  dnl rrd_graph_v is available in 1.3.0+
-  PHP_CHECK_FUNC(rrd_graph_v, rrd)
-  if test "$ac_cv_func_rrd_graph_v" != yes; then
-    AC_MSG_ERROR([rrd lib version seems older than 1.3.0, update to 1.3.0+])
-  fi
-
   dnl rrd_lastupdate_r available in 1.4.0+
   PHP_CHECK_FUNC(rrd_lastupdate_r, rrd)
 
