--- third_party/googletest/src/googletest/src/gtest.cc.orig	2019-12-12 12:42:45 UTC
+++ third_party/googletest/src/googletest/src/gtest.cc
@@ -114,6 +114,7 @@
 
 #if GTEST_CAN_STREAM_RESULTS_
 # include <arpa/inet.h>  // NOLINT
+# include <sys/socket.h> // NOLINT
 # include <netdb.h>  // NOLINT
 # include <sys/socket.h>  // NOLINT
 # include <sys/types.h>  // NOLINT
