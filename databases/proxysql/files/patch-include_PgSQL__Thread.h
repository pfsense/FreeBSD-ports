--- include/PgSQL_Thread.h.orig	2025-11-08 01:19:28 UTC
+++ include/PgSQL_Thread.h
@@ -212,10 +212,10 @@ class __attribute__((aligned(64))) PgSQL_Thread : publ
 	//PtrArray* mysql_sessions;
 	PtrArray* mirror_queue_mysql_sessions;
 	PtrArray* mirror_queue_mysql_sessions_cache;
+	CopyCmdMatcher *copy_cmd_matcher;
 #ifdef IDLE_THREADS
 	PtrArray* idle_mysql_sessions;
 	PtrArray* resume_mysql_sessions;
-	CopyCmdMatcher *copy_cmd_matcher;
 	pgsql_conn_exchange_t myexchange;
 #endif // IDLE_THREADS
 
