--- dhcp6c.c.orig	2008-06-15 07:48:41 UTC
+++ dhcp6c.c
@@ -55,6 +55,8 @@
 #include <netinet6/in6_var.h>
 #endif
 
+#define __PFSENSE__ 1
+
 #include <arpa/inet.h>
 #include <netdb.h>
 
@@ -74,7 +76,9 @@
 #include <timer.h>
 #include <dhcp6c.h>
 #include <control.h>
+#ifndef __PFSENSE__
 #include <dhcp6_ctl.h>
+#endif
 #include <dhcp6c_ia.h>
 #include <prefixconf.h>
 #include <auth.h>
@@ -150,6 +154,8 @@ extern int client6_script __P((char *, i
 
 #define MAX_ELAPSED_TIME 0xffff
 
+int opt_norelease = 0;
+
 int
 main(argc, argv)
 	int argc;
@@ -169,7 +175,7 @@ main(argc, argv)
 	else
 		progname++;
 
-	while ((ch = getopt(argc, argv, "c:dDfik:p:")) != -1) {
+	while ((ch = getopt(argc, argv, "c:ndDfik:p:")) != -1) {
 		switch (ch) {
 		case 'c':
 			conffile = optarg;
@@ -192,6 +198,9 @@ main(argc, argv)
 		case 'p':
 			pid_file = optarg;
 			break;
+		case 'n':
+			opt_norelease = 1;
+			break;
 		default:
 			usage();
 			exit(0);
@@ -246,7 +255,7 @@ static void
 usage()
 {
 
-	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfi] "
+	fprintf(stderr, "usage: dhcp6c [-c configfile] [-ndDfi] "
 	    "[-p pid-file] interface [interfaces...]\n");
 }
 
@@ -264,13 +273,16 @@ client6_init()
 		dprintf(LOG_ERR, FNAME, "failed to get a DUID");
 		exit(1);
 	}
-
+	else {
+	    dprintf(LOG_ERR, FNAME, "loaded DUID from %s",DUID_FILE);
+	}
+#ifndef __PFSENSE__
 	if (dhcp6_ctl_authinit(ctlkeyfile, &ctlkey, &ctldigestlen) != 0) {
 		dprintf(LOG_NOTICE, FNAME,
 		    "failed initialize control message authentication");
 		/* run the server anyway */
 	}
-
+#endif
 	memset(&hints, 0, sizeof(hints));
 	hints.ai_family = PF_INET6;
 	hints.ai_socktype = SOCK_DGRAM;
@@ -357,7 +369,7 @@ client6_init()
 	memcpy(&sa6_allagent_storage, res->ai_addr, res->ai_addrlen);
 	sa6_allagent = (const struct sockaddr_in6 *)&sa6_allagent_storage;
 	freeaddrinfo(res);
-
+#ifndef __PFSENSE__
 	/* set up control socket */
 	if (ctlkey == NULL)
 		dprintf(LOG_NOTICE, FNAME, "skip opening control port");
@@ -367,7 +379,7 @@ client6_init()
 		    "failed to initialize control channel");
 		exit(1);
 	}
-
+#endif
 	if (signal(SIGHUP, client6_signal) == SIG_ERR) {
 		dprintf(LOG_WARNING, FNAME, "failed to set signal: %s",
 		    strerror(errno));
@@ -523,12 +535,13 @@ client6_mainloop()
 		FD_ZERO(&r);
 		FD_SET(sock, &r);
 		maxsock = sock;
+#ifndef __PFSENSE__
 		if (ctlsock >= 0) {
 			FD_SET(ctlsock, &r);
 			maxsock = (sock > ctlsock) ? sock : ctlsock;
 			(void)dhcp6_ctl_setreadfds(&r, &maxsock);
 		}
-
+#endif
 		ret = select(maxsock + 1, &r, NULL, NULL, w);
 
 		switch (ret) {
@@ -545,7 +558,8 @@ client6_mainloop()
 			break;
 		}
 		if (FD_ISSET(sock, &r))
-			client6_recv();
+			client6_recv();		
+#ifndef __PFSENSE__			
 		if (ctlsock >= 0) {
 			if (FD_ISSET(ctlsock, &r)) {
 				(void)dhcp6_ctl_acceptcommand(ctlsock,
@@ -553,6 +567,7 @@ client6_mainloop()
 			}
 			(void)dhcp6_ctl_readcommand(&r);
 		}
+#endif		
 	}
 }
 
@@ -606,7 +621,7 @@ get_ifname(bpp, lenp, ifbuf, ifbuflen)
 
 	return (0);
 }
-
+#ifndef __PFSENSE__
 static int
 client6_do_ctlcommand(buf, len)
 	char *buf;
@@ -728,7 +743,7 @@ client6_do_ctlcommand(buf, len)
 
   	return (DHCP6CTL_R_DONE);
 }
-
+#endif
 static void
 client6_reload()
 {
@@ -743,7 +758,7 @@ client6_reload()
 
 	return;
 }
-
+#ifndef __PFSENSE__
 static int
 client6_ifctl(ifname, command)
 	char *ifname;
@@ -785,7 +800,7 @@ client6_ifctl(ifname, command)
 
 	return (0);
 }
-
+#endif
 static struct dhcp6_timer *
 client6_expire_refreshtime(arg)
 	void *arg;
@@ -1459,6 +1474,7 @@ client6_recv()
 	switch(dh6->dh6_msgtype) {
 	case DH6_ADVERTISE:
 		(void)client6_recvadvert(ifp, dh6, len, &optinfo);
+		dprintf(LOG_INFO, FNAME, "dhcp6c Received ADVERTISE");
 		break;
 	case DH6_REPLY:
 		(void)client6_recvreply(ifp, dh6, len, &optinfo);
@@ -1721,6 +1737,31 @@ client6_recvreply(ifp, dh6, len, optinfo
 		dprintf(LOG_INFO, FNAME, "unexpected reply");
 		return (-1);
 	}
+	// Log_received_reply
+	
+	switch(state)
+	{
+	  
+	  case DHCP6S_INFOREQ:
+	  dprintf(LOG_INFO, FNAME, "dhcp6c Received INFOREQ");
+	  break;
+	  
+	  case DHCP6S_REQUEST:
+	    dprintf(LOG_INFO, FNAME, "dhcp6c Received REQUEST");
+	  break;
+	  case DHCP6S_RENEW:
+	     dprintf(LOG_INFO, FNAME, "dhcp6c Received INFO");
+	  break;
+	  case DHCP6S_REBIND:
+	     dprintf(LOG_INFO, FNAME, "dhcp6c Received REBIND");
+	  break;
+	  case DHCP6S_RELEASE:
+	     dprintf(LOG_INFO, FNAME, "dhcp6c Received RELEASE");
+	  break;
+	  case DHCP6S_SOLICIT:
+	     dprintf(LOG_INFO, FNAME, "dhcp6c Received SOLICIT");
+	  break;
+	}
 
 	/* A Reply message must contain a Server ID option */
 	if (optinfo->serverID.duid_len == 0) {
