--- libpkg/fetch_libcurl.c.orig	2023-11-06 20:02:58 UTC
+++ libpkg/fetch_libcurl.c
@@ -141,6 +141,7 @@ curl_do_fetch(struct curl_userdata *data, CURL *cl, st
 static long
 curl_do_fetch(struct curl_userdata *data, CURL *cl, struct curl_repodata *cr)
 {
+	char *tmp;
 	int still_running = 1;
 	CURLMsg *msg;
 	int msg_left;
@@ -154,6 +155,8 @@ curl_do_fetch(struct curl_userdata *data, CURL *cl, st
 		curl_easy_setopt(cl, CURLOPT_DEBUGFUNCTION, my_trace);
 
 	/* compat with libfetch */
+	if ((tmp = getenv("HTTP_USER_AGENT")) != NULL)
+		curl_easy_setopt(cl, CURLOPT_USERAGENT, tmp);
 	if (getenv("SSL_NO_VERIFY_PEER") != NULL)
 		curl_easy_setopt(cl, CURLOPT_SSL_VERIFYPEER, 0L);
 	if (getenv("SSL_NO_VERIFY_HOSTNAME") != NULL)
