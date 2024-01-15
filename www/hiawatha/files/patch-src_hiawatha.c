--- src/hiawatha.c.orig	2021-07-22 15:18:13 UTC
+++ src/hiawatha.c
@@ -141,8 +141,12 @@ void task_runner(t_config *config) {
 	int delay = 0;
 	time_t now;
 #ifdef ENABLE_LOADCHECK
+#ifdef HAVE_GETLOADAVG
+	double loadavg[1];
+#else
 	FILE *load_fp = NULL;
 	char load_str[50], *c;
+#endif
 #ifdef ENABLE_MONITOR
 	int  load_monitor_timer = 0;
 #endif
@@ -213,6 +217,21 @@ void task_runner(t_config *config) {
 
 #ifdef ENABLE_LOADCHECK
 		if (config->max_server_load > 0) {
+#ifdef HAVE_GETLOADAVG
+			if (getloadavg(loadavg, 1) >= 1) {
+				current_server_load = loadavg[0];
+#ifdef ENABLE_MONITOR
+				if (config->monitor_enabled) {
+					if ((current_server_load > config->max_server_load) && (load_monitor_timer == 0)) {
+						monitor_event("High server load (%0.2f)", current_server_load);
+						load_monitor_timer = 60;
+					}
+				}
+#endif
+			} else {
+				current_server_load = 0;
+			}
+#else
 			if ((load_fp = fopen("/proc/loadavg", "r")) != NULL) {
 				if (fgets(load_str, 49, load_fp) != NULL) {
 					load_str[49] = '\0';
@@ -238,6 +257,7 @@ void task_runner(t_config *config) {
 			} else {
 				current_server_load = 0;
 			}
+#endif
 
 #ifdef ENABLE_MONITOR
 			if (load_monitor_timer > 0) {
