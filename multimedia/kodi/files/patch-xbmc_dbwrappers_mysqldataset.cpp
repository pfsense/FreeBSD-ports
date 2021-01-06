--- xbmc/dbwrappers/mysqldataset.cpp.orig	2020-10-04 17:34:28 UTC
+++ xbmc/dbwrappers/mysqldataset.cpp
@@ -17,10 +17,8 @@
 #include "utils/StringUtils.h"
 
 #include "mysqldataset.h"
-#ifdef HAS_MYSQL
+#if defined(HAS_MYSQL) || defined(HAS_MARIADB)
 #include <mysql/errmsg.h>
-#elif defined(HAS_MARIADB)
-#include <mariadb/errmsg.h>
 #endif
 
 #ifdef TARGET_POSIX
