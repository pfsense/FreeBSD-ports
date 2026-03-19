--- lib/ProxySQL_Admin.cpp.orig	2026-03-18 17:48:48 UTC
+++ lib/ProxySQL_Admin.cpp
@@ -2726,8 +2726,10 @@ ProxySQL_Admin::ProxySQL_Admin() :
 	// processlist configuration
 	variables.mysql_processlist.show_extended = 0;
 	variables.pgsql_processlist.show_extended = 0;
+#ifdef IDLE_THREADS
 	variables.mysql_processlist.show_idle_session = true;
 	variables.pgsql_processlist.show_idle_session = true;
+#endif
 	variables.mysql_processlist.max_query_length = PROCESSLIST_MAX_QUERY_LEN_DEFAULT;
 	variables.pgsql_processlist.max_query_length = PROCESSLIST_MAX_QUERY_LEN_DEFAULT;
 
