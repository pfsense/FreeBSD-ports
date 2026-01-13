--- dhcp6s.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6s.c
@@ -311,7 +311,7 @@ main(argc, argv)
 		exit(1);
 
 	if ((cfparse(conffile)) != 0) {
-		d_printf(LOG_ERR, FNAME, "failed to parse configuration file");
+		dprintf(LOG_ERR, FNAME, "failed to parse configuration file");
 		exit(1);
 	}
 
@@ -330,7 +330,7 @@ main(argc, argv)
 	/* prohibit a mixture of old and new style of DNS server config */
 	if (!TAILQ_EMPTY(&arg_dnslist)) {
 		if (!TAILQ_EMPTY(&dnslist)) {
-			d_printf(LOG_INFO, FNAME, "do not specify DNS servers "
+			dprintf(LOG_INFO, FNAME, "do not specify DNS servers "
 			    "both by command line and by configuration file.");
 			exit(1);
 		}
@@ -369,24 +369,24 @@ server6_init()
 
 	TAILQ_INIT(&dhcp6_binding_head);
 	if (lease_init() != 0) {
-		d_printf(LOG_ERR, FNAME, "failed to initialize the lease table");
+		dprintf(LOG_ERR, FNAME, "failed to initialize the lease table");
 		exit(1);
 	}
 
 	ifidx = if_nametoindex(device);
 	if (ifidx == 0) {
-		d_printf(LOG_ERR, FNAME, "invalid interface %s", device);
+		dprintf(LOG_ERR, FNAME, "invalid interface %s", device);
 		exit(1);
 	}
 
 	/* get our DUID */
 	if (get_duid(DUID_FILE, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to get a DUID");
+		dprintf(LOG_ERR, FNAME, "failed to get a DUID");
 		exit(1);
 	}
 
 	if (dhcp6_ctl_authinit(ctlkeyfile, &ctlkey, &ctldigestlen) != 0) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to initialize control message authentication");
 		/* run the server anyway */
 	}
@@ -398,7 +398,7 @@ server6_init()
 	rmh.msg_iovlen = 1;
 	rmsgctllen = CMSG_SPACE(sizeof(struct in6_pktinfo));
 	if ((rmsgctlbuf = (char *)malloc(rmsgctllen)) == NULL) {
-		d_printf(LOG_ERR, FNAME, "memory allocation failed");
+		dprintf(LOG_ERR, FNAME, "memory allocation failed");
 		exit(1);
 	}
 
@@ -410,32 +410,32 @@ server6_init()
 	hints.ai_flags = AI_PASSIVE;
 	error = getaddrinfo(NULL, DH6PORT_UPSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
 	insock = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
 	if (insock < 0) {
-		d_printf(LOG_ERR, FNAME, "socket(insock): %s",
+		dprintf(LOG_ERR, FNAME, "socket(insock): %s",
 		    strerror(errno));
 		exit(1);
 	}
 	if (setsockopt(insock, SOL_SOCKET, SO_REUSEPORT, &on,
 		       sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(insock, SO_REUSEPORT): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(insock, SO_REUSEPORT): %s",
 		    strerror(errno));
 		exit(1);
 	}
 	if (setsockopt(insock, SOL_SOCKET, SO_REUSEADDR, &on,
 		       sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME, "setsockopt(insock, SO_REUSEADDR): %s",
+		dprintf(LOG_ERR, FNAME, "setsockopt(insock, SO_REUSEADDR): %s",
 		    strerror(errno));
 		exit(1);
 	}
 #ifdef IPV6_RECVPKTINFO
 	if (setsockopt(insock, IPPROTO_IPV6, IPV6_RECVPKTINFO, &on,
 		       sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(inbound, IPV6_RECVPKTINFO): %s",
 		    strerror(errno));
 		exit(1);
@@ -443,7 +443,7 @@ server6_init()
 #else
 	if (setsockopt(insock, IPPROTO_IPV6, IPV6_PKTINFO, &on,
 		       sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(inbound, IPV6_PKTINFO): %s",
 		    strerror(errno));
 		exit(1);
@@ -452,13 +452,13 @@ server6_init()
 #ifdef IPV6_V6ONLY
 	if (setsockopt(insock, IPPROTO_IPV6, IPV6_V6ONLY,
 	    &on, sizeof(on)) < 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(inbound, IPV6_V6ONLY): %s", strerror(errno));
 		exit(1);
 	}
 #endif
 	if (bind(insock, res->ai_addr, res->ai_addrlen) < 0) {
-		d_printf(LOG_ERR, FNAME, "bind(insock): %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "bind(insock): %s", strerror(errno));
 		exit(1);
 	}
 	freeaddrinfo(res);
@@ -466,7 +466,7 @@ server6_init()
 	hints.ai_flags = 0;
 	error = getaddrinfo(DH6ADDR_ALLAGENT, DH6PORT_UPSTREAM, &hints, &res2);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
@@ -477,7 +477,7 @@ server6_init()
 	    sizeof(mreq6.ipv6mr_multiaddr));
 	if (setsockopt(insock, IPPROTO_IPV6, IPV6_JOIN_GROUP,
 	    &mreq6, sizeof(mreq6))) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(insock, IPV6_JOIN_GROUP): %s",
 		    strerror(errno));
 		exit(1);
@@ -488,7 +488,7 @@ server6_init()
 	error = getaddrinfo(DH6ADDR_ALLSERVER, DH6PORT_UPSTREAM,
 			    &hints, &res2);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
@@ -499,7 +499,7 @@ server6_init()
 	    sizeof(mreq6.ipv6mr_multiaddr));
 	if (setsockopt(insock, IPPROTO_IPV6, IPV6_JOIN_GROUP,
 	    &mreq6, sizeof(mreq6))) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(insock, IPV6_JOIN_GROUP): %s",
 		    strerror(errno));
 		exit(1);
@@ -509,20 +509,20 @@ server6_init()
 	hints.ai_flags = 0;
 	error = getaddrinfo(NULL, DH6PORT_DOWNSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
 	outsock = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
 	if (outsock < 0) {
-		d_printf(LOG_ERR, FNAME, "socket(outsock): %s",
+		dprintf(LOG_ERR, FNAME, "socket(outsock): %s",
 		    strerror(errno));
 		exit(1);
 	}
 	/* set outgoing interface of multicast packets for DHCP reconfig */
 	if (setsockopt(outsock, IPPROTO_IPV6, IPV6_MULTICAST_IF,
 	    &ifidx, sizeof(ifidx)) < 0) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "setsockopt(outsock, IPV6_MULTICAST_IF): %s",
 		    strerror(errno));
 		exit(1);
@@ -530,7 +530,7 @@ server6_init()
 #if !defined(__linux__) && !defined(__sun__)
 	/* make the socket write-only */
 	if (shutdown(outsock, 0)) {
-		d_printf(LOG_ERR, FNAME, "shutdown(outbound, 0): %s",
+		dprintf(LOG_ERR, FNAME, "shutdown(outbound, 0): %s",
 		    strerror(errno));
 		exit(1);
 	}
@@ -543,7 +543,7 @@ server6_init()
 	hints.ai_protocol = IPPROTO_UDP;
 	error = getaddrinfo("::", DH6PORT_DOWNSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
@@ -558,7 +558,7 @@ server6_init()
 	hints.ai_protocol = IPPROTO_UDP;
 	error = getaddrinfo("::", DH6PORT_UPSTREAM, &hints, &res);
 	if (error) {
-		d_printf(LOG_ERR, FNAME, "getaddrinfo: %s",
+		dprintf(LOG_ERR, FNAME, "getaddrinfo: %s",
 		    gai_strerror(error));
 		exit(1);
 	}
@@ -569,16 +569,16 @@ server6_init()
 
 	/* set up control socket */
 	if (ctlkey == NULL)
-		d_printf(LOG_NOTICE, FNAME, "skip opening control port");
+		dprintf(LOG_NOTICE, FNAME, "skip opening control port");
 	else if (dhcp6_ctl_init(ctladdr, ctlport,
 	    DHCP6CTL_DEF_COMMANDQUEUELEN, &ctlsock)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to initialize control channel");
 		exit(1);
 	}
 
 	if (signal(SIGTERM, server6_signal) == SIG_ERR) {
-		d_printf(LOG_WARNING, FNAME, "failed to set signal: %s",
+		dprintf(LOG_WARNING, FNAME, "failed to set signal: %s",
 		    strerror(errno));
 		exit(1);
 	}
@@ -622,7 +622,7 @@ server6_mainloop()
 		switch (ret) {
 		case -1:
 			if (errno != EINTR) {
-				d_printf(LOG_ERR, FNAME, "select: %s",
+				dprintf(LOG_ERR, FNAME, "select: %s",
 				    strerror(errno));
 				exit(1);
 			}
@@ -709,31 +709,31 @@ server6_do_ctlcommand(buf, len)
 	commandlen = (int)(ntohs(ctlhead->len));
 	version = ntohs(ctlhead->version);
 	if (len != sizeof(struct dhcp6ctl) + commandlen) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "assumption failure: command length mismatch");
 		return (DHCP6CTL_R_FAILURE);
 	}
 
 	/* replay protection and message authentication */
 	if ((now = time(NULL)) < 0) {
-		d_printf(LOG_ERR, FNAME, "failed to get current time: %s",
+		dprintf(LOG_ERR, FNAME, "failed to get current time: %s",
 		    strerror(errno));
 		return (DHCP6CTL_R_FAILURE);
 	}
 	ts0 = (u_int32_t)now;
 	ts = ntohl(ctlhead->timestamp);
 	if (ts + CTLSKEW < ts0 || (ts - CTLSKEW) > ts0) {
-		d_printf(LOG_INFO, FNAME, "timestamp is out of range");
+		dprintf(LOG_INFO, FNAME, "timestamp is out of range");
 		return (DHCP6CTL_R_FAILURE);
 	}
 
 	if (ctlkey == NULL) {	/* should not happen!! */
-		d_printf(LOG_ERR, FNAME, "no secret key for control channel");
+		dprintf(LOG_ERR, FNAME, "no secret key for control channel");
 		return (DHCP6CTL_R_FAILURE);
 	}
 	if (dhcp6_verify_mac(buf, len, DHCP6CTL_AUTHPROTO_UNDEF,
 	    DHCP6CTL_AUTHALG_HMACMD5, sizeof(*ctlhead), ctlkey) != 0) {
-		d_printf(LOG_INFO, FNAME, "authentication failure");
+		dprintf(LOG_INFO, FNAME, "authentication failure");
 		return (DHCP6CTL_R_FAILURE);
 	}
 
@@ -741,14 +741,14 @@ server6_do_ctlcommand(buf, len)
 	commandlen -= ctldigestlen;
 
 	if (version > DHCP6CTL_VERSION) {
-		d_printf(LOG_INFO, FNAME, "unsupported version: %d", version);
+		dprintf(LOG_INFO, FNAME, "unsupported version: %d", version);
 		return (DHCP6CTL_R_FAILURE);
 	}
 
 	switch (command) {
 	case DHCP6CTL_COMMAND_RELOAD:
 		if (commandlen != 0) {
-			d_printf(LOG_INFO, FNAME, "invalid command length "
+			dprintf(LOG_INFO, FNAME, "invalid command length "
 			    "for reload: %d", commandlen);
 			return (DHCP6CTL_R_DONE);
 		}
@@ -756,7 +756,7 @@ server6_do_ctlcommand(buf, len)
 		break;
 	case DHCP6CTL_COMMAND_STOP:
 		if (commandlen != 0) {
-			d_printf(LOG_INFO, FNAME, "invalid command length "
+			dprintf(LOG_INFO, FNAME, "invalid command length "
 			    "for stop: %d", commandlen);
 			return (DHCP6CTL_R_DONE);
 		}
@@ -766,7 +766,7 @@ server6_do_ctlcommand(buf, len)
 		if (get_val32(&bp, &commandlen, &p32))
 			return (DHCP6CTL_R_FAILURE);
 		if (p32 != DHCP6CTL_BINDING) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "unknown remove target: %ul", p32);
 			return (DHCP6CTL_R_FAILURE);
 		}
@@ -774,7 +774,7 @@ server6_do_ctlcommand(buf, len)
 		if (get_val32(&bp, &commandlen, &p32))
 			return (DHCP6CTL_R_FAILURE);
 		if (p32 != DHCP6CTL_BINDING_IA) {
-			d_printf(LOG_INFO, FNAME, "unknown binding type: %ul",
+			dprintf(LOG_INFO, FNAME, "unknown binding type: %ul",
 			    p32);
 			return (DHCP6CTL_R_FAILURE);
 		}
@@ -783,7 +783,7 @@ server6_do_ctlcommand(buf, len)
 			return (DHCP6CTL_R_FAILURE);
 		if (ntohl(iaspec.type) != DHCP6CTL_IA_PD &&
 		    ntohl(iaspec.type) != DHCP6CTL_IA_NA) {
-			d_printf(LOG_INFO, FNAME, "unknown IA type: %ul",
+			dprintf(LOG_INFO, FNAME, "unknown IA type: %ul",
 			    ntohl(iaspec.type));
 			return (DHCP6CTL_R_FAILURE);
 		}
@@ -791,7 +791,7 @@ server6_do_ctlcommand(buf, len)
 		duidlen = ntohl(iaspec.duidlen);
 
 		if (duidlen > commandlen) {
-			d_printf(LOG_INFO, FNAME, "DUID length mismatch");
+			dprintf(LOG_INFO, FNAME, "DUID length mismatch");
 			return (DHCP6CTL_R_FAILURE);
 		}
 
@@ -804,7 +804,7 @@ server6_do_ctlcommand(buf, len)
 			binding = find_binding(&duid, DHCP6_BINDING_IA,
 			    DHCP6_LISTVAL_IANA, iaid);
 			if (binding == NULL) {
-				d_printf(LOG_INFO, FNAME, "no such binding");
+				dprintf(LOG_INFO, FNAME, "no such binding");
 				return (DHCP6CTL_R_FAILURE);
 			}
 		}
@@ -812,7 +812,7 @@ server6_do_ctlcommand(buf, len)
 		    
 		break;
 	default:
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "unknown control command: %d (len=%d)",
 		    (int)command, commandlen);
 		return (DHCP6CTL_R_FAILURE);
@@ -826,12 +826,12 @@ server6_reload()
 {
 	/* reload the configuration file */
 	if (cfparse(conffile) != 0) {
-		d_printf(LOG_WARNING, FNAME,
+		dprintf(LOG_WARNING, FNAME,
 		    "failed to reload configuration file");
 		return;
 	}
 
-	d_printf(LOG_NOTICE, FNAME, "server reloaded");
+	dprintf(LOG_NOTICE, FNAME, "server reloaded");
 
 	return;
 }
@@ -841,7 +841,7 @@ server6_stop()
 {
 	/* Right now, we simply stop running */
 
-	d_printf(LOG_NOTICE, FNAME, "exiting");
+	dprintf(LOG_NOTICE, FNAME, "exiting");
 
 	exit (0);
 }
@@ -880,7 +880,7 @@ server6_recv(s)
 	mhdr.msg_controllen = sizeof(cmsgbuf);
 
 	if ((len = recvmsg(insock, &mhdr, 0)) < 0) {
-		d_printf(LOG_ERR, FNAME, "recvmsg: %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "recvmsg: %s", strerror(errno));
 		return;
 	}
 	fromlen = mhdr.msg_namelen;
@@ -894,7 +894,7 @@ server6_recv(s)
 		}
 	}
 	if (pi == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to get packet info");
+		dprintf(LOG_NOTICE, FNAME, "failed to get packet info");
 		return;
 	}
 	/*
@@ -905,7 +905,7 @@ server6_recv(s)
 	if (pi->ipi6_ifindex != ifidx)
 		return;
 	if ((ifp = find_ifconfbyid((unsigned int)pi->ipi6_ifindex)) == NULL) {
-		d_printf(LOG_INFO, FNAME, "unexpected interface (%d)",
+		dprintf(LOG_INFO, FNAME, "unexpected interface (%d)",
 		    (unsigned int)pi->ipi6_ifindex);
 		return;
 	}
@@ -913,11 +913,11 @@ server6_recv(s)
 	dh6 = (struct dhcp6 *)rdatabuf;
 
 	if (len < sizeof(*dh6)) {
-		d_printf(LOG_INFO, FNAME, "short packet (%d bytes)", len);
+		dprintf(LOG_INFO, FNAME, "short packet (%d bytes)", len);
 		return;
 	}
 
-	d_printf(LOG_DEBUG, FNAME, "received %s from %s",
+	dprintf(LOG_DEBUG, FNAME, "received %s from %s",
 	    dhcp6msgstr(dh6->dh6_msgtype),
 	    addr2str((struct sockaddr *)&from));
 
@@ -932,7 +932,7 @@ server6_recv(s)
 	    dh6->dh6_msgtype == DH6_CONFIRM ||
 	    dh6->dh6_msgtype == DH6_REBIND ||
 	    dh6->dh6_msgtype == DH6_INFORM_REQ)) {
-		d_printf(LOG_INFO, FNAME, "invalid unicast message");
+		dprintf(LOG_INFO, FNAME, "invalid unicast message");
 		return;
 	}
 
@@ -942,7 +942,7 @@ server6_recv(s)
 	 * reject them here.
 	 */
 	if (dh6->dh6_msgtype == DH6_RELAY_REPLY) {
-		d_printf(LOG_INFO, FNAME, "relay reply message from %s",
+		dprintf(LOG_INFO, FNAME, "relay reply message from %s",
 		    addr2str((struct sockaddr *)&from));
 		return;
 		
@@ -964,7 +964,7 @@ server6_recv(s)
 	dhcp6_init_options(&optinfo);
 	if (dhcp6_get_options((struct dhcp6opt *)(dh6 + 1),
 	    optend, &optinfo) < 0) {
-		d_printf(LOG_INFO, FNAME, "failed to parse options");
+		dprintf(LOG_INFO, FNAME, "failed to parse options");
 		goto end;
 	}
 
@@ -1002,7 +1002,7 @@ server6_recv(s)
 		    (struct sockaddr *)&from, fromlen, &relayinfohead);
 		break;
 	default:
-		d_printf(LOG_INFO, FNAME, "unknown or unsupported msgtype (%s)",
+		dprintf(LOG_INFO, FNAME, "unknown or unsupported msgtype (%s)",
 		    dhcp6msgstr(dh6->dh6_msgtype));
 		break;
 	}
@@ -1047,11 +1047,11 @@ process_relayforw(dh6p, optendp, relayinfohead, from)
   again:
 	len = (void *)optend - (void *)dh6relay;
 	if (len < sizeof (*dh6relay)) {
-		d_printf(LOG_INFO, FNAME, "short relay message from %s",
+		dprintf(LOG_INFO, FNAME, "short relay message from %s",
 		    addr2str(from));
 		return (-1);
 	}
-	d_printf(LOG_DEBUG, FNAME,
+	dprintf(LOG_DEBUG, FNAME,
 	    "dhcp6 relay: hop=%d, linkaddr=%s, peeraddr=%s",
 	    dh6relay->dh6relay_hcnt,
 	    in6addr2str(&dh6relay->dh6relay_linkaddr, 0),
@@ -1063,13 +1063,13 @@ process_relayforw(dh6p, optendp, relayinfohead, from)
 	dhcp6_init_options(&optinfo);
 	if (dhcp6_get_options((struct dhcp6opt *)(dh6relay + 1),
 	    optend, &optinfo) < 0) {
-		d_printf(LOG_INFO, FNAME, "failed to parse options");
+		dprintf(LOG_INFO, FNAME, "failed to parse options");
 		return (-1);
 	}
 
 	/* A relay forward message must include a relay message option */
 	if (optinfo.relaymsg_msg == NULL) {
-		d_printf(LOG_INFO, FNAME, "relay forward from %s "
+		dprintf(LOG_INFO, FNAME, "relay forward from %s "
 		    "without a relay message", addr2str(from));
 		return (-1);
 	}
@@ -1077,13 +1077,13 @@ process_relayforw(dh6p, optendp, relayinfohead, from)
 	/* relay message must contain a DHCPv6 message. */
 	len = optinfo.relaymsg_len;
 	if (len < sizeof (struct dhcp6)) {
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "short packet (%d bytes) in relay message", len);
 		return (-1);
 	}
 
 	if ((relayinfo = malloc(sizeof (*relayinfo))) == NULL) {
-		d_printf(LOG_ERR, FNAME, "failed to allocate relay info");
+		dprintf(LOG_ERR, FNAME, "failed to allocate relay info");
 		return (-1);
 	}
 	memset(relayinfo, 0, sizeof (*relayinfo));
@@ -1134,71 +1134,71 @@ set_statelessinfo(type, optinfo)
 {
 	/* SIP domain name */
 	if (dhcp6_copy_list(&optinfo->sipname_list, &sipnamelist)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to copy SIP domain list");
 		return (-1);
 	}
 
 	/* SIP server */
 	if (dhcp6_copy_list(&optinfo->sip_list, &siplist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy SIP servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy SIP servers");
 		return (-1);
 	}
 
 	/* DNS server */
 	if (dhcp6_copy_list(&optinfo->dns_list, &dnslist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy DNS servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy DNS servers");
 		return (-1);
 	}
 
 	/* DNS search list */
 	if (dhcp6_copy_list(&optinfo->dnsname_list, &dnsnamelist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy DNS search list");
+		dprintf(LOG_ERR, FNAME, "failed to copy DNS search list");
 		return (-1);
 	}
 
 	/* NTP server */
 	if (dhcp6_copy_list(&optinfo->ntp_list, &ntplist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy NTP servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy NTP servers");
 		return (-1);
 	}
 
 	/* NIS domain name */
 	if (dhcp6_copy_list(&optinfo->nisname_list, &nisnamelist)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to copy NIS domain list");
 		return (-1);
 	}
 
 	/* NIS server */
 	if (dhcp6_copy_list(&optinfo->nis_list, &nislist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy NIS servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy NIS servers");
 		return (-1);
 	}
 
 	/* NIS+ domain name */
 	if (dhcp6_copy_list(&optinfo->nispname_list, &nispnamelist)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to copy NIS+ domain list");
 		return (-1);
 	}
 
 	/* NIS+ server */
 	if (dhcp6_copy_list(&optinfo->nisp_list, &nisplist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy NIS+ servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy NIS+ servers");
 		return (-1);
 	}
 
 	/* BCMCS domain name */
 	if (dhcp6_copy_list(&optinfo->bcmcsname_list, &bcmcsnamelist)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to copy BCMCS domain list");
 		return (-1);
 	}
 
 	/* BCMCS server */
 	if (dhcp6_copy_list(&optinfo->bcmcs_list, &bcmcslist)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy BCMCS servers");
+		dprintf(LOG_ERR, FNAME, "failed to copy BCMCS servers");
 		return (-1);
 	}
 
@@ -1234,10 +1234,10 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 	 * [RFC3315 Section 15.2]
 	 */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	} else {
-		d_printf(LOG_DEBUG, FNAME, "client ID %s",
+		dprintf(LOG_DEBUG, FNAME, "client ID %s",
 		    duidstr(&optinfo->clientID));
 	}
 
@@ -1247,13 +1247,13 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 	 * [RFC3315 Section 15.2]
 	 */
 	if (optinfo->serverID.duid_len) {
-		d_printf(LOG_INFO, FNAME, "server ID option found");
+		dprintf(LOG_INFO, FNAME, "server ID option found");
 		return (-1);
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME, "found a host configuration for %s",
+		dprintf(LOG_DEBUG, FNAME, "found a host configuration for %s",
 		    client_conf->name);
 	}
 
@@ -1264,7 +1264,7 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -1272,13 +1272,13 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
@@ -1288,7 +1288,7 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 
 	/* add other configuration information */
 	if (set_statelessinfo(DH6_SOLICIT, &roptinfo)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to set other stateless information");
 		goto fail;
 	}
@@ -1315,7 +1315,7 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 		/* make a local copy of the configured prefixes */
 		if (client_conf &&
 		    dhcp6_copy_list(&conflist, &client_conf->prefix_list)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make local data");
 			goto fail;
 		}
@@ -1361,7 +1361,7 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 		if (client_conf == NULL && ifp->pool.name) {
 			if ((client_conf = create_dynamic_hostconf(&optinfo->clientID,
 				&ifp->pool)) == NULL)
-				d_printf(LOG_NOTICE, FNAME,
+				dprintf(LOG_NOTICE, FNAME,
 			    	"failed to make host configuration");
 		}
 		TAILQ_INIT(&conflist);
@@ -1369,7 +1369,7 @@ react_solicit(ifp, dh6, len, optinfo, from, fromlen, r
 		/* make a local copy of the configured addresses */
 		if (client_conf &&
 		    dhcp6_copy_list(&conflist, &client_conf->addr_list)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make local data");
 			goto fail;
 		}
@@ -1439,17 +1439,17 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* the message must include a Server Identifier option */
 	if (optinfo->serverID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no server ID option");
+		dprintf(LOG_INFO, FNAME, "no server ID option");
 		return (-1);
 	}
 	/* the contents of the Server Identifier option must match ours */
 	if (duidcmp(&optinfo->serverID, &server_duid)) {
-		d_printf(LOG_INFO, FNAME, "server ID mismatch");
+		dprintf(LOG_INFO, FNAME, "server ID mismatch");
 		return (-1);
 	}
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
@@ -1460,24 +1460,24 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -1499,11 +1499,11 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 	    TAILQ_EMPTY(relayinfohead)) {
 		u_int16_t stcode = DH6OPT_STCODE_USEMULTICAST;
 
-		d_printf(LOG_INFO, FNAME, "unexpected unicast message from %s",
+		dprintf(LOG_INFO, FNAME, "unexpected unicast message from %s",
 		    addr2str(from));
 		if (dhcp6_add_listval(&roptinfo.stcode_list,
 		    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to add a status code");
+			dprintf(LOG_ERR, FNAME, "failed to add a status code");
 			goto fail;
 		}
 		server6_send(DH6_REPLY, ifp, dh6, optinfo, from,
@@ -1533,7 +1533,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 		/* make a local copy of the configured prefixes */
 		if (client_conf &&
 		    dhcp6_copy_list(&conflist, &client_conf->prefix_list)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make local data");
 			goto fail;
 		}
@@ -1558,7 +1558,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 				    iapd->val_ia.iaid,
 				    DH6OPT_STCODE_NOPREFIXAVAIL,
 				    &roptinfo.iapd_list)) {
-					d_printf(LOG_NOTICE, FNAME,
+					dprintf(LOG_NOTICE, FNAME,
 					    "failed to make an option list");
 					dhcp6_clear_list(&conflist);
 					goto fail;
@@ -1576,7 +1576,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 		if (client_conf == NULL && ifp->pool.name) {
 			if ((client_conf = create_dynamic_hostconf(&optinfo->clientID,
 				&ifp->pool)) == NULL)
-				d_printf(LOG_NOTICE, FNAME,
+				dprintf(LOG_NOTICE, FNAME,
 			    	"failed to make host configuration");
 		}
 		TAILQ_INIT(&conflist);
@@ -1584,7 +1584,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 		/* make a local copy of the configured prefixes */
 		if (client_conf &&
 		    dhcp6_copy_list(&conflist, &client_conf->addr_list)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make local data");
 			goto fail;
 		}
@@ -1602,7 +1602,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 				    iana->val_ia.iaid,
 				    DH6OPT_STCODE_NOADDRSAVAIL,
 				    &roptinfo.iana_list)) {
-					d_printf(LOG_NOTICE, FNAME,
+					dprintf(LOG_NOTICE, FNAME,
 					    "failed to make an option list");
 					dhcp6_clear_list(&conflist);
 					goto fail;
@@ -1635,7 +1635,7 @@ react_request(ifp, pi, dh6, len, optinfo, from, fromle
 	 * information to be assigned to the client.
 	 */
 	if (set_statelessinfo(DH6_REQUEST, &roptinfo)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to set other stateless information");
 		goto fail;
 	}
@@ -1672,17 +1672,17 @@ react_renew(ifp, pi, dh6, len, optinfo, from, fromlen,
 
 	/* the message must include a Server Identifier option */
 	if (optinfo->serverID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no server ID option");
+		dprintf(LOG_INFO, FNAME, "no server ID option");
 		return (-1);
 	}
 	/* the contents of the Server Identifier option must match ours */
 	if (duidcmp(&optinfo->serverID, &server_duid)) {
-		d_printf(LOG_INFO, FNAME, "server ID mismatch");
+		dprintf(LOG_INFO, FNAME, "server ID mismatch");
 		return (-1);
 	}
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
@@ -1693,24 +1693,24 @@ react_renew(ifp, pi, dh6, len, optinfo, from, fromlen,
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -1730,11 +1730,11 @@ react_renew(ifp, pi, dh6, len, optinfo, from, fromlen,
 	    TAILQ_EMPTY(relayinfohead)) {
 		u_int16_t stcode = DH6OPT_STCODE_USEMULTICAST;
 
-		d_printf(LOG_INFO, FNAME, "unexpected unicast message from %s",
+		dprintf(LOG_INFO, FNAME, "unexpected unicast message from %s",
 		    addr2str(from));
 		if (dhcp6_add_listval(&roptinfo.stcode_list,
 		    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to add a status code");
+			dprintf(LOG_ERR, FNAME, "failed to add a status code");
 			goto fail;
 		}
 		server6_send(DH6_REPLY, ifp, dh6, optinfo, from,
@@ -1759,7 +1759,7 @@ react_renew(ifp, pi, dh6, len, optinfo, from, fromlen,
 
 	/* add other configuration information */
 	if (set_statelessinfo(DH6_RENEW, &roptinfo)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to set other stateless information");
 		goto fail;
 	}
@@ -1794,13 +1794,13 @@ react_rebind(ifp, dh6, len, optinfo, from, fromlen, re
 
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
 	/* the message must not include a server Identifier option */
 	if (optinfo->serverID.duid_len) {
-		d_printf(LOG_INFO, FNAME, "server ID option is included in "
+		dprintf(LOG_INFO, FNAME, "server ID option is included in "
 		    "a rebind message");
 		return (-1);
 	}
@@ -1812,24 +1812,24 @@ react_rebind(ifp, dh6, len, optinfo, from, fromlen, re
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -1861,13 +1861,13 @@ react_rebind(ifp, dh6, len, optinfo, from, fromlen, re
 	 */
 	if (TAILQ_EMPTY(&roptinfo.iapd_list) &&
 	    TAILQ_EMPTY(&roptinfo.iana_list)) {
-		d_printf(LOG_INFO, FNAME, "no useful information for a rebind");
+		dprintf(LOG_INFO, FNAME, "no useful information for a rebind");
 		goto fail;	/* discard the rebind */
 	}
 
 	/* add other configuration information */
 	if (set_statelessinfo(DH6_REBIND, &roptinfo)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to set other stateless information");
 		goto fail;
 	}
@@ -1903,17 +1903,17 @@ react_release(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* the message must include a Server Identifier option */
 	if (optinfo->serverID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no server ID option");
+		dprintf(LOG_INFO, FNAME, "no server ID option");
 		return (-1);
 	}
 	/* the contents of the Server Identifier option must match ours */
 	if (duidcmp(&optinfo->serverID, &server_duid)) {
-		d_printf(LOG_INFO, FNAME, "server ID mismatch");
+		dprintf(LOG_INFO, FNAME, "server ID mismatch");
 		return (-1);
 	}
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
@@ -1924,24 +1924,24 @@ react_release(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -1961,11 +1961,11 @@ react_release(ifp, pi, dh6, len, optinfo, from, fromle
 	    TAILQ_EMPTY(relayinfohead)) {
 		u_int16_t stcode = DH6OPT_STCODE_USEMULTICAST;
 
-		d_printf(LOG_INFO, FNAME, "unexpected unicast message from %s",
+		dprintf(LOG_INFO, FNAME, "unexpected unicast message from %s",
 		    addr2str(from));
 		if (dhcp6_add_listval(&roptinfo.stcode_list,
 		    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to add a status code");
+			dprintf(LOG_ERR, FNAME, "failed to add a status code");
 			goto fail;
 		}
 		server6_send(DH6_REPLY, ifp, dh6, optinfo, from,
@@ -1996,7 +1996,7 @@ react_release(ifp, pi, dh6, len, optinfo, from, fromle
 	stcode = DH6OPT_STCODE_SUCCESS;
 	if (dhcp6_add_listval(&roptinfo.stcode_list,
 	    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to add a status code");
+		dprintf(LOG_NOTICE, FNAME, "failed to add a status code");
 		goto fail;
 	}
 
@@ -2032,17 +2032,17 @@ react_decline(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* the message must include a Server Identifier option */
 	if (optinfo->serverID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no server ID option");
+		dprintf(LOG_INFO, FNAME, "no server ID option");
 		return (-1);
 	}
 	/* the contents of the Server Identifier option must match ours */
 	if (duidcmp(&optinfo->serverID, &server_duid)) {
-		d_printf(LOG_INFO, FNAME, "server ID mismatch");
+		dprintf(LOG_INFO, FNAME, "server ID mismatch");
 		return (-1);
 	}
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
@@ -2053,24 +2053,24 @@ react_decline(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -2090,11 +2090,11 @@ react_decline(ifp, pi, dh6, len, optinfo, from, fromle
 	    TAILQ_EMPTY(relayinfohead)) {
 		stcode = DH6OPT_STCODE_USEMULTICAST;
 
-		d_printf(LOG_INFO, FNAME, "unexpected unicast message from %s",
+		dprintf(LOG_INFO, FNAME, "unexpected unicast message from %s",
 		    addr2str(from));
 		if (dhcp6_add_listval(&roptinfo.stcode_list,
 		    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to add a status code");
+			dprintf(LOG_ERR, FNAME, "failed to add a status code");
 			goto fail;
 		}
 		server6_send(DH6_REPLY, ifp, dh6, optinfo, from,
@@ -2121,7 +2121,7 @@ react_decline(ifp, pi, dh6, len, optinfo, from, fromle
 	stcode = DH6OPT_STCODE_SUCCESS;
 	if (dhcp6_add_listval(&roptinfo.stcode_list,
 	    DHCP6_LISTVAL_STCODE, &stcode, NULL) == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to add a status code");
+		dprintf(LOG_NOTICE, FNAME, "failed to add a status code");
 		goto fail;
 	}
 
@@ -2159,12 +2159,12 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* the message may not include a Server Identifier option */
 	if (optinfo->serverID.duid_len) {
-		d_printf(LOG_INFO, FNAME, "server ID option found");
+		dprintf(LOG_INFO, FNAME, "server ID option found");
 		return (-1);
 	}
 	/* the message must include a Client Identifier option */
 	if (optinfo->clientID.duid_len == 0) {
-		d_printf(LOG_INFO, FNAME, "no client ID option");
+		dprintf(LOG_INFO, FNAME, "no client ID option");
 		return (-1);
 	}
 
@@ -2172,24 +2172,24 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 	/* copy client information back */
 	if (duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
 	/* process authentication */
 	if (process_auth(dh6, len, client_conf, optinfo, &roptinfo)) {
-		d_printf(LOG_INFO, FNAME, "failed to process authentication "
+		dprintf(LOG_INFO, FNAME, "failed to process authentication "
 		    "information for %s",
 		    clientstr(client_conf, &optinfo->clientID));
 		goto fail;
@@ -2198,7 +2198,7 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 	if (client_conf == NULL && ifp->pool.name) {
 		if ((client_conf = create_dynamic_hostconf(&optinfo->clientID,
 			&ifp->pool)) == NULL) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 		    	"failed to make host configuration");
 			goto fail;
 		}
@@ -2206,7 +2206,7 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 	TAILQ_INIT(&conflist);
 	/* make a local copy of the configured addresses */
 	if (dhcp6_copy_list(&conflist, &client_conf->addr_list)) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to make local data");
 		goto fail;
 	}
@@ -2216,13 +2216,13 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 	 * [RFC3315 18.2]. (IA-PD is just ignored [RFC3633 12.1])
 	 */
 	if (TAILQ_EMPTY(&optinfo->iana_list)) {
-		d_printf(LOG_INFO, FNAME, "no IA-NA option found");
+		dprintf(LOG_INFO, FNAME, "no IA-NA option found");
 		goto fail;
 	}
 	for (iana = TAILQ_FIRST(&optinfo->iana_list); iana;
 	    iana = TAILQ_NEXT(iana, link)) {
 		if (TAILQ_EMPTY(&iana->sublist)) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "no IA-ADDR option found in IA-NA %d",
 			    iana->val_ia.iaid);
 			goto fail;
@@ -2245,7 +2245,7 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 				struct relayinfo *relayinfo;
 
 				if (relayinfohead == NULL) {
-					d_printf(LOG_INFO, FNAME,
+					dprintf(LOG_INFO, FNAME,
 					    "no link-addr found");
 					goto fail;
 				}
@@ -2259,7 +2259,7 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 			}
 
 			if (memcmp(linkaddr, confaddr, 8) != 0) {
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "%s does not seem to belong to %s's link",
 				    in6addr2str(confaddr, 0),
 				    in6addr2str(linkaddr, 0));
@@ -2278,7 +2278,7 @@ react_confirm(ifp, pi, dh6, len, optinfo, from, fromle
 	    iana = TAILQ_NEXT(iana, link)) {
 		if (make_ia(iana, &conflist, &roptinfo.iana_list,
 		    client_conf, 1) == 0) {
-			d_printf(LOG_DEBUG, FNAME,
+			dprintf(LOG_DEBUG, FNAME,
 			    "IA-NA configuration not found");
 			goto fail;
 		}
@@ -2321,12 +2321,12 @@ react_informreq(ifp, dh6, len, optinfo, from, fromlen,
 	 * [RFC3315 Section 15]
 	 */
 	if (!TAILQ_EMPTY(&optinfo->iapd_list)) {
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "information request contains an IA_PD option");
 		return (-1);
 	}
 	if (!TAILQ_EMPTY(&optinfo->iana_list)) {
-		d_printf(LOG_INFO, FNAME,
+		dprintf(LOG_INFO, FNAME,
 		    "information request contains an IA_NA option");
 		return (-1);
 	}
@@ -2334,7 +2334,7 @@ react_informreq(ifp, dh6, len, optinfo, from, fromlen,
 	/* if a server identifier is included, it must match ours. */
 	if (optinfo->serverID.duid_len &&
 	    duidcmp(&optinfo->serverID, &server_duid)) {
-		d_printf(LOG_INFO, FNAME, "server DUID mismatch");
+		dprintf(LOG_INFO, FNAME, "server DUID mismatch");
 		return (-1);
 	}
 
@@ -2345,20 +2345,20 @@ react_informreq(ifp, dh6, len, optinfo, from, fromlen,
 
 	/* server identifier option */
 	if (duidcpy(&roptinfo.serverID, &server_duid)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy server ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy server ID");
 		goto fail;
 	}
 
 	/* copy client information back (if provided) */
 	if (optinfo->clientID.duid_id &&
 	    duidcpy(&roptinfo.clientID, &optinfo->clientID)) {
-		d_printf(LOG_ERR, FNAME, "failed to copy client ID");
+		dprintf(LOG_ERR, FNAME, "failed to copy client ID");
 		goto fail;
 	}
 
 	/* set stateless information */
 	if (set_statelessinfo(DH6_INFORM_REQ, &roptinfo)) {
-		d_printf(LOG_ERR, FNAME,
+		dprintf(LOG_ERR, FNAME,
 		    "failed to set other stateless information");
 		goto fail;
 	}
@@ -2386,7 +2386,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 
 	/* get per-host configuration for the client, if any. */
 	if ((client_conf = find_hostconf(&optinfo->clientID))) {
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "found a host configuration named %s", client_conf->name);
 	}
 
@@ -2399,7 +2399,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 		 * Sections 18.2.3 and 18.2.4 of RFC3315, and the two sets
 		 * of behavior are identical.
 		 */
-		d_printf(LOG_INFO, FNAME, "no binding found for %s",
+		dprintf(LOG_INFO, FNAME, "no binding found for %s",
 		    duidstr(&optinfo->clientID));
 
 		switch (msgtype) {
@@ -2413,7 +2413,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 			 */
 			if (make_ia_stcode(iap->type, iap->val_ia.iaid,
 			    DH6OPT_STCODE_NOBINDING, retlist)) {
-				d_printf(LOG_NOTICE, FNAME,
+				dprintf(LOG_NOTICE, FNAME,
 				    "failed to make an option list");
 				return (-1);
 			}
@@ -2435,7 +2435,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 			 */
 			return (-1);
 		default:	/* XXX: should be a bug */
-			d_printf(LOG_ERR, FNAME, "impossible message type %s",
+			dprintf(LOG_ERR, FNAME, "impossible message type %s",
 			    dhcp6msgstr(msgtype));
 			return (-1);
 		}
@@ -2463,7 +2463,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 				blv = dhcp6_find_listval(&binding->val_list,
 				    DHCP6_LISTVAL_PREFIX6, &prefix, 0);
 				if (blv == NULL) {
-					d_printf(LOG_DEBUG, FNAME,
+					dprintf(LOG_DEBUG, FNAME,
 					    "%s/%d is not found in %s",
 					    in6addr2str(&prefix.addr, 0),
 					    prefix.plen, bindingstr(binding));
@@ -2479,7 +2479,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 				if (dhcp6_add_listval(&ialist,
 				    DHCP6_LISTVAL_PREFIX6, &prefix, NULL)
 				    == NULL) {
-					d_printf(LOG_NOTICE, FNAME,
+					dprintf(LOG_NOTICE, FNAME,
 					    "failed  to copy binding info");
 					dhcp6_clear_list(&ialist);
 					return (-1);
@@ -2493,7 +2493,7 @@ update_ia(msgtype, iap, retlist, optinfo)
 				blv = dhcp6_find_listval(&binding->val_list,
 				    DHCP6_LISTVAL_STATEFULADDR6, &saddr, 0);
 				if (blv == NULL) {
-					d_printf(LOG_DEBUG, FNAME,
+					dprintf(LOG_DEBUG, FNAME,
 					    "%s is not found in %s",
 					    in6addr2str(&saddr.addr, 0),
 					    bindingstr(binding));
@@ -2509,14 +2509,14 @@ update_ia(msgtype, iap, retlist, optinfo)
 				if (dhcp6_add_listval(&ialist,
 				    DHCP6_LISTVAL_STATEFULADDR6, &saddr, NULL)
 				    == NULL) {
-					d_printf(LOG_NOTICE, FNAME,
+					dprintf(LOG_NOTICE, FNAME,
 					    "failed  to copy binding info");
 					dhcp6_clear_list(&ialist);
 					return (-1);
 				}
 				break;
 			default:
-				d_printf(LOG_ERR, FNAME, "unsupported IA type");
+				dprintf(LOG_ERR, FNAME, "unsupported IA type");
 				return (-1); /* XXX */
 			}
 		}
@@ -2555,7 +2555,7 @@ release_binding_ia(iap, retlist, optinfo)
 		 */
 		if (make_ia_stcode(iap->type, iap->val_ia.iaid,
 		    DH6OPT_STCODE_NOBINDING, retlist)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make an option list");
 			return (-1);
 		}
@@ -2577,7 +2577,7 @@ release_binding_ia(iap, retlist, optinfo)
 			if ((lvia = find_binding_ia(lv, binding)) != NULL) {
 				switch (binding->iatype) {
 					case DHCP6_LISTVAL_IAPD:
-						d_printf(LOG_DEBUG, FNAME,
+						dprintf(LOG_DEBUG, FNAME,
 						    "bound prefix %s/%d "
 						    "has been released",
 						    in6addr2str(&lvia->val_prefix6.addr,
@@ -2586,7 +2586,7 @@ release_binding_ia(iap, retlist, optinfo)
 						break;
 					case DHCP6_LISTVAL_IANA:
 						release_address(&lvia->val_prefix6.addr);
-						d_printf(LOG_DEBUG, FNAME,
+						dprintf(LOG_DEBUG, FNAME,
 						    "bound address %s "
 						    "has been released",
 						    in6addr2str(&lvia->val_prefix6.addr,
@@ -2630,7 +2630,7 @@ decline_binding_ia(iap, retlist, optinfo)
 		 */
 		if (make_ia_stcode(iap->type, iap->val_ia.iaid,
 		    DH6OPT_STCODE_NOBINDING, retlist)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to make an option list");
 			return (-1);
 		}
@@ -2652,13 +2652,13 @@ decline_binding_ia(iap, retlist, optinfo)
 		}
 
 		if ((lvia = find_binding_ia(lv, binding)) == NULL) {
-			d_printf(LOG_DEBUG, FNAME, "no binding found "
+			dprintf(LOG_DEBUG, FNAME, "no binding found "
 			    "for address %s",
 			    in6addr2str(&lv->val_statefuladdr6.addr, 0));
 			continue;
 		}
 
-		d_printf(LOG_DEBUG, FNAME,
+		dprintf(LOG_DEBUG, FNAME,
 		    "bound address %s has been marked as declined",
 		    in6addr2str(&lvia->val_statefuladdr6.addr, 0));
 		decline_address(&lvia->val_statefuladdr6.addr);
@@ -2683,7 +2683,7 @@ server6_signal(sig)
 	int sig;
 {
 
-	d_printf(LOG_INFO, FNAME, "received a signal (%d)", sig);
+	dprintf(LOG_INFO, FNAME, "received a signal (%d)", sig);
 
 	switch (sig) {
 	case SIGTERM:
@@ -2712,7 +2712,7 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 	struct relayinfo *relayinfo;
 
 	if (sizeof(struct dhcp6) > sizeof(replybuf)) {
-		d_printf(LOG_ERR, FNAME, "buffer size assumption failed");
+		dprintf(LOG_ERR, FNAME, "buffer size assumption failed");
 		return (-1);
 	}
 
@@ -2725,7 +2725,7 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 	/* set options in the reply message */
 	if ((optlen = dhcp6_set_options(type, (struct dhcp6opt *)(dh6 + 1),
 	    (struct dhcp6opt *)(replybuf + sizeof(replybuf)), roptinfo)) < 0) {
-		d_printf(LOG_INFO, FNAME, "failed to construct reply options");
+		dprintf(LOG_INFO, FNAME, "failed to construct reply options");
 		return (-1);
 	}
 	len += optlen;
@@ -2735,7 +2735,7 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 	case DHCP6_AUTHPROTO_DELAYED:
 		if (client_conf == NULL || client_conf->delayedkey == NULL) {
 			/* This case should have been caught earlier */
-			d_printf(LOG_ERR, FNAME, "authentication required "
+			dprintf(LOG_ERR, FNAME, "authentication required "
 			    "but not key provided");
 			break;
 		}
@@ -2743,7 +2743,7 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 		    roptinfo->authalgorithm,
 		    roptinfo->delayedauth_offset + sizeof(*dh6),
 		    client_conf->delayedkey)) {
-			d_printf(LOG_WARNING, FNAME, "failed to calculate MAC");
+			dprintf(LOG_WARNING, FNAME, "failed to calculate MAC");
 			return (-1);
 		}
 		break;
@@ -2787,7 +2787,7 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 		    (struct dhcp6opt *)(dh6relay + 1),
 		    (struct dhcp6opt *)(replybuf + sizeof(replybuf)),
 		    &relayopt)) < 0) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "failed to construct relay message");
 			dhcp6_clear_options(&relayopt);
 			return (-1);
@@ -2803,12 +2803,12 @@ server6_send(type, ifp, origmsg, optinfo, from, fromle
 	dst.sin6_scope_id = ((struct sockaddr_in6 *)from)->sin6_scope_id;
 	if (transmit_sa(outsock, (struct sockaddr *)&dst,
 	    replybuf, len) != 0) {
-		d_printf(LOG_ERR, FNAME, "transmit %s to %s failed",
+		dprintf(LOG_ERR, FNAME, "transmit %s to %s failed",
 		    dhcp6msgstr(type), addr2str((struct sockaddr *)&dst));
 		return (-1);
 	}
 
-	d_printf(LOG_DEBUG, FNAME, "transmit %s to %s",
+	dprintf(LOG_DEBUG, FNAME, "transmit %s to %s",
 	    dhcp6msgstr(type), addr2str((struct sockaddr *)&dst));
 
 	return (0);
@@ -2830,13 +2830,13 @@ make_ia_stcode(iatype, iaid, stcode, retlist)
 	TAILQ_INIT(&stcode_list);
 	if (dhcp6_add_listval(&stcode_list, DHCP6_LISTVAL_STCODE,
 	    &stcode, NULL) == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to make an option list");
+		dprintf(LOG_NOTICE, FNAME, "failed to make an option list");
 		return (-1);
 	}
 
 	if (dhcp6_add_listval(retlist, iatype,
 	    &ia_empty, &stcode_list) == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to make an option list");
+		dprintf(LOG_NOTICE, FNAME, "failed to make an option list");
 		dhcp6_clear_list(&stcode_list);
 		return (-1);
 	}
@@ -2867,7 +2867,7 @@ make_ia(spec, conflist, retlist, client_conf, do_bindi
 		struct dhcp6_list *blist = &binding->val_list;
 		struct dhcp6_listval *bia, *v;
 
-		d_printf(LOG_DEBUG, FNAME, "we have a binding already: %s",
+		dprintf(LOG_DEBUG, FNAME, "we have a binding already: %s",
 		    bindingstr(binding));
 
 		update_binding(binding);
@@ -2878,7 +2878,7 @@ make_ia(spec, conflist, retlist, client_conf, do_bindi
 		calc_ia_timo(&ia, blist, client_conf);
 		if (dhcp6_add_listval(retlist, spec->type, &ia, blist)
 		    == NULL) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to copy binding info");
 			return (0);
 		}
@@ -2954,7 +2954,7 @@ make_ia(spec, conflist, retlist, client_conf, do_bindi
 		if (do_binding) {
 			if (add_binding(&client_conf->duid, DHCP6_BINDING_IA,
 			    spec->type, spec->val_ia.iaid, &ialist) == NULL) {
-				d_printf(LOG_NOTICE, FNAME,
+				dprintf(LOG_NOTICE, FNAME,
 				    "failed to make a binding");
 				found = 0;
 			}
@@ -2995,7 +2995,7 @@ make_match_ia(spec, conflist, retlist)
 				match = 0;
 			break;
 		default:
-			d_printf(LOG_ERR, FNAME, "unsupported IA type");
+			dprintf(LOG_ERR, FNAME, "unsupported IA type");
 			return (0); /* XXX */
 		}
 	}
@@ -3027,10 +3027,10 @@ make_iana_from_pool(poolspec, spec, retlist)
 	struct pool_conf *pool;
 	int found = 0;
 
-	d_printf(LOG_DEBUG, FNAME, "called");
+	dprintf(LOG_DEBUG, FNAME, "called");
 
 	if ((pool = find_pool(poolspec->name)) == NULL) {
-		d_printf(LOG_ERR, FNAME, "pool '%s' not found", poolspec->name);
+		dprintf(LOG_ERR, FNAME, "pool '%s' not found", poolspec->name);
 		return (0);
 	}
 
@@ -3055,7 +3055,7 @@ make_iana_from_pool(poolspec, spec, retlist)
 		}
 	}
 
-	d_printf(LOG_DEBUG, FNAME, "returns (found=%d)", found);
+	dprintf(LOG_DEBUG, FNAME, "returns (found=%d)", found);
 
 	return (found);
 }
@@ -3073,7 +3073,7 @@ calc_ia_timo(ia, ialist, client_conf)
 	iatype = TAILQ_FIRST(ialist)->type;
 	for (iav = TAILQ_FIRST(ialist); iav; iav = TAILQ_NEXT(iav, link)) {
 		if (iav->type != iatype) {
-			d_printf(LOG_ERR, FNAME,
+			dprintf(LOG_ERR, FNAME,
 			    "assumption failure: IA list is not consistent");
 			exit (1); /* XXX */
 		}
@@ -3139,7 +3139,7 @@ update_binding_duration(binding)
 				lifetime = iav->val_statefuladdr6.vltime;
 				break;
 			default:
-				d_printf(LOG_ERR, FNAME, "unsupported IA type");
+				dprintf(LOG_ERR, FNAME, "unsupported IA type");
 				return;	/* XXX */
 			}
 
@@ -3157,7 +3157,7 @@ update_binding_duration(binding)
 		break;
 	default:
 		/* should be internal error. */
-		d_printf(LOG_ERR, FNAME, "unknown binding type (%d)",
+		dprintf(LOG_ERR, FNAME, "unknown binding type (%d)",
 		    binding->type);
 		return;
 	}
@@ -3177,13 +3177,13 @@ add_binding(clientid, btype, iatype, iaid, val0)
 	u_int32_t duration = DHCP6_DURATION_INFINITE;
 
 	if ((binding = malloc(sizeof(*binding))) == NULL) {
-		d_printf(LOG_NOTICE, FNAME, "failed to allocate memory");
+		dprintf(LOG_NOTICE, FNAME, "failed to allocate memory");
 		return (NULL);
 	}
 	memset(binding, 0, sizeof(*binding));
 	binding->type = btype;
 	if (duidcpy(&binding->clientid, clientid)) {
-		d_printf(LOG_NOTICE, FNAME, "failed to copy DUID");
+		dprintf(LOG_NOTICE, FNAME, "failed to copy DUID");
 		goto fail;
 	}
 	binding->iatype = iatype;
@@ -3195,7 +3195,7 @@ add_binding(clientid, btype, iatype, iaid, val0)
 		TAILQ_INIT(&binding->val_list);
 		if (dhcp6_copy_list(&binding->val_list,
 		    (struct dhcp6_list *)val0)) {
-			d_printf(LOG_NOTICE, FNAME,
+			dprintf(LOG_NOTICE, FNAME,
 			    "failed to copy binding data");
 			goto fail;
 		}
@@ -3208,13 +3208,13 @@ add_binding(clientid, btype, iatype, iaid, val0)
 				lv_next = TAILQ_NEXT(lv, link);
 
 				if (lv->type != DHCP6_LISTVAL_STATEFULADDR6) {
-					d_printf(LOG_ERR, FNAME,
+					dprintf(LOG_ERR, FNAME,
 						"unexpected binding value type(%d)", lv->type);
 					continue;
 				}
 
 				if (!lease_address(&lv->val_statefuladdr6.addr)) {
-					d_printf(LOG_NOTICE, FNAME,
+					dprintf(LOG_NOTICE, FNAME,
 						"cannot lease address %s",
 						in6addr2str(&lv->val_statefuladdr6.addr, 0));
 					TAILQ_REMOVE(ia_list, lv, link);
@@ -3222,13 +3222,13 @@ add_binding(clientid, btype, iatype, iaid, val0)
 				}
 			}
 			if (TAILQ_EMPTY(ia_list)) {
-				d_printf(LOG_NOTICE, FNAME, "cannot lease any address");
+				dprintf(LOG_NOTICE, FNAME, "cannot lease any address");
 				goto fail;
 			}
 		}
 		break;
 	default:
-		d_printf(LOG_ERR, FNAME, "unexpected binding type(%d)", btype);
+		dprintf(LOG_ERR, FNAME, "unexpected binding type(%d)", btype);
 		goto fail;
 	}
 
@@ -3240,7 +3240,7 @@ add_binding(clientid, btype, iatype, iaid, val0)
 
 		binding->timer = dhcp6_add_timer(binding_timo, binding);
 		if (binding->timer == NULL) {
-			d_printf(LOG_NOTICE, FNAME, "failed to add timer");
+			dprintf(LOG_NOTICE, FNAME, "failed to add timer");
 			goto fail;
 		}
 		timo.tv_sec = (long)duration;
@@ -3250,7 +3250,7 @@ add_binding(clientid, btype, iatype, iaid, val0)
 
 	TAILQ_INSERT_TAIL(&dhcp6_binding_head, binding, link);
 
-	d_printf(LOG_DEBUG, FNAME, "add a new binding %s", bindingstr(binding));
+	dprintf(LOG_DEBUG, FNAME, "add a new binding %s", bindingstr(binding));
 
 	return (binding);
 
@@ -3290,7 +3290,7 @@ update_binding(binding)
 {
 	struct timeval timo;
 
-	d_printf(LOG_DEBUG, FNAME, "update binding %s for %s",
+	dprintf(LOG_DEBUG, FNAME, "update binding %s for %s",
 	    bindingstr(binding), duidstr(&binding->clientid));
 
 	/* update timestamp and calculate new duration */
@@ -3311,7 +3311,7 @@ static void
 remove_binding(binding)
 	struct dhcp6_binding *binding;
 {
-	d_printf(LOG_DEBUG, FNAME, "remove a binding %s",
+	dprintf(LOG_DEBUG, FNAME, "remove a binding %s",
 	    bindingstr(binding));
 
 	if (binding->timer)
@@ -3338,7 +3338,7 @@ free_binding(binding)
 
 			for (lv = TAILQ_FIRST(ia_list); lv; lv = TAILQ_NEXT(lv, link)) {
 				if (lv->type != DHCP6_LISTVAL_STATEFULADDR6) {
-					d_printf(LOG_ERR, FNAME,
+					dprintf(LOG_ERR, FNAME,
 						"unexpected binding value type(%d)", lv->type);
 					continue;
 				}
@@ -3348,7 +3348,7 @@ free_binding(binding)
 		dhcp6_clear_list(&binding->val_list);
 		break;
 	default:
-		d_printf(LOG_ERR, FNAME, "unknown binding type %d",
+		dprintf(LOG_ERR, FNAME, "unknown binding type %d",
 		    binding->type);
 		break;
 	}
@@ -3381,7 +3381,7 @@ binding_timo(arg)
 				lifetime = iav->val_prefix6.vltime;
 				break;
 			default:
-				d_printf(LOG_ERR, FNAME, "internal error: "
+				dprintf(LOG_ERR, FNAME, "internal error: "
 				    "unknown binding type (%d)",
 				    binding->iatype);
 				return (NULL); /* XXX */
@@ -3389,7 +3389,7 @@ binding_timo(arg)
 
 			if (lifetime != DHCP6_DURATION_INFINITE &&
 			    lifetime <= past) {
-				d_printf(LOG_DEBUG, FNAME, "bound prefix %s/%d"
+				dprintf(LOG_DEBUG, FNAME, "bound prefix %s/%d"
 				    " in %s has expired",
 				    in6addr2str(&iav->val_prefix6.addr, 0),
 				    iav->val_prefix6.plen,
@@ -3409,7 +3409,7 @@ binding_timo(arg)
 
 		break;
 	default:
-		d_printf(LOG_ERR, FNAME, "unknown binding type %d",
+		dprintf(LOG_ERR, FNAME, "unknown binding type %d",
 		    binding->type);
 		return (NULL);	/* XXX */
 	}
@@ -3439,7 +3439,7 @@ find_binding_ia(key, binding)
 	case DHCP6_BINDING_IA:
 		return (dhcp6_find_listval(ia_list, key->type, &key->uv, 0));
 	default:
-		d_printf(LOG_ERR, FNAME, "unknown binding type %d",
+		dprintf(LOG_ERR, FNAME, "unknown binding type %d",
 		    binding->type);
 		return (NULL);	/* XXX */
 	}
@@ -3469,7 +3469,7 @@ bindingstr(binding)
 		    (u_long)binding->duration);
 		break;
 	default:
-		d_printf(LOG_ERR, FNAME, "unexpected binding type(%d)",
+		dprintf(LOG_ERR, FNAME, "unexpected binding type(%d)",
 		    binding->type);
 		return ("???");
 	}
@@ -3503,7 +3503,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 		return (0);
 	case DHCP6_AUTHPROTO_DELAYED:
 		if (optinfo->authalgorithm != DHCP6_AUTHALG_HMACMD5) {
-			d_printf(LOG_INFO, FNAME, "unknown authentication "
+			dprintf(LOG_INFO, FNAME, "unknown authentication "
 			    "algorithm (%d) required by %s",
 			    optinfo->authalgorithm,
 			    clientstr(client_conf, &optinfo->clientID));
@@ -3511,7 +3511,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 		}
 
 		if (optinfo->authrdm != DHCP6_AUTHRDM_MONOCOUNTER) {
-			d_printf(LOG_INFO, FNAME,
+			dprintf(LOG_INFO, FNAME,
 			    "unknown RDM (%d) required by %s",
 			    optinfo->authrdm,
 			    clientstr(client_conf, &optinfo->clientID));
@@ -3520,13 +3520,13 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 
 		/* see if we have a key for the client */
 		if (client_conf == NULL || client_conf->delayedkey == NULL) {
-			d_printf(LOG_INFO, FNAME, "client %s wanted "
+			dprintf(LOG_INFO, FNAME, "client %s wanted "
 			    "authentication, but no key found",
 			    clientstr(client_conf, &optinfo->clientID));
 			break;
 		}
 		key = client_conf->delayedkey;
-		d_printf(LOG_DEBUG, FNAME, "found key %s for client %s",
+		dprintf(LOG_DEBUG, FNAME, "found key %s for client %s",
 		    key->name, clientstr(client_conf, &optinfo->clientID));
 
 		if (msgtype == DH6_SOLICIT) {
@@ -3535,7 +3535,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 				 * A solicit message should not contain
 				 * authentication information.
 				 */
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "authentication information "
 				    "provided in solicit from %s",
 				    clientstr(client_conf,
@@ -3545,7 +3545,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 		} else {
 			/* replay protection */
 			if (!client_conf->saw_previous_rd) {
-				d_printf(LOG_WARNING, FNAME,
+				dprintf(LOG_WARNING, FNAME,
 				    "previous RD value for %s is unknown "
 				    "(accept it)", clientstr(client_conf,
 				    &optinfo->clientID));
@@ -3553,7 +3553,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 				if (dhcp6_auth_replaycheck(optinfo->authrdm,
 				    client_conf->previous_rd,
 				    optinfo->authrd)) {
-					d_printf(LOG_INFO, FNAME,
+					dprintf(LOG_INFO, FNAME,
 					    "possible replay attack detected "
 					    "for client %s",
 					    clientstr(client_conf,
@@ -3563,7 +3563,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 			}
 
 			if ((optinfo->authflags & DHCP6OPT_AUTHFLAG_NOINFO)) {
-				d_printf(LOG_INFO, FNAME,
+				dprintf(LOG_INFO, FNAME,
 				    "client %s did not provide authentication "
 				    "information in %s",
 				    clientstr(client_conf, &optinfo->clientID),
@@ -3583,7 +3583,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 			    optinfo->delayedauth_realmlen != key->realmlen ||
 			    memcmp(optinfo->delayedauth_realmval, key->realm,
 			    key->realmlen) != 0) {
-				d_printf(LOG_INFO, FNAME, "authentication key "
+				dprintf(LOG_INFO, FNAME, "authentication key "
 				    "mismatch with client %s",
 				    clientstr(client_conf,
 				    &optinfo->clientID));
@@ -3592,7 +3592,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 
 			/* check for the key lifetime */
 			if (dhcp6_validate_key(key)) {
-				d_printf(LOG_INFO, FNAME, "key %s has expired",
+				dprintf(LOG_INFO, FNAME, "key %s has expired",
 				    key->name);
 				break;
 			}
@@ -3602,12 +3602,12 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 			    optinfo->authproto, optinfo->authalgorithm,
 			    optinfo->delayedauth_offset + sizeof(*dh6), key)
 			    == 0) {
-				d_printf(LOG_DEBUG, FNAME,
+				dprintf(LOG_DEBUG, FNAME,
 				    "message authentication validated for "
 				    "client %s", clientstr(client_conf,
 				    &optinfo->clientID));
 			} else {
-				d_printf(LOG_INFO, FNAME, "invalid message "
+				dprintf(LOG_INFO, FNAME, "invalid message "
 				    "authentication");
 				break;
 			}
@@ -3619,7 +3619,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 
 		if (get_rdvalue(roptinfo->authrdm, &roptinfo->authrd,
 		    sizeof(roptinfo->authrd))) {
-			d_printf(LOG_ERR, FNAME, "failed to get a replay "
+			dprintf(LOG_ERR, FNAME, "failed to get a replay "
 			    "detection value for %s",
 			    clientstr(client_conf, &optinfo->clientID));
 			break;	/* XXX: try to recover? */
@@ -3630,7 +3630,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 		roptinfo->delayedauth_realmval =
 		    malloc(roptinfo->delayedauth_realmlen);
 		if (roptinfo->delayedauth_realmval == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to allocate memory "
+			dprintf(LOG_ERR, FNAME, "failed to allocate memory "
 			    "for authentication realm for %s",
 			    clientstr(client_conf, &optinfo->clientID));
 			break;
@@ -3642,7 +3642,7 @@ process_auth(dh6, len, client_conf, optinfo, roptinfo)
 
 		break;
 	default:
-		d_printf(LOG_INFO, FNAME, "client %s wanted authentication "
+		dprintf(LOG_INFO, FNAME, "client %s wanted authentication "
 		    "with unsupported protocol (%d)",
 		    clientstr(client_conf, &optinfo->clientID),
 		    optinfo->authproto);
