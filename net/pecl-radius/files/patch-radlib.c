--- radlib.c.orig	2016-02-15 15:11:50 UTC
+++ radlib.c
@@ -47,8 +47,17 @@
 #include <string.h>
 #ifndef PHP_WIN32
 #include <unistd.h>
+#include <fcntl.h>
 #endif
 
+#ifdef HAVE_CONFIG_H
+#include "config.h"
+#endif
+
+#ifdef HAVE_OPENSSL
+#include <openssl/hmac.h>
+#endif
+
 #include "radlib_compat.h"
 #include "radlib_md5.h"
 #include "radlib_private.h"
@@ -58,8 +67,7 @@ static void	 insert_request_authenticator(struct rad_h
 		    __printflike(2, 3);
 static void	 insert_scrambled_password(struct rad_handle *, int);
 static void	 insert_request_authenticator(struct rad_handle *, int);
-static int	 is_valid_response(struct rad_handle *, int,
-		    const struct sockaddr_in *);
+static int	 is_valid_response(struct rad_handle *, const struct sockaddr *);
 static int	 put_password_attr(struct rad_handle *, int,
 		    const void *, size_t,
 		    const struct rad_attr_options *);
@@ -67,6 +75,9 @@ static int	 split(char *, char *[], int, char *, size_
 		    const void *, size_t,
 		    const struct rad_attr_options *);
 static int	 split(char *, char *[], int, char *, size_t);
+static int	 rad_init_socket(struct rad_handle *h);
+static int	 rad_receive(struct rad_handle *h, int fd);
+static int	 rad_send_request_next(struct rad_handle *h, struct timeval *tv);
 
 static void
 clear_password(struct rad_handle *h)
@@ -78,7 +89,30 @@ clear_password(struct rad_handle *h)
 	h->pass_pos = 0;
 }
 
+#ifdef HAVE_OPENSSL
 static void
