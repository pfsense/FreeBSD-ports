--- external/libfetch/http.c.orig	2017-08-17 03:56:56 UTC
+++ external/libfetch/http.c
@@ -1373,12 +1373,51 @@ http_authorize(conn_t *conn, const char *hdr, http_aut
 /*****************************************************************************
  * Helper functions for connecting to a server or proxy
  */
+static int
+http_connect_tunnel(conn_t *conn, struct url *URL, struct url *purl, int isproxyauth)
+{
+	const char *p;
+	http_auth_challenges_t proxy_challenges;
+	init_http_auth_challenges(&proxy_challenges);
+	http_cmd(conn, "CONNECT %s:%d HTTP/1.1",
+	      URL->host, URL->port);
+	http_cmd(conn, "Host: %s:%d",
+	      URL->host, URL->port);
+	if (isproxyauth > 0)
+	{
+		http_auth_params_t aparams;
+		init_http_auth_params(&aparams);
+		if (*purl->user || *purl->pwd) {
+			aparams.user = strdup(purl->user);
+			aparams.password = strdup(purl->pwd);
+		} else if ((p = getenv("HTTP_PROXY_AUTH")) != NULL &&
+			    *p != '\0') {
+			if (http_authfromenv(p, &aparams) < 0) {
+				http_seterr(HTTP_NEED_PROXY_AUTH);
+				return HTTP_PROTOCOL_ERROR;
+			}
+		} else if (fetch_netrc_auth(purl) == 0) {
+			aparams.user = strdup(purl->user);
+			aparams.password = strdup(purl->pwd);
+		}
+		else {
+			// No auth information found in system - exiting with warning.
+			warnx("Missing username and/or password set");
+			return HTTP_PROTOCOL_ERROR;
+		}
+		http_authorize(conn, "Proxy-Authorization",
+				&proxy_challenges, &aparams, purl);
+		clean_http_auth_params(&aparams);
+	}
+	http_cmd(conn, "");
+	return 0;
+}
 
 /*
  * Connect to the correct HTTP server or proxy.
  */
 static conn_t *
-http_connect(struct url *URL, struct url *purl, const char *flags)
+http_connect(struct url *URL, struct url *purl, const char *flags, int isproxyauth)
 {
 	struct url *curl;
 	conn_t *conn;
@@ -1410,15 +1449,19 @@ http_connect(struct url *URL, struct url *purl, const 
 		return (NULL);
 	init_http_headerbuf(&headerbuf);
 	if (strcasecmp(URL->scheme, SCHEME_HTTPS) == 0 && purl) {
-		http_cmd(conn, "CONNECT %s:%d HTTP/1.1",
-		    URL->host, URL->port);
-		http_cmd(conn, "Host: %s:%d",
-		    URL->host, URL->port);
-		http_cmd(conn, "");
-		if (http_get_reply(conn) != HTTP_OK) {
-			http_seterr(conn->err);
+		if (http_connect_tunnel(conn, URL, purl, isproxyauth) > 0) {
+			fetch_syserr();
 			goto ouch;
 		}
+		/* Get replay from CONNECT Tunnel attempt */
+		int httpreply = http_get_reply(conn);
+		if (httpreply != HTTP_OK) {
+			http_seterr(httpreply);
+			/* If the error is a 407/HTTP_NEED_PROXY_AUTH */
+			if (httpreply == HTTP_NEED_PROXY_AUTH)
+				goto proxyauth;
+			goto ouch;
+		}
 		/* Read and discard the rest of the proxy response */
 		if (fetch_getln(conn) < 0) {
 			fetch_syserr();
@@ -1457,6 +1500,15 @@ ouch:
 	fetch_close(conn);
 	errno = serrno;
 	return (NULL);
+proxyauth:
+	/* returning a "dummy" object with error 
+	 * set to 407/HTTP_NEED_PROXY_AUTH */
+	serrno = errno;
+	clean_http_headerbuf(&headerbuf);
+	fetch_close(conn);
+	errno = serrno;
+	conn->err = HTTP_NEED_PROXY_AUTH;
+	return (conn);
 }
 
 static struct url *
@@ -1605,9 +1657,19 @@ http_request_body(struct url *URL, const char *op, str
 		}
 
 		/* connect to server or proxy */
-		if ((conn = http_connect(url, purl, flags)) == NULL)
+		/* Getting connection without proxy connection */
+		if ((conn = http_connect(url, purl, flags, 0)) == NULL)
 			goto ouch;
-
+		
+		/* If returning object request proxy auth, rerun the connect with proxy auth */
+		if (conn->err == HTTP_NEED_PROXY_AUTH) {
+			/* Retry connection with proxy auth */
+			if ((conn = http_connect(url, purl, flags, 1)) == NULL) {
+				http_seterr(HTTP_NEED_PROXY_AUTH);
+				goto ouch;
+			}
+		}
+		
 		host = url->host;
 #ifdef INET6
 		if (strchr(url->host, ':')) {
