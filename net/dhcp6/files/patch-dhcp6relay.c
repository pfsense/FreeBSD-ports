--- dhcp6relay.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6relay.c
@@ -222,7 +222,7 @@ main(argc, argv)
 
 	relay6_init(argc, argv);
 
-	d_printf(LOG_INFO, FNAME, "dhcp6relay started");
+	dprintf(LOG_INFO, FNAME, "dhcp6relay started");
 	relay6_loop();
 
 	exit(0);
@@ -240,7 +240,7 @@ make_prefix(pstr0)
 
 	/* make a local copy for safety */
 	if (strlcpy(pstr, pstr0, sizeof (pstr)) >= sizeof (pstr)) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "prefix string too long (maybe bogus): %s", pstr0);
 		return (NULL);
 	}
@@ -250,27 +250,27 @@ make_prefix(pstr0)
 		plen = 128; /* assumes it as a host prefix */
 	else {
 		if (p[1] == '\0') {
-			d_printf(LOG_WARNING, FNAME,
+			dprintf(LOG_WARNING, FNAME,
 			    "no prefix length (ignored): %s", p + 1);
 			return (NULL);
 		}
 		plen = (int)strtoul(p + 1, &ep, 10);
 		if (*ep != '\0') {
-			d_printf(LOG_WARNING, FNAME,
+			dprintf(LOG_WARNING, FNAME,
 			    "illegal prefix length (ignored): %s", p + 1);
 			return (NULL);
 		}
 		*p = '\0';
 	}
 	if (inet_pton(AF_INET6, pstr, &paddr) != 1) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "inet_pton failed for %s", pstr);
 		return (NULL);
 	}
 
 	/* allocate a new entry */
 	if ((pent = (struct prefix_list *)malloc(sizeof (*pent))) == NULL) {
-		d_printf(LOG_WARNING, FNAME, "memory allocation failed");
+		dprintf(LOG_WARNING, FNAME, "memory allocation failed");
 		return (NULL);	/* or abort? */
 	}
 
@@ -312,14 +312,14 @@ relay6_init(int ifnum, char *iflist[])
 	hints.ai_flags = AI_PASSIVE;
 	error = getaddrinfo(serveraddr, DH6PORT_UPSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		goto failexit;
 	}
 	if (res->ai_family != PF_INET6 ||
 	    res->ai_addrlen < sizeof (sa6_server)) {
 		/* this should be impossible, but check for safety */
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "getaddrinfo returned a bogus address: %s",
 		    strerror(errno));
 		goto failexit;
@@ -335,7 +335,7 @@ relay6_init(int ifnum, char *iflist[])
 	rmh.msg_iovlen = 1;
 	rmsgctllen = CMSG_SPACE(sizeof (struct in6_pktinfo));
 	if ((rmsgctlbuf = (char *)malloc(rmsgctllen)) == NULL) {
-		d_printf(LOG_ERR, FNAME, "memory allocation failed");
+		dprintf(LOG_ERR, FNAME, "memory allocation failed");
 		goto failexit;
 	}
 
