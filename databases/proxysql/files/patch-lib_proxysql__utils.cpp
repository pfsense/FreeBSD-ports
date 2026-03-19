--- lib/proxysql_utils.cpp.orig	2026-01-16 09:33:05 UTC
+++ lib/proxysql_utils.cpp
@@ -19,7 +19,13 @@
 #include <unistd.h>
 #include <dirent.h>
 #include <sys/syscall.h>
+#ifdef __linux__
 #include <linux/close_range.h>
+#endif
+#ifdef __FreeBSD__
+#include <sys/socket.h>
+#include <netinet/in.h>
+#endif
 
 using std::function;
 using std::string;