+compute_hmac_md5(const char *key, unsigned char *data, int datalen, unsigned char *output)
+{
+	unsigned int len = 16;
+
+	HMAC(EVP_md5(), key, strlen(key), (unsigned char *)data, datalen, output, &len);
+}
+
+static void
+insert_message_authenticator(struct rad_handle *h, int srv)
+{
+	const struct rad_server *srvp;
+
+	srvp = &h->servers[srv];
+	h->request[POS_MSG_AUTH] = RAD_MSG_AUTH;
+	h->request[POS_MSG_AUTH + 1] = LEN_MSG_AUTH;
+	memset(&h->request[POS_MSG_AUTH + 2], 0, LEN_MSG_AUTH_HASH);
+
+	compute_hmac_md5(srvp->secret, h->request, h->req_len, &h->request[POS_MSG_AUTH + 2]);
+}
+#endif
+
+static void
 generr(struct rad_handle *h, const char *format, ...)
 {
 	va_list		 ap;
@@ -144,20 +178,41 @@ static int
  * specified server.
  */
 static int
-is_valid_response(struct rad_handle *h, int srv,
-    const struct sockaddr_in *from)
+is_valid_response(struct rad_handle *h, const struct sockaddr *from)
 {
 	MD5_CTX ctx;
 	unsigned char md5[16];
-	const struct rad_server *srvp;
-	int len;
+	unsigned char buf[MSGSIZE];
+	const struct rad_server *srvp = NULL;
+	int len, resp_type, resp_attr_len, srv;
+	int resp_pos = 0;
+	bool found = false;
 
-	srvp = &h->servers[srv];
+	for (srv = 0; srv < h->num_servers; srv++) {
+		/* Check the source address */
+		if (from->sa_family != h->servers[srv].addr.addr.sa_family)
+			continue;
+		if (from->sa_family == AF_INET) {
+			const struct sockaddr_in *from4 = (const struct sockaddr_in *)from;
+			const struct sockaddr_in *srva4 = &h->servers[srv].addr.addr4;
+			if (from4->sin_port != srva4->sin_port ||
+			    memcmp(&from4->sin_addr, &srva4->sin_addr, sizeof(from4->sin_addr)) != 0)
+				continue;
+		} else if (from->sa_family == AF_INET6) {
+			const struct sockaddr_in6 *from6 = (const struct sockaddr_in6 *)from;
+			const struct sockaddr_in6 *srva6 = &h->servers[srv].addr.addr6;
+			if (from6->sin6_port != srva6->sin6_port ||
+			    memcmp(&from6->sin6_addr, &srva6->sin6_addr, sizeof(from6->sin6_addr)) != 0)
+				continue;
+		} else {
+			return 0;
+		}
+		srvp = &h->servers[srv];
+		break;
+	}
 
-	/* Check the source address */
-	if (from->sin_family != srvp->addr.sin_family ||
-	    from->sin_addr.s_addr != srvp->addr.sin_addr.s_addr ||
-	    from->sin_port != srvp->addr.sin_port)
+	/* Does not correspond to any of our records */
+	if (srvp == NULL)
 		return 0;
 
 	/* Check the message length */
@@ -166,6 +221,8 @@ is_valid_response(struct rad_handle *h, int srv,
 	len = h->response[POS_LENGTH] << 8 | h->response[POS_LENGTH+1];
 	if (len > h->resp_len)
 		return 0;
+	if (len > MSGSIZE)
+		return 0;
 
 	/* Check the response authenticator */
 	MD5Init(&ctx);
@@ -177,6 +234,39 @@ is_valid_response(struct rad_handle *h, int srv,
 	if (memcmp(&h->response[POS_AUTH], md5, sizeof md5) != 0)
 		return 0;
 
+#ifdef HAVE_OPENSSL
+	if (h->msg_auth &&
+	    (h->response[POS_CODE] == RAD_ACCESS_ACCEPT ||
+	     h->response[POS_CODE] == RAD_ACCESS_REJECT ||
+	     h->response[POS_CODE] == RAD_ACCESS_CHALLENGE)) {
+		resp_pos = POS_ATTRS;
+		while (true) {
+			if (resp_pos >= h->resp_len)
+				break;
+
+			resp_type = h->response[resp_pos++];
+			resp_attr_len = h->response[resp_pos++] - 2;
+
+			if (resp_type == RAD_MSG_AUTH && resp_attr_len == LEN_MSG_AUTH_HASH) {
+				found = true;
+				break;
+			}
+
+			resp_pos += resp_attr_len;
+		}
+
+		if (found) {
+			memcpy(&buf[POS_CODE], &h->response[POS_CODE], POS_AUTH - POS_CODE);
+			memcpy(&buf[POS_AUTH], &h->request[POS_AUTH], LEN_AUTH);
+			memcpy(&buf[POS_ATTRS], &h->response[POS_ATTRS], len - POS_ATTRS);
+			memset(&buf[resp_pos], 0, LEN_MSG_AUTH_HASH);
+			compute_hmac_md5(srvp->secret, buf, len, md5);
+			if (memcmp(&h->response[resp_pos], md5, LEN_MSG_AUTH_HASH) != 0)
+				return 0;
+		}
+	}
+#endif
+
 	return 1;
 }
 
@@ -277,49 +367,75 @@ rad_add_server(struct rad_handle *h, const char *host,
 rad_add_server(struct rad_handle *h, const char *host, int port,
     const char *secret, int timeout, int tries)
 {
-	struct rad_server *srvp;
+	struct addrinfo hints;
+	struct addrinfo *result, *rp;
+	int r, n = h->num_servers;
+	short p;
 
 	if (h->num_servers >= MAXSERVERS) {
 		generr(h, "Too many RADIUS servers specified");
 		return -1;
 	}
-	srvp = &h->servers[h->num_servers];
 
-	memset(&srvp->addr, 0, sizeof srvp->addr);
-	srvp->addr.sin_family = AF_INET;
-	if (!inet_aton(host, &srvp->addr.sin_addr)) {
-		struct hostent *hent;
+	memset(&hints, 0, sizeof(hints));
+	hints.ai_family   = AF_UNSPEC;
+	hints.ai_socktype = SOCK_STREAM;
+	hints.ai_flags    = 0;
+	hints.ai_protocol = 0;
 
-		if ((hent = gethostbyname(host)) == NULL) {
-			generr(h, "%s: host not found", host);
-			return -1;
-		}
-		memcpy(&srvp->addr.sin_addr, hent->h_addr,
-		    sizeof srvp->addr.sin_addr);
+	if ((r = getaddrinfo(host, NULL, &hints, &result)) != 0) {
+		generr(h, "%s: %s", host, gai_strerror(r));
+		return -1;
 	}
-	if (port != 0)
-		srvp->addr.sin_port = htons((short) port);
-	else {
+
+	if (port == 0) {
 		struct servent *sent;
 
 		if (h->type == RADIUS_AUTH)
-			srvp->addr.sin_port =
-			    (sent = getservbyname("radius", "udp")) != NULL ?
+			p = (sent = getservbyname("radius", "udp")) != NULL ?
 				sent->s_port : htons(RADIUS_PORT);
 		else
-			srvp->addr.sin_port =
-			    (sent = getservbyname("radacct", "udp")) != NULL ?
+			p = (sent = getservbyname("radacct", "udp")) != NULL ?
 				sent->s_port : htons(RADACCT_PORT);
+	} else
+		p = htons((short)port);
+
+	for (rp = result; rp != NULL; rp = rp->ai_next) {
+		struct rad_server *srvp;
+		if (n >= MAXSERVERS) {
+			generr(h, "Too many RADIUS servers specified");
+			goto err;
+		}
+		srvp = &h->servers[n];
+		memset(&srvp->addr, 0, sizeof srvp->addr);
+		if (rp->ai_family == AF_INET) {
+			memcpy(&srvp->addr, rp->ai_addr, sizeof(struct sockaddr_in));
+			srvp->addr.addr4.sin_port = p;
+		} else if (rp->ai_family == AF_INET6) {
+			memcpy(&srvp->addr, rp->ai_addr, sizeof(struct sockaddr_in6));
+			srvp->addr.addr6.sin6_port = p;
+		} else
+			continue;
+		n++;
+		srvp->timeout = timeout;
+		srvp->max_tries = tries;
+		srvp->num_tries = 0;
+		if ((srvp->secret = strdup(secret)) == NULL) {
+			generr(h, "Out of memory");
+			goto err;
+		}
 	}
-	if ((srvp->secret = strdup(secret)) == NULL) {
-		generr(h, "Out of memory");
-		return -1;
-	}
-	srvp->timeout = timeout;
-	srvp->max_tries = tries;
-	srvp->num_tries = 0;
-	h->num_servers++;
+	h->num_servers = n;
+	freeaddrinfo(result);
 	return 0;
+
+err:
+	freeaddrinfo(result);
+	while (n > h->num_servers) {
+		n--;
+		free(h->servers[n].secret);
+	}
+	return -1;
 }
 
 void
@@ -327,8 +443,10 @@ rad_close(struct rad_handle *h)
 {
 	int srv;
 
-	if (h->fd != -1)
-		close(h->fd);
+	if (h->fd4 != -1)
+		close(h->fd4);
+	if (h->fd6 != -1)
+		close(h->fd6);
 	for (srv = 0;  srv < h->num_servers;  srv++) {
 		memset(h->servers[srv].secret, 0,
 		    strlen(h->servers[srv].secret));
@@ -482,42 +600,18 @@ rad_config(struct rad_handle *h, const char *path)
 }
 
 /*
- * rad_init_send_request() must have previously been called.
+ * rad_init_socket() must have previously been called.
  * Returns:
- *   0     The application should select on *fd with a timeout of tv before
+ *   > 0   The application should select on *fd with a timeout of tv before
  *         calling rad_continue_send_request again.
  *   < 0   Failure
- *   > 0   Success
+ *     0   The application should call us again for next server
  */
 int
-rad_continue_send_request(struct rad_handle *h, int selected, int *fd,
-                          struct timeval *tv)
+static rad_send_request_next(struct rad_handle *h, struct timeval *tv)
 {
 	int n;
 
-	if (selected) {
-		struct sockaddr_in from;
-		int fromlen;
-
-		fromlen = sizeof from;
-		h->resp_len = recvfrom(h->fd, h->response,
-		    MSGSIZE, MSG_WAITALL, (struct sockaddr *)&from, &fromlen);
-		if (h->resp_len == -1) {
-#ifdef PHP_WIN32
-			generr(h, "recfrom: %d", WSAGetLastError());
-#else
-			generr(h, "recvfrom: %s", strerror(errno));
-#endif
-			return -1;
-		}
-		if (is_valid_response(h, h->srv, &from)) {
-			h->resp_len = h->response[POS_LENGTH] << 8 |
-			    h->response[POS_LENGTH+1];
-			h->resp_pos = POS_ATTRS;
-			return h->response[POS_CODE];
-		}
-	}
-
 	if (h->try == h->total_tries) {
 		generr(h, "No valid RADIUS responses received");
 		return -1;
@@ -541,38 +635,39 @@ rad_continue_send_request(struct rad_handle *h, int se
 	    || h->request[POS_CODE] == RAD_DISCONNECT_NAK)
 		/* Insert the request authenticator into the request */
 		insert_request_authenticator(h, h->srv);
-	else
+	else {
 		/* Insert the scrambled password into the request */
 		if (h->pass_pos != 0)
 			insert_scrambled_password(h, h->srv);
+#ifdef HAVE_OPENSSL
+		if (h->msg_auth)
+			insert_message_authenticator(h, h->srv);
+#endif
+	}
 
 	/* Send the request */
-	n = sendto(h->fd, h->request, h->req_len, 0,
-	    (const struct sockaddr *)&h->servers[h->srv].addr,
-	    sizeof h->servers[h->srv].addr);
-	if (n != h->req_len) {
-		if (n == -1)
-#ifdef PHP_WIN32
-			generr(h, "sendto: %d", WSAGetLastError());
-#else
-			generr(h, "sendto: %s", strerror(errno));
-#endif
-		else
-			generr(h, "sendto: short write");
-		return -1;
+	if (h->servers[h->srv].addr.addr.sa_family == AF_INET6 && h->fd6 != -1) {
+		n = sendto(h->fd6, h->request, h->req_len, 0,
+		    &h->servers[h->srv].addr.addr,
+		    sizeof h->servers[h->srv].addr.addr6);
+	} else if (h->servers[h->srv].addr.addr.sa_family == AF_INET && h->fd4 != -1) {
+		n = sendto(h->fd4, h->request, h->req_len, 0,
+		    &h->servers[h->srv].addr.addr,
+		    sizeof h->servers[h->srv].addr.addr4);
+	} else {
+		h->servers[h->srv].num_tries++;
+		return 0;
 	}
 
 	h->try++;
 	h->servers[h->srv].num_tries++;
 	tv->tv_sec = h->servers[h->srv].timeout;
 	tv->tv_usec = 0;
-	*fd = h->fd;
-
-	return 0;
+	return n != h->req_len ? 0 : 1;
 }
 
 int
-rad_create_request(struct rad_handle *h, int code)
+rad_create_request(struct rad_handle *h, int code, bool msg_auth)
 {
 	int i;
 
@@ -581,14 +676,23 @@ rad_create_request(struct rad_handle *h, int code)
 	/* Create a random authenticator */
 	for (i = 0;  i < LEN_AUTH;  i += 2) {
 		long r;
-		TSRMLS_FETCH();
-		r = php_rand(TSRMLS_C);
+		r = (zend_long) php_mt_rand();
 		h->request[POS_AUTH+i] = (unsigned char) r;
 		h->request[POS_AUTH+i+1] = (unsigned char) (r >> 8);
 	}
 	h->req_len = POS_ATTRS;
-	h->request_created = 1;    
+	h->request_created = 1;
 	clear_password(h);
+
+#ifdef HAVE_OPENSSL
+	if (msg_auth) {
+		h->msg_auth = true;
+		h->req_len += LEN_MSG_AUTH;
+	} else {
+		h->msg_auth = false;
+	}
+#endif
+
 	return 0;
 }
 
@@ -654,42 +758,92 @@ rad_get_attr(struct rad_handle *h, const void **value,
 }
 
 /*
- * Returns -1 on error, 0 to indicate no event and >0 for success
+ * Returns -1 on error, 0 on success
  */
-int
-rad_init_send_request(struct rad_handle *h, int *fd, struct timeval *tv)
+static int
+rad_init_socket(struct rad_handle *h)
 {
-	int srv;
+	int srv, tries4 = 0, tries6 = 0;
 
-	/* Make sure we have a socket to use */
-	if (h->fd == -1) {
-		struct sockaddr_in sin;
+	h->total_tries = 0;
 
-		if ((h->fd = socket(PF_INET, SOCK_DGRAM, IPPROTO_UDP)) == -1) {
+	for (srv = 0;  srv < h->num_servers;  srv++)
+		if (h->servers[srv].addr.addr.sa_family == AF_INET)
+			tries4 += h->servers[srv].max_tries;
+		else if (h->servers[srv].addr.addr.sa_family == AF_INET6)
+			tries6 += h->servers[srv].max_tries;
+
+	/* Make sure we have a IPv4 socket to use */
+	if (h->fd4 == -1 && tries4 > 0) {
+		struct sockaddr_in sin4;
+		int so;
+
+		h->fd4 = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
+		if (h->fd4 == -1) {
 #ifdef PHP_WIN32
-			generr(h, "Cannot create socket: %d", WSAGetLastError());
+			generr(h, "Cannot create ipv4 socket: %d", WSAGetLastError());
 #else
-			generr(h, "Cannot create socket: %s", strerror(errno));
+			generr(h, "Cannot create ipv4 socket: %s", strerror(errno));
 #endif
-			return -1;
+			goto done4;
 		}
-		memset(&sin, 0, sizeof sin);
-		sin.sin_family = AF_INET;
-		sin.sin_addr.s_addr = INADDR_ANY;
-		sin.sin_port = htons(0);
-		if (bind(h->fd, (const struct sockaddr *)&sin,
-		    sizeof sin) == -1) {
+
+		memset(&sin4, 0, sizeof sin4);
+		sin4.sin_family = AF_INET;
+		sin4.sin_addr.s_addr = INADDR_ANY;
+		sin4.sin_port = htons(0);
+		if (bind(h->fd4, (const struct sockaddr *)&sin4, sizeof sin4) == -1) {
 #ifdef PHP_WIN32
-			generr(h, "bind: %d", WSAGetLastError());
+			generr(h, "bind ipv4: %d", WSAGetLastError());
 #else
-			generr(h, "bind: %s", strerror(errno));
+			generr(h, "bind ipv4: %s", strerror(errno));
 #endif
-			close(h->fd);
-			h->fd = -1;
-			return -1;
+			close(h->fd4);
+			h->fd4 = -1;
+			goto done4;
 		}
+		so = fcntl(h->fd4, F_GETFL);
+		if (so != -1)
+			fcntl(h->fd4, F_SETFL, O_NONBLOCK | so);
 	}
+done4:
+	/* Make sure we have a IPv6 socket to use */
+	if (h->fd6 == -1 && tries6 > 0) {
+		struct sockaddr_in6 sin6;
+		int so = 1;
 
+		h->fd6 = socket(AF_INET6, SOCK_DGRAM, IPPROTO_UDP);
+		if (h->fd6 == -1 && errno != EAFNOSUPPORT) {
+#ifdef PHP_WIN32
+			generr(h, "Cannot create ipv6 socket: %d", WSAGetLastError());
+#else
+			generr(h, "Cannot create ipv6 socket: %s", strerror(errno));
+#endif
+			goto done6;
+		}
+
+		memset(&sin6, 0, sizeof sin6);
+		sin6.sin6_family = AF_INET6;
+		sin6.sin6_port = htons(0);
+		setsockopt(h->fd6, IPPROTO_IPV6, IPV6_V6ONLY, &so, sizeof so);
+		if (bind(h->fd6, (const struct sockaddr *)&sin6, sizeof sin6) == -1) {
+#ifdef PHP_WIN32
+			generr(h, "bind ipv6: %d", WSAGetLastError());
+#else
+			generr(h, "bind ipv6: %s", strerror(errno));
+#endif
+			close(h->fd6);
+			h->fd6 = -1;
+			goto done6;
+		}
+		so = fcntl(h->fd6, F_GETFL);
+		if (so != -1)
+			fcntl(h->fd6, F_SETFL, O_NONBLOCK | so);
+	}
+done6:
+	if (h->fd4 == -1 && h->fd6 == -1)
+		return -1;
+
 	if (h->request[POS_CODE] == RAD_ACCOUNTING_REQUEST
 	    || h->request[POS_CODE] == RAD_COA_REQUEST
 	    || h->request[POS_CODE] == RAD_COA_ACK
@@ -723,18 +877,17 @@ rad_init_send_request(struct rad_handle *h, int *fd, s
 	 * counter for each server.
 	 */
 	h->total_tries = 0;
-	for (srv = 0;  srv < h->num_servers;  srv++) {
-		h->total_tries += h->servers[srv].max_tries;
-		h->servers[srv].num_tries = 0;
-	}
+	if (h->fd4 != -1)
+		h->total_tries += tries4;
+	if (h->fd6 != -1)
+		h->total_tries += tries6;
 	if (h->total_tries == 0) {
 		generr(h, "No RADIUS servers specified");
 		return -1;
 	}
 
 	h->try = h->srv = 0;
-
-	return rad_continue_send_request(h, 0, fd, tv);
+	return 0;
 }
 
 /*
@@ -749,11 +902,11 @@ rad_auth_open(void)
 
 	h = (struct rad_handle *)malloc(sizeof(struct rad_handle));
 	if (h != NULL) {
-		TSRMLS_FETCH();
-		php_srand(time(NULL) * getpid() * (unsigned long) (php_combined_lcg(TSRMLS_C) * 10000.0) TSRMLS_CC);
-		h->fd = -1;
+		php_mt_srand(time(NULL) * getpid() * (unsigned long) (php_combined_lcg() * 10000.0));
+		h->fd4 = -1;
+		h->fd6 = -1;
 		h->num_servers = 0;
-		h->ident = php_rand(TSRMLS_C);
+		h->ident = (zend_long) php_mt_rand();
 		h->errmsg[0] = '\0';
 		memset(h->request, 0, sizeof h->request);
 		h->req_len = 0;
@@ -828,6 +981,40 @@ rad_put_string(struct rad_handle *h, int type, const c
 	return rad_put_attr(h, type, str, strlen(str), options);
 }
 
+static int
+rad_receive(struct rad_handle *h, int fd)
+{
+	union {
+		struct sockaddr_in from4;
+		struct sockaddr_in6 from6;
+	} from;
+	socklen_t fromlen;
+
+retry:
+	fromlen = sizeof from;
+	h->resp_len = recvfrom(fd, h->response,
+	    MSGSIZE, MSG_WAITALL, (struct sockaddr *)&from, &fromlen);
+	if (h->resp_len == -1) {
+		if (errno == EAGAIN || errno == EWOULDBLOCK)
+			return 0;
+		if (errno == EINTR)
+			goto retry;
+#ifdef PHP_WIN32
+		generr(h, "recfrom: %d", WSAGetLastError());
+#else
+		generr(h, "recvfrom: %s", strerror(errno));
+#endif
+		return -1;
+	}
+
+	if (is_valid_response(h, (struct sockaddr *)&from)) {
+		h->resp_len = h->response[POS_LENGTH] << 8 |
+		    h->response[POS_LENGTH+1];
+		h->resp_pos = POS_ATTRS;
+		return h->response[POS_CODE];
+	} else
+		goto retry;
+}
 /*
  * Returns the response type code on success, or -1 on failure.
  */
@@ -836,47 +1023,56 @@ rad_send_request(struct rad_handle *h)
 {
 	struct timeval timelimit;
 	struct timeval tv;
-	int fd;
 	int n;
 
-	n = rad_init_send_request(h, &fd, &tv);
+	n = rad_init_socket(h);
 
 	if (n != 0)
 		return n;
 
-	gettimeofday(&timelimit, NULL);
-	timeradd(&tv, &timelimit, &timelimit);
-
-	for ( ; ; ) {
+	while (h->try < h->total_tries) {
 		fd_set readfds;
 
+		n = rad_send_request_next(h, &tv);
+		if (++h->srv >= h->num_servers)
+			h->srv = 0;
+		if (n == -1)
+			return n;
+		else if (n == 0)
+			continue;
+
+		gettimeofday(&timelimit, NULL);
+		timeradd(&tv, &timelimit, &timelimit);
+
+wait:
 		FD_ZERO(&readfds);
-		FD_SET(fd, &readfds);
+		if (h->fd4 != -1)
+			FD_SET(h->fd4, &readfds);
+		if (h->fd6 != -1)
+			FD_SET(h->fd6, &readfds);
 
-		n = select(fd + 1, &readfds, NULL, NULL, &tv);
+		n = select((h->fd4 > h->fd6 ? h->fd4 : h->fd6) + 1, &readfds, NULL, NULL, &tv);
 
-		if (n == -1) {
+		if (n > 0) {
+			if (h->fd4 != -1 && FD_ISSET(h->fd4, &readfds))
+				if ((n = rad_receive(h, h->fd4)))
+					return n;
+			if (h->fd6 != -1 && FD_ISSET(h->fd6, &readfds))
+				if ((n = rad_receive(h, h->fd6)))
+					return n;
+		} else if (n == -1 && errno != EINTR) {
 			generr(h, "select: %s", strerror(errno));
 			return -1;
 		}
-
-		if (!FD_ISSET(fd, &readfds)) {
-			/* Compute a new timeout */
-			gettimeofday(&tv, NULL);
-			timersub(&timelimit, &tv, &tv);
-			if (tv.tv_sec > 0 || (tv.tv_sec == 0 && tv.tv_usec > 0))
-				/* Continue the select */
-				continue;
-		}
-
-		n = rad_continue_send_request(h, n, &fd, &tv);
-
-		if (n != 0)
-			return n;
-
-		gettimeofday(&timelimit, NULL);
-		timeradd(&tv, &timelimit, &timelimit);
+		/* Compute a new timeout */
+		gettimeofday(&tv, NULL);
+		timersub(&timelimit, &tv, &tv);
+		if (tv.tv_sec > 0 || (tv.tv_sec == 0 && tv.tv_usec > 0))
+			/* Continue the select */
+			goto wait;
 	}
+	generr(h, "%s", strerror(ETIMEDOUT));
+	return -1;
 }
 
 const char *
@@ -1035,7 +1231,7 @@ rad_put_vendor_attr(struct rad_handle *h, int vendor, 
 	/* OK, allocate and start building the attribute. */
 	attr = emalloc(va_len);
 	if (attr == NULL) {
-		generr(h, "malloc failure (%d bytes)", va_len);
+		generr(h, "malloc failure (%zu bytes)", va_len);
 		goto end;
 	}
 
@@ -1218,12 +1414,12 @@ rad_demangle_mppe_key(struct rad_handle *h, const void
 	*/
 	*len = *P;
 	if (*len > mlen - 1) {
-		generr(h, "Mangled data seems to be garbage %d %d", *len, mlen-1);        
+		generr(h, "Mangled data seems to be garbage %zu %lu", *len, mlen-1);        
 		return -1;
 	}
 
 	if (*len > MPPE_KEY_LEN) {
-		generr(h, "Key to long (%d) for me max. %d", *len, MPPE_KEY_LEN);        
+		generr(h, "Key to long (%zu) for me max. %d", *len, MPPE_KEY_LEN);        
 		return -1;
 	}
 
@@ -1235,14 +1431,13 @@ int rad_salt_value(struct rad_handle *h, const char *i
 {
 	char authenticator[16];
 	size_t i;
-	char intermediate[16];
+	u_char intermediate[16];
 	const char *in_pos;
 	MD5_CTX md5;
 	char *out_pos;
-	php_uint32 random;
+	uint32_t random;
 	size_t salted_len;
 	const char *secret;
-	TSRMLS_FETCH();
 
 	if (len == 0) {
 		out->len = 0;
@@ -1289,7 +1484,7 @@ int rad_salt_value(struct rad_handle *h, const char *i
 	}
 
 	/* Generate a random number to use as the salt. */
-	random = php_rand(TSRMLS_C);
+	random = (zend_long) php_mt_rand();
 
 	/* The RFC requires that the high bit of the salt be 1. Otherwise,
 	 * let's set up the header. */
@@ -1341,5 +1536,3 @@ err:
 
 	return -1;
 }
-
-/* vim: set ts=8 sw=8 noet: */
