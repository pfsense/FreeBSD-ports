--- dhcp6c.c.orig	2008-06-15 07:48:41 UTC
+++ dhcp6c.c
@@ -67,6 +67,7 @@
 #include <string.h>
 #include <err.h>
 #include <ifaddrs.h>
+#include <fcntl.h>
 
 #include <dhcp6.h>
 #include <config.h>
@@ -88,7 +89,6 @@ static sig_atomic_t sig_flags = 0;
 const dhcp6_mode_t dhcp6_mode = DHCP6_MODE_CLIENT;
 
 int sock;	/* inbound/outbound udp port */
-int rtsock;	/* routing socket */
 int ctlsock = -1;		/* control TCP port */
 char *ctladdr = DEFAULT_CLIENT_CONTROL_ADDR;
 char *ctlport = DEFAULT_CLIENT_CONTROL_PORT;
@@ -147,6 +147,7 @@ int client6_start __P((struct dhcp6_if *
 static void info_printf __P((const char *, ...));
 
 extern int client6_script __P((char *, int, struct dhcp6_optinfo *));
+int opt_norelease;
 
 #define MAX_ELAPSED_TIME 0xffff
 
@@ -169,7 +170,7 @@ main(argc, argv)
 	else
 		progname++;
 
-	while ((ch = getopt(argc, argv, "c:dDfik:p:")) != -1) {
+	while ((ch = getopt(argc, argv, "c:ndDfik:p:")) != -1) {
 		switch (ch) {
 		case 'c':
 			conffile = optarg;
@@ -192,6 +193,9 @@ main(argc, argv)
 		case 'p':
 			pid_file = optarg;
 			break;
+		case 'n':
+			opt_norelease = 1;
+			break;
 		default:
 			usage();
 			exit(0);
@@ -246,7 +250,7 @@ static void
 usage()
 {
 
-	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfi] "
+	fprintf(stderr, "usage: dhcp6c [-c configfile] [-ndDfi] "
 	    "[-p pid-file] interface [interfaces...]\n");
 }
 
@@ -257,7 +261,7 @@ client6_init()
 {
 	struct addrinfo hints, *res;
 	static struct sockaddr_in6 sa6_allagent_storage;
-	int error, on = 1;
+	int error, on = 0;
 
 	/* get our DUID */
 	if (get_duid(DUID_FILE, &client_duid)) {
@@ -287,6 +291,20 @@ client6_init()
 		dprintf(LOG_ERR, FNAME, "socket");
 		exit(1);
 	}
+
+	if ((on = fcntl(sock, F_GETFL, 0)) == -1) {
+		dprintf(LOG_ERR, FNAME, "fctnl getflags");
+		exit(1);
+	}
+
+	on |= FD_CLOEXEC;
+
+	if ((on = fcntl(sock, F_SETFL, on)) == -1) {
+		dprintf(LOG_ERR, FNAME, "fctnl setflags");
+		exit(1);
+	}
+
+	on = 1;
 	if (setsockopt(sock, SOL_SOCKET, SO_REUSEPORT,
 		       &on, sizeof(on)) < 0) {
 		dprintf(LOG_ERR, FNAME,
@@ -337,13 +355,6 @@ client6_init()
 	}
 	freeaddrinfo(res);
 
-	/* open a routing socket to watch the routing table */
-	if ((rtsock = socket(PF_ROUTE, SOCK_RAW, 0)) < 0) {
-		dprintf(LOG_ERR, FNAME, "open a routing socket: %s",
-		    strerror(errno));
-		exit(1);
-	}
-
 	memset(&hints, 0, sizeof(hints));
 	hints.ai_family = PF_INET6;
 	hints.ai_socktype = SOCK_DGRAM;
@@ -596,7 +607,7 @@ get_ifname(bpp, lenp, ifbuf, ifbuflen)
 	if (*lenp < ifnamelen || ifnamelen > ifbuflen)
 		return (-1);
 
-	memset(ifbuf, 0, sizeof(ifbuf));
+	memset(ifbuf, 0, ifbuflen);
 	memcpy(ifbuf, *bpp, ifnamelen);
 	if (ifbuf[ifbuflen - 1] != '\0')
 		return (-1);	/* not null terminated */
@@ -763,6 +774,15 @@ client6_ifctl(ifname, command)
 
 	switch(command) {
 	case DHCP6CTL_COMMAND_START:
+		/*
+		 * The ifid might have changed, so reset it before releasing the
+		 * lease.
+		 */
+		if (ifreset(ifp)) {
+			dprintf(LOG_NOTICE, FNAME, "failed to reset %s",
+			    ifname);
+			return (-1);
+		}
 		free_resources(ifp);
 		if (client6_start(ifp)) {
 			dprintf(LOG_NOTICE, FNAME, "failed to restart %s",
@@ -929,7 +949,7 @@ construct_confdata(ifp, ev)
 			    "failed to create a new event data");
 			goto fail;
 		}
-		memset(evd, 0, sizeof(evd));
+		memset(evd, 0, sizeof(*evd));
 
 		memset(&iaparam, 0, sizeof(iaparam));
 		iaparam.iaid = iac->iaid;
@@ -1163,27 +1183,33 @@ client6_send(ev)
 	switch(ev->state) {
 	case DHCP6S_SOLICIT:
 		dh6->dh6_msgtype = DH6_SOLICIT;
+		d_printf(LOG_INFO, FNAME, "Sending Solicit");
 		break;
 	case DHCP6S_REQUEST:
 		dh6->dh6_msgtype = DH6_REQUEST;
+		d_printf(LOG_INFO, FNAME, "Sending Request");
 		break;
 	case DHCP6S_RENEW:
 		dh6->dh6_msgtype = DH6_RENEW;
+		d_printf(LOG_INFO, FNAME, "Sending Renew");
 		break;
 	case DHCP6S_REBIND:
 		dh6->dh6_msgtype = DH6_REBIND;
+		d_printf(LOG_INFO, FNAME, "Sending Rebind");
 		break;
 	case DHCP6S_RELEASE:
 		dh6->dh6_msgtype = DH6_RELEASE;
+		d_printf(LOG_INFO, FNAME, "Sending Release");
 		break;
 	case DHCP6S_INFOREQ:
 		dh6->dh6_msgtype = DH6_INFORM_REQ;
+		d_printf(LOG_INFO, FNAME, "Sending Information Request");
 		break;
 	default:
 		dprintf(LOG_ERR, FNAME, "unexpected state");
 		exit(1);	/* XXX */
 	}
-
+	
 	if (ev->timeouts == 0) {
 		/*
 		 * A client SHOULD generate a random number that cannot easily
@@ -1721,7 +1747,29 @@ client6_recvreply(ifp, dh6, len, optinfo
 		dprintf(LOG_INFO, FNAME, "unexpected reply");
 		return (-1);
 	}
-
+	
+	switch(state)
+	{
+	  case DHCP6S_INFOREQ:
+	    d_printf(LOG_INFO, FNAME, "dhcp6c Received INFOREQ");
+	  break;  
+	  case DHCP6S_REQUEST:
+	    d_printf(LOG_INFO, FNAME, "dhcp6c Received REQUEST");
+	  break;
+	  case DHCP6S_RENEW:
+	     d_printf(LOG_INFO, FNAME, "dhcp6c Received INFO");
+	  break;
+	  case DHCP6S_REBIND:
+	     d_printf(LOG_INFO, FNAME, "dhcp6c Received REBIND");
+	  break;
+	  case DHCP6S_RELEASE:
+	     d_printf(LOG_INFO, FNAME, "dhcp6c Received RELEASE");
+	  break;
+	  case DHCP6S_SOLICIT:
+	     d_printf(LOG_INFO, FNAME, "dhcp6c Received SOLICIT");
+	  break;	  
+	}
+	
 	/* A Reply message must contain a Server ID option */
 	if (optinfo->serverID.duid_len == 0) {
 		dprintf(LOG_INFO, FNAME, "no server ID option");
@@ -1828,15 +1876,6 @@ client6_recvreply(ifp, dh6, len, optinfo
 	}
 
 	/*
-	 * Call the configuration script, if specified, to handle various
-	 * configuration parameters.
-	 */
-	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0) {
-		dprintf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
-		client6_script(ifp->scriptpath, state, optinfo);
-	}
-
-	/*
 	 * Set refresh timer for configuration information specified in
 	 * information-request.  If the timer value is specified by the server
 	 * in an information refresh time option, use it; use the protocol
@@ -1888,6 +1927,15 @@ client6_recvreply(ifp, dh6, len, optinfo
 		    &optinfo->serverID, ev->authparam);
 	}
 
+	/*
+	 * Call the configuration script, if specified, to handle various
+	 * configuration parameters.
+	 */
+	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0) {
+		dprintf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
+		client6_script(ifp->scriptpath, state, optinfo);
+	}
+
 	dhcp6_remove_event(ev);
 
 	if (state == DHCP6S_RELEASE) {