@@ -349,13 +349,13 @@ relay6_init(int ifnum, char *iflist[])
 	hints.ai_flags = AI_PASSIVE;
 	error = getaddrinfo(NULL, DH6PORT_UPSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		goto failexit;
 	}
 	csock = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
 	if (csock < 0) {
-		d_printf(LOG_ERR, FNAME, "socket(csock): %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "socket(csock): %s", strerror(errno));
 		goto failexit;
 	}
 	if (csock > maxfd)
@@ -363,20 +363,20 @@ relay6_init(int ifnum, char *iflist[])
 	on = 1;
 	if (setsockopt(csock, SOL_SOCKET, SO_REUSEPORT,
 	    &on, sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(csock, SO_REUSEPORT): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(csock, SO_REUSEPORT): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #ifdef IPV6_V6ONLY
 	if (setsockopt(csock, IPPROTO_IPV6, IPV6_V6ONLY,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(csock, IPV6_V6ONLY): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(csock, IPV6_V6ONLY): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #endif
 	if (bind(csock, res->ai_addr, res->ai_addrlen) < 0) {
-		d_printf(LOG_ERR, FNAME, "bind(csock): %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "bind(csock): %s", strerror(errno));
 		goto failexit;
 	}
 	freeaddrinfo(res);
@@ -384,14 +384,14 @@ relay6_init(int ifnum, char *iflist[])
 #ifdef IPV6_RECVPKTINFO
 	if (setsockopt(csock, IPPROTO_IPV6, IPV6_RECVPKTINFO,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(IPV6_RECVPKTINFO): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(IPV6_RECVPKTINFO): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #else
 	if (setsockopt(csock, IPPROTO_IPV6, IPV6_PKTINFO,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(IPV6_PKTINFO): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(IPV6_PKTINFO): %s",
 		    strerror(errno));
 		goto failexit;
 	}
@@ -400,7 +400,7 @@ relay6_init(int ifnum, char *iflist[])
 	hints.ai_flags = 0;
 	error = getaddrinfo(DH6ADDR_ALLAGENT, 0, &hints, &res2);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		goto failexit;
 	}
@@ -416,21 +416,21 @@ relay6_init(int ifnum, char *iflist[])
 
 		ifd = (struct ifid_list *)malloc(sizeof (*ifd));
 		if (ifd == NULL) {
-			d_printf(LOG_WARNING, FNAME,
+			dprintf(LOG_WARNING, FNAME,
 			    "memory allocation failed");
 			goto failexit;
 		}
 		memset(ifd, 0, sizeof (*ifd));
 		ifd->ifid = if_nametoindex(ifp);
 		if (ifd->ifid == 0) {
-			d_printf(LOG_ERR, FNAME, "invalid interface %s", ifp);
+			dprintf(LOG_ERR, FNAME, "invalid interface %s", ifp);
 			goto failexit;
 		}
 		mreq6.ipv6mr_interface = ifd->ifid;
 
 		if (setsockopt(csock, IPPROTO_IPV6, IPV6_JOIN_GROUP,
 		    &mreq6, sizeof (mreq6))) {
-			d_printf(LOG_ERR, FNAME,
+			dprintf(LOG_ERR, FNAME,
 			    "setsockopt(csock, IPV6_JOIN_GROUP): %s",
 			     strerror(errno));
 			goto failexit;
@@ -445,7 +445,7 @@ relay6_init(int ifnum, char *iflist[])
 	 */
 	relayifid = if_nametoindex(relaydevice);
 	if (relayifid == 0)
-		d_printf(LOG_ERR, FNAME, "invalid interface %s", relaydevice);
+		dprintf(LOG_ERR, FNAME, "invalid interface %s", relaydevice);
 	/*
 	 * We are not really sure if we need to listen on the downstream
 	 * port to receive packets from servers.  We'll need to clarify the
@@ -454,14 +454,14 @@ relay6_init(int ifnum, char *iflist[])
 	hints.ai_flags = AI_PASSIVE;
 	error = getaddrinfo(boundaddr, DH6PORT_DOWNSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		goto failexit;
 	}
 	memcpy(&sa6_client, res->ai_addr, sizeof (sa6_client));
 	ssock = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
 	if (ssock < 0) {
-		d_printf(LOG_ERR, FNAME, "socket(outsock): %s",
+		dprintf(LOG_ERR, FNAME, "socket(outsock): %s",
 		    strerror(error));
 		goto failexit;
 	}
@@ -474,7 +474,7 @@ relay6_init(int ifnum, char *iflist[])
 	 */
 	if (setsockopt(ssock, SOL_SOCKET, SO_REUSEPORT,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(ssock, SO_REUSEPORT): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(ssock, SO_REUSEPORT): %s",
 		    strerror(errno));
 		goto failexit;
 	}
@@ -482,13 +482,13 @@ relay6_init(int ifnum, char *iflist[])
 #ifdef IPV6_V6ONLY
 	if (setsockopt(ssock, IPPROTO_IPV6, IPV6_V6ONLY,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(ssock, IPV6_V6ONLY): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(ssock, IPV6_V6ONLY): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #endif
 	if (bind(ssock, res->ai_addr, res->ai_addrlen) < 0) {
-		d_printf(LOG_ERR, FNAME, "bind(ssock): %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "bind(ssock): %s", strerror(errno));
 		goto failexit;
 	}
 	freeaddrinfo(res);
@@ -497,21 +497,21 @@ relay6_init(int ifnum, char *iflist[])
 #ifdef IPV6_RECVPKTINFO
 	if (setsockopt(ssock, IPPROTO_IPV6, IPV6_RECVPKTINFO,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(IPV6_RECVPKTINFO): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(IPV6_RECVPKTINFO): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #else
 	if (setsockopt(ssock, IPPROTO_IPV6, IPV6_PKTINFO,
 	    &on, sizeof (on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(IPV6_PKTINFO): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(IPV6_PKTINFO): %s",
 		    strerror(errno));
 		goto failexit;
 	}
 #endif
 
 	if (signal(SIGTERM, relay6_signal) == SIG_ERR) {
-		d_printf(LOG_WARNING, FNAME, "failed to set signal: %s",
+		dprintf(LOG_WARNING, FNAME, "failed to set signal: %s",
 		    strerror(errno));
 		exit(1);
 	}
@@ -599,15 +599,15 @@ relay6_recv(s, fromclient)
 	rmh.msg_namelen = sizeof (from);
 
 	if ((len = recvmsg(s, &rmh, 0)) < 0) {
-		d_printf(LOG_WARNING, FNAME, "recvmsg: %s", strerror(errno));
+		dprintf(LOG_WARNING, FNAME, "recvmsg: %s", strerror(errno));
 		return;
 	}
 
-	d_printf(LOG_DEBUG, FNAME, "from %s, size %d",
+	dprintf(LOG_DEBUG, FNAME, "from %s, size %d",
 	    addr2str((struct sockaddr *)&from), len);
 
 	if (((struct sockaddr *)&from)->sa_family != AF_INET6) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "non-IPv6 packet is received (AF %d) ",
 		    ((struct sockaddr *)&from)->sa_family);
 		return;
@@ -626,7 +626,7 @@ relay6_recv(s, fromclient)
 		}
 	}
 	if (pi == NULL) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to get the arrival interface");
 		return;
 	}
@@ -643,7 +643,7 @@ relay6_recv(s, fromclient)
 	if (ifd == NULL && pi->ipi6_ifindex != relayifid)
 		return;
 	if (if_indextoname(pi->ipi6_ifindex, ifname) == NULL) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "if_indextoname(id = %d): %s",
 		    pi->ipi6_ifindex, strerror(errno));
 		return;
@@ -651,12 +651,12 @@ relay6_recv(s, fromclient)
 
 	/* packet validation */
 	if (len < sizeof (*dh6)) {
-		d_printf(LOG_INFO, FNAME, "short packet (%d bytes)", len);
+		dprintf(LOG_INFO, FNAME, "short packet (%d bytes)", len);
 		return;
 	}
 
 	dh6 = (struct dhcp6 *)rdatabuf;
-	d_printf(LOG_DEBUG, FNAME, "received %s from %s",
+	dprintf(LOG_DEBUG, FNAME, "received %s from %s",
 	    dhcp6msgstr(dh6->dh6_msgtype), addr2str((struct sockaddr *)&from));
 
 	/*
@@ -688,7 +688,7 @@ relay6_recv(s, fromclient)
 			    (struct sockaddr *)&from);
 			break;
 		default:
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "unexpected message (%s) on the client side "
 			    "from %s", dhcp6msgstr(dh6->dh6_msgtype),
 			    addr2str((struct sockaddr *)&from));
@@ -696,7 +696,7 @@ relay6_recv(s, fromclient)
 		}
 	} else {
 		if (dh6->dh6_msgtype != DH6_RELAY_REPLY) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "unexpected message (%s) on the server side"
 			    "from %s", dhcp6msgstr(dh6->dh6_msgtype),
 			    addr2str((struct sockaddr *)&from));
@@ -781,7 +781,7 @@ relay_to_server(dh6, len, from, ifname, ifid)
 
 	/* Relay message */
 	if ((optinfo.relaymsg_msg = malloc(len)) == NULL) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to allocate memory to copy the original packet: "
 		    "%s", strerror(errno));
 		goto out;
@@ -791,7 +791,7 @@ relay_to_server(dh6, len, from, ifname, ifid)
 
 	/* Interface-id.  We always use this option. */
 	if ((optinfo.ifidopt_id = malloc(sizeof (ifid))) == NULL) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to allocate memory for IFID: %s", strerror(errno));
 		goto out;
 	}
@@ -817,7 +817,7 @@ relay_to_server(dh6, len, from, ifname, ifid)
 			break;
 	}
 	if (p == NULL) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to find a global address on %s", ifname);
 
 		/*
@@ -842,7 +842,7 @@ relay_to_server(dh6, len, from, ifname, ifid)
 		 * [RFC3315 Section 20.1.2]
 		 */
 		if (dh6relay0->dh6relay_hcnt >= DHCP6_RELAY_HOP_COUNT_LIMIT) {
-			d_printf(LOG_INFO, FNAME, "too many relay forwardings");
+			dprintf(LOG_INFO, FNAME, "too many relay forwardings");
 			goto out;
 		}
 
@@ -865,7 +865,7 @@ relay_to_server(dh6, len, from, ifname, ifid)
 	    (struct dhcp6opt *)(dh6relay + 1),
 	    (struct dhcp6opt *)(relaybuf + sizeof (relaybuf)),
 	    &optinfo)) < 0) {
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "failed to construct relay options");
 		goto out;
 	}
@@ -886,22 +886,22 @@ relay_to_server(dh6, len, from, ifname, ifid)
 		pktinfo.ipi6_ifindex = relayifid;
 		if (make_msgcontrol(&mh, ctlbuf, sizeof (ctlbuf),
 		    &pktinfo, mhops)) {
-			d_printf(LOG_WARNING, FNAME,
+			dprintf(LOG_WARNING, FNAME,
 			    "failed to make message control data");
 			goto out;
 		}
 	}
 
 	if ((cc = sendmsg(ssock, &mh, 0)) < 0) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "sendmsg %s failed: %s",
 		    addr2str((struct sockaddr *)&sa6_server), strerror(errno));
 	} else if (cc != relaylen) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to send a complete packet to %s",
 		    addr2str((struct sockaddr *)&sa6_server));
 	} else {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "relay a message to a server %s",
 		    addr2str((struct sockaddr *)&sa6_server));
 	}
@@ -928,7 +928,7 @@ relay_to_client(dh6relay, len, from)
 	static struct iovec iov[2];
 	char ctlbuf[CMSG_SPACE(sizeof (struct in6_pktinfo))];
 
-	d_printf(LOG_DEBUG, FNAME,
+	dprintf(LOG_DEBUG, FNAME,
 	    "dhcp6 relay reply: hop=%d, linkaddr=%s, peeraddr=%s",
 	    dh6relay->dh6relay_hcnt,
 	    in6addr2str(&dh6relay->dh6relay_linkaddr, 0),
@@ -940,20 +940,20 @@ relay_to_client(dh6relay, len, from)
 	dhcp6_init_options(&optinfo);
 	if (dhcp6_get_options((struct dhcp6opt *)(dh6relay + 1),
 	    (struct dhcp6opt *)((char *)dh6relay + len), &optinfo) < 0) {
-		d_printf(LOG_INFO, FNAME, "failed to parse options");
+		dprintf(LOG_INFO, FNAME, "failed to parse options");
 		return;
 	}
 
 	/* A relay reply message must include a relay message option */
 	if (optinfo.relaymsg_msg == NULL) {
-		d_printf(LOG_INFO, FNAME, "relay reply message from %s "
+		dprintf(LOG_INFO, FNAME, "relay reply message from %s "
 		    "without a relay message", addr2str(from));
 		goto out;
 	}
 
 	/* minimum validation for the inner message */
 	if (optinfo.relaymsg_len < sizeof (struct dhcp6)) {
-		d_printf(LOG_INFO, FNAME, "short relay message from %s",
+		dprintf(LOG_INFO, FNAME, "short relay message from %s",
 		    addr2str(from));
 		goto out;
 	}
@@ -965,7 +965,7 @@ relay_to_client(dh6relay, len, from)
 	ifid = 0;
 	if (optinfo.ifidopt_id) {
 		if (optinfo.ifidopt_len != sizeof (ifid)) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "unexpected length (%d) for Interface ID from %s",
 			    optinfo.ifidopt_len, addr2str(from));
 			goto out;
@@ -975,13 +975,13 @@ relay_to_client(dh6relay, len, from)
 
 			/* validation for ID */
 			if ((if_indextoname(ifid, ifnamebuf)) == NULL) {
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "invalid interface ID: %x", ifid);
 				goto out;
 			}
 		}
 	} else {
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "Interface ID is not included from %s", addr2str(from));
 		/*
 		 * the responding server should be buggy, but we deal with it.
@@ -999,7 +999,7 @@ relay_to_client(dh6relay, len, from)
 	}
 
 	if (ifid == 0) {
-		d_printf(LOG_INFO, FNAME, "failed to determine relay link");
+		dprintf(LOG_INFO, FNAME, "failed to determine relay link");
 		goto out;
 	}
 
@@ -1030,22 +1030,22 @@ relay_to_client(dh6relay, len, from)
 	memset(&pktinfo, 0, sizeof (pktinfo));
 	pktinfo.ipi6_ifindex = ifid;
 	if (make_msgcontrol(&mh, ctlbuf, sizeof (ctlbuf), &pktinfo, 0)) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to make message control data");
 		goto out;
 	}
 
 	/* send packet */
 	if ((cc = sendmsg(csock, &mh, 0)) < 0) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "sendmsg to %s failed: %s",
 		    addr2str((struct sockaddr *)&peer), strerror(errno));
 	} else if (cc != optinfo.relaymsg_len) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to send a complete packet to %s",
 		    addr2str((struct sockaddr *)&peer));
 	} else {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "relay a message to a client %s",
 		    addr2str((struct sockaddr *)&peer));
 	}
