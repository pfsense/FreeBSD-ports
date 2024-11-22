--- dhcp6_ctl.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6_ctl.c
@@ -93,32 +93,32 @@ dhcp6_ctl_init(addr, port, max, sockp)
 	hints.ai_protocol = IPPROTO_TCP;
 	error = getaddrinfo(addr, port, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		return (-1);
 	}
 	ctlsock = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
 	if (ctlsock < 0) {
-		d_printf(LOG_ERR, FNAME, "socket(control sock): %s",
+		dprintf(LOG_ERR, FNAME, "socket(control sock): %s",
 		    strerror(errno));
 		goto fail;
 	}
 	on = 1;
 	if (setsockopt(ctlsock, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on))
 	    < 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(control sock, SO_REUSEADDR: %s",
 		    strerror(errno));
 		goto fail;
 	}
 	if (bind(ctlsock, res->ai_addr, res->ai_addrlen) < 0) {
-		d_printf(LOG_ERR, FNAME, "bind(control sock): %s",
+		dprintf(LOG_ERR, FNAME, "bind(control sock): %s",
 		    strerror(errno));
 		goto fail;
 	}
 	freeaddrinfo(res);
 	if (listen(ctlsock, 1)) {
-		d_printf(LOG_ERR, FNAME, "listen(control sock): %s",
+		dprintf(LOG_ERR, FNAME, "listen(control sock): %s",
 		    strerror(errno));
 		goto fail;
 	}
@@ -126,7 +126,7 @@ dhcp6_ctl_init(addr, port, max, sockp)
 	TAILQ_INIT(&commandqueue_head);
 
 	if (max <= 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "invalid maximum number of commands (%d)", max_commands);
 		goto fail;
 	}
@@ -159,27 +159,27 @@ dhcp6_ctl_authinit(keyfile, keyinfop, digestlenp)
 	*digestlenp = MD5_DIGESTLENGTH;
 
 	if ((fp = fopen(keyfile, "r")) == NULL) {
-		d_printf(LOG_ERR, FNAME, "failed to open %s: %s", keyfile,
+		dprintf(LOG_ERR, FNAME, "failed to open %s: %s", keyfile,
 		    strerror(errno));
 		return (-1);
 	}
 	if (fgets(line, sizeof(line), fp) == NULL && ferror(fp)) {
-		d_printf(LOG_ERR, FNAME, "failed to read key file: %s",
+		dprintf(LOG_ERR, FNAME, "failed to read key file: %s",
 		    strerror(errno));
 		goto fail;
 	}
 	if ((secretlen = base64_decodestring(line, secret, sizeof(secret)))
 	    < 0) {
-		d_printf(LOG_ERR, FNAME, "failed to decode base64 string");
+		dprintf(LOG_ERR, FNAME, "failed to decode base64 string");
 		goto fail;
 	}
 	if ((ctlkey = malloc(sizeof(*ctlkey))) == NULL) {
-		d_printf(LOG_WARNING, FNAME, "failed to allocate control key");
+		dprintf(LOG_WARNING, FNAME, "failed to allocate control key");
 		goto fail;
 	}
 	memset(ctlkey, 0, sizeof(*ctlkey));
 	if ((ctlkey->secret = malloc(secretlen)) == NULL) {
-		d_printf(LOG_WARNING, FNAME, "failed to allocate secret key");
+		dprintf(LOG_WARNING, FNAME, "failed to allocate secret key");
 		goto fail;
 	}
 	ctlkey->secretlen = (size_t)secretlen;
@@ -214,24 +214,24 @@ dhcp6_ctl_acceptcommand(sl, callback)
 
 	fromlen = sizeof(from_ss);
 	if ((s = accept(sl, from, &fromlen)) < 0) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to accept control connection: %s",
 		    strerror(errno));
 		return (-1);
 	}
 
-	d_printf(LOG_DEBUG, FNAME, "accept control connection from %s",
+	dprintf(LOG_DEBUG, FNAME, "accept control connection from %s",
 	    addr2str(from));
 
 	if (max_commands <= 0) {
-		d_printf(LOG_ERR, FNAME, "command queue is not initialized");
+		dprintf(LOG_ERR, FNAME, "command queue is not initialized");
 		close(s);
 		return (-1);
 	}
 
 	new = malloc(sizeof(*new));
 	if (new == NULL) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to allocate new command context");
 		goto fail;
 	}
@@ -240,7 +240,7 @@ dhcp6_ctl_acceptcommand(sl, callback)
 	if (commands == max_commands) {
 		ctx = TAILQ_FIRST(&commandqueue_head);
 
-		d_printf(LOG_INFO, FNAME, "command queue is full. "
+		dprintf(LOG_INFO, FNAME, "command queue is full. "
 		    "drop the oldest one (fd=%d)", ctx->s);
 
 		TAILQ_REMOVE(&commandqueue_head, ctx, link);
@@ -271,7 +271,7 @@ dhcp6_ctl_closecommand(ctx)
 	free(ctx);
 
 	if (commands == 0) {
-		d_printf(LOG_ERR, FNAME, "assumption error: "
+		dprintf(LOG_ERR, FNAME, "assumption error: "
 		    "command queue is empty?");
 		exit(1);	/* XXX */
 	}
@@ -299,12 +299,12 @@ dhcp6_ctl_readcommand(read_fds)
 
 			cc = read(ctx->s, cp, resid);
 			if (cc < 0) {
-				d_printf(LOG_WARNING, FNAME, "read failed: %s",
+				dprintf(LOG_WARNING, FNAME, "read failed: %s",
 				    strerror(errno));
 				goto closecommand;
 			}
 			if (cc == 0) {
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "control channel was reset by peer");
 				goto closecommand;
 			}
@@ -330,7 +330,7 @@ dhcp6_ctl_readcommand(read_fds)
 					break;
 				}
 			} else if (ctx->input_len > sizeof(ctx->inputbuf)) {
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "too large command (%d bytes)",
 				    ctx->input_len);
 				goto closecommand;
