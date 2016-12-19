*** dhcp6c.c.orig	Tue Nov 29 18:08:04 2016
--- dhcp6c.c	Sat Dec  3 16:41:24 2016
***************
*** 55,60 ****
--- 55,62 ----
  #include <netinet6/in6_var.h>
  #endif
  
+ #define __PFSENSE__ 1
+ 
  #include <arpa/inet.h>
  #include <netdb.h>
  
***************
*** 67,72 ****
--- 69,75 ----
  #include <string.h>
  #include <err.h>
  #include <ifaddrs.h>
+ #include <fcntl.h>
  
  #include <dhcp6.h>
  #include <config.h>
***************
*** 74,80 ****
--- 77,85 ----
  #include <timer.h>
  #include <dhcp6c.h>
  #include <control.h>
+ #ifndef __PFSENSE__
  #include <dhcp6_ctl.h>
+ #endif
  #include <dhcp6c_ia.h>
  #include <prefixconf.h>
  #include <auth.h>
***************
*** 85,94 ****
  #define SIGF_TERM 0x1
  #define SIGF_HUP 0x2
  
  const dhcp6_mode_t dhcp6_mode = DHCP6_MODE_CLIENT;
  
  int sock;	/* inbound/outbound udp port */
- int rtsock;	/* routing socket */
  int ctlsock = -1;		/* control TCP port */
  char *ctladdr = DEFAULT_CLIENT_CONTROL_ADDR;
  char *ctlport = DEFAULT_CLIENT_CONTROL_PORT;
--- 90,99 ----
  #define SIGF_TERM 0x1
  #define SIGF_HUP 0x2
  
+ 
  const dhcp6_mode_t dhcp6_mode = DHCP6_MODE_CLIENT;
  
  int sock;	/* inbound/outbound udp port */
  int ctlsock = -1;		/* control TCP port */
  char *ctladdr = DEFAULT_CLIENT_CONTROL_ADDR;
  char *ctlport = DEFAULT_CLIENT_CONTROL_PORT;
***************
*** 150,155 ****
--- 155,162 ----
  
  #define MAX_ELAPSED_TIME 0xffff
  
+ int opt_norelease = 0;
+ 
  int
  main(argc, argv)
  	int argc;
***************
*** 169,175 ****
  	else
  		progname++;
  
! 	while ((ch = getopt(argc, argv, "c:dDfik:p:")) != -1) {
  		switch (ch) {
  		case 'c':
  			conffile = optarg;
--- 176,182 ----
  	else
  		progname++;
  
! 	while ((ch = getopt(argc, argv, "c:ndDfik:p:")) != -1) {
  		switch (ch) {
  		case 'c':
  			conffile = optarg;
***************
*** 192,197 ****
--- 199,208 ----
  		case 'p':
  			pid_file = optarg;
  			break;
+ 		case 'n':
+ 			opt_norelease = 1;
+ 			break;
+ 			
  		default:
  			usage();
  			exit(0);
***************
*** 246,252 ****
  usage()
  {
  
! 	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfi] "
  	    "[-p pid-file] interface [interfaces...]\n");
  }
  
--- 257,263 ----
  usage()
  {
  
! 	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfin] "
  	    "[-p pid-file] interface [interfaces...]\n");
  }
  
***************
*** 257,276 ****
  {
  	struct addrinfo hints, *res;
  	static struct sockaddr_in6 sa6_allagent_storage;
! 	int error, on = 1;
  
  	/* get our DUID */
  	if (get_duid(DUID_FILE, &client_duid)) {
  		dprintf(LOG_ERR, FNAME, "failed to get a DUID");
  		exit(1);
  	}
! 
  	if (dhcp6_ctl_authinit(ctlkeyfile, &ctlkey, &ctldigestlen) != 0) {
  		dprintf(LOG_NOTICE, FNAME,
  		    "failed initialize control message authentication");
  		/* run the server anyway */
  	}
! 
  	memset(&hints, 0, sizeof(hints));
  	hints.ai_family = PF_INET6;
  	hints.ai_socktype = SOCK_DGRAM;
--- 268,290 ----
  {
  	struct addrinfo hints, *res;
  	static struct sockaddr_in6 sa6_allagent_storage;
! 	int error, on = 0;
  
  	/* get our DUID */
  	if (get_duid(DUID_FILE, &client_duid)) {
  		dprintf(LOG_ERR, FNAME, "failed to get a DUID");
  		exit(1);
  	}
! 	else {
! 	    dprintf(LOG_ERR, FNAME, "loaded DUID from %s",DUID_FILE);
! 	}
! #ifndef __PFSENSE__
  	if (dhcp6_ctl_authinit(ctlkeyfile, &ctlkey, &ctldigestlen) != 0) {
  		dprintf(LOG_NOTICE, FNAME,
  		    "failed initialize control message authentication");
  		/* run the server anyway */
  	}
! #endif
  	memset(&hints, 0, sizeof(hints));
  	hints.ai_family = PF_INET6;
  	hints.ai_socktype = SOCK_DGRAM;
***************
*** 287,292 ****
--- 301,320 ----
  		dprintf(LOG_ERR, FNAME, "socket");
  		exit(1);
  	}
+ 
+ 	if ((on = fcntl(sock, F_GETFL, 0)) == -1) {
+ 		dprintf(LOG_ERR, FNAME, "fctnl getflags");
+ 		exit(1);
+ 	}
+ 
+ 	on |= FD_CLOEXEC;
+ 
+ 	if ((on = fcntl(sock, F_SETFL, on)) == -1) {
+ 		dprintf(LOG_ERR, FNAME, "fctnl setflags");
+ 		exit(1);
+ 	}
+ 
+ 	on = 1;
  	if (setsockopt(sock, SOL_SOCKET, SO_REUSEPORT,
  		       &on, sizeof(on)) < 0) {
  		dprintf(LOG_ERR, FNAME,
***************
*** 337,349 ****
  	}
  	freeaddrinfo(res);
  
- 	/* open a routing socket to watch the routing table */
- 	if ((rtsock = socket(PF_ROUTE, SOCK_RAW, 0)) < 0) {
- 		dprintf(LOG_ERR, FNAME, "open a routing socket: %s",
- 		    strerror(errno));
- 		exit(1);
- 	}
- 
  	memset(&hints, 0, sizeof(hints));
  	hints.ai_family = PF_INET6;
  	hints.ai_socktype = SOCK_DGRAM;
--- 365,370 ----
***************
*** 357,363 ****
  	memcpy(&sa6_allagent_storage, res->ai_addr, res->ai_addrlen);
  	sa6_allagent = (const struct sockaddr_in6 *)&sa6_allagent_storage;
  	freeaddrinfo(res);
! 
  	/* set up control socket */
  	if (ctlkey == NULL)
  		dprintf(LOG_NOTICE, FNAME, "skip opening control port");
--- 378,384 ----
  	memcpy(&sa6_allagent_storage, res->ai_addr, res->ai_addrlen);
  	sa6_allagent = (const struct sockaddr_in6 *)&sa6_allagent_storage;
  	freeaddrinfo(res);
! #ifndef __PFSENSE__
  	/* set up control socket */
  	if (ctlkey == NULL)
  		dprintf(LOG_NOTICE, FNAME, "skip opening control port");
***************
*** 367,373 ****
  		    "failed to initialize control channel");
  		exit(1);
  	}
! 
  	if (signal(SIGHUP, client6_signal) == SIG_ERR) {
  		dprintf(LOG_WARNING, FNAME, "failed to set signal: %s",
  		    strerror(errno));
--- 388,394 ----
  		    "failed to initialize control channel");
  		exit(1);
  	}
! #endif
  	if (signal(SIGHUP, client6_signal) == SIG_ERR) {
  		dprintf(LOG_WARNING, FNAME, "failed to set signal: %s",
  		    strerror(errno));
***************
*** 459,465 ****
  			ev_next = TAILQ_NEXT(ev, link);
  
  			if (ev->state == DHCP6S_RELEASE)
! 				continue; /* keep it for now */
  
  			dhcp6_remove_event(ev);
  		}
--- 480,486 ----
  			ev_next = TAILQ_NEXT(ev, link);
  
  			if (ev->state == DHCP6S_RELEASE)
! 				  continue; /* keep it for now */
  
  			dhcp6_remove_event(ev);
  		}
***************
*** 523,534 ****
  		FD_ZERO(&r);
  		FD_SET(sock, &r);
  		maxsock = sock;
  		if (ctlsock >= 0) {
  			FD_SET(ctlsock, &r);
  			maxsock = (sock > ctlsock) ? sock : ctlsock;
  			(void)dhcp6_ctl_setreadfds(&r, &maxsock);
  		}
! 
  		ret = select(maxsock + 1, &r, NULL, NULL, w);
  
  		switch (ret) {
--- 544,556 ----
  		FD_ZERO(&r);
  		FD_SET(sock, &r);
  		maxsock = sock;
+ #ifndef __PFSENSE__
  		if (ctlsock >= 0) {
  			FD_SET(ctlsock, &r);
  			maxsock = (sock > ctlsock) ? sock : ctlsock;
  			(void)dhcp6_ctl_setreadfds(&r, &maxsock);
  		}
! #endif
  		ret = select(maxsock + 1, &r, NULL, NULL, w);
  
  		switch (ret) {
***************
*** 546,551 ****
--- 568,574 ----
  		}
  		if (FD_ISSET(sock, &r))
  			client6_recv();
+ #ifndef __PFSENSE__
  		if (ctlsock >= 0) {
  			if (FD_ISSET(ctlsock, &r)) {
  				(void)dhcp6_ctl_acceptcommand(ctlsock,
***************
*** 553,558 ****
--- 576,582 ----
  			}
  			(void)dhcp6_ctl_readcommand(&r);
  		}
+ #endif
  	}
  }
  
***************
*** 596,602 ****
  	if (*lenp < ifnamelen || ifnamelen > ifbuflen)
  		return (-1);
  
! 	memset(ifbuf, 0, sizeof(ifbuf));
  	memcpy(ifbuf, *bpp, ifnamelen);
  	if (ifbuf[ifbuflen - 1] != '\0')
  		return (-1);	/* not null terminated */
--- 620,626 ----
  	if (*lenp < ifnamelen || ifnamelen > ifbuflen)
  		return (-1);
  
! 	memset(ifbuf, 0, ifbuflen);
  	memcpy(ifbuf, *bpp, ifnamelen);
  	if (ifbuf[ifbuflen - 1] != '\0')
  		return (-1);	/* not null terminated */
***************
*** 606,612 ****
  
  	return (0);
  }
! 
  static int
  client6_do_ctlcommand(buf, len)
  	char *buf;
--- 630,636 ----
  
  	return (0);
  }
! #ifndef __PFSENSE__
  static int
  client6_do_ctlcommand(buf, len)
  	char *buf;
***************
*** 728,734 ****
  
    	return (DHCP6CTL_R_DONE);
  }
! 
  static void
  client6_reload()
  {
--- 752,758 ----
  
    	return (DHCP6CTL_R_DONE);
  }
! #endif
  static void
  client6_reload()
  {
***************
*** 743,749 ****
  
  	return;
  }
! 
  static int
  client6_ifctl(ifname, command)
  	char *ifname;
--- 767,773 ----
  
  	return;
  }
! #ifndef __PFSENSE__
  static int
  client6_ifctl(ifname, command)
  	char *ifname;
***************
*** 763,768 ****
--- 787,801 ----
  
  	switch(command) {
  	case DHCP6CTL_COMMAND_START:
+ 		/*
+ 		 * The ifid might have changed, so reset it before releasing the
+ 		 * lease.
+ 		 */
+ 		if (ifreset(ifp)) {
+ 			dprintf(LOG_NOTICE, FNAME, "failed to reset %s",
+ 			    ifname);
+ 			return (-1);
+ 		}
  		free_resources(ifp);
  		if (client6_start(ifp)) {
  			dprintf(LOG_NOTICE, FNAME, "failed to restart %s",
***************
*** 785,791 ****
  
  	return (0);
  }
! 
  static struct dhcp6_timer *
  client6_expire_refreshtime(arg)
  	void *arg;
--- 818,824 ----
  
  	return (0);
  }
! #endif
  static struct dhcp6_timer *
  client6_expire_refreshtime(arg)
  	void *arg;
***************
*** 929,935 ****
  			    "failed to create a new event data");
  			goto fail;
  		}
! 		memset(evd, 0, sizeof(evd));
  
  		memset(&iaparam, 0, sizeof(iaparam));
  		iaparam.iaid = iac->iaid;
--- 962,968 ----
  			    "failed to create a new event data");
  			goto fail;
  		}
! 		memset(evd, 0, sizeof(*evd));
  
  		memset(&iaparam, 0, sizeof(iaparam));
  		iaparam.iaid = iac->iaid;
***************
*** 1354,1360 ****
  		goto end;
  	}
  
! 	dprintf(LOG_DEBUG, FNAME, "send %s to %s",
  	    dhcp6msgstr(dh6->dh6_msgtype), addr2str((struct sockaddr *)&dst));
  
    end:
--- 1387,1393 ----
  		goto end;
  	}
  
! 	dprintf(LOG_INFO, FNAME, "send %s to %s",
  	    dhcp6msgstr(dh6->dh6_msgtype), addr2str((struct sockaddr *)&dst));
  
    end:
***************
*** 1459,1464 ****
--- 1492,1498 ----
  	switch(dh6->dh6_msgtype) {
  	case DH6_ADVERTISE:
  		(void)client6_recvadvert(ifp, dh6, len, &optinfo);
+ 		 dprintf(LOG_INFO, FNAME, "dhcp6c Received ADVERTISE");
  		break;
  	case DH6_REPLY:
  		(void)client6_recvreply(ifp, dh6, len, &optinfo);
***************
*** 1721,1726 ****
--- 1755,1790 ----
  		dprintf(LOG_INFO, FNAME, "unexpected reply");
  		return (-1);
  	}
+ 	// Log_received_reply
+ 	
+ 	switch(state)
+ 	{
+ 	  
+ 	  case DHCP6S_INFOREQ:
+ 	  dprintf(LOG_INFO, FNAME, "dhcp6c Received INFOREQ");
+ 	  break;
+ 	  
+ 	  case DHCP6S_REQUEST:
+ 	    dprintf(LOG_INFO, FNAME, "dhcp6c Received REQUEST");
+ 	  break;
+ 	  case DHCP6S_RENEW:
+ 	     dprintf(LOG_INFO, FNAME, "dhcp6c Received INFO");
+ 	  break;
+ 	  case DHCP6S_REBIND:
+ 	     dprintf(LOG_INFO, FNAME, "dhcp6c Received REBIND");
+ 	  break;
+ 	  case DHCP6S_RELEASE:
+ 	     dprintf(LOG_INFO, FNAME, "dhcp6c Received RELEASE");
+ 	  break;
+ 	  case DHCP6S_SOLICIT:
+ 	     dprintf(LOG_INFO, FNAME, "dhcp6c Received SOLICIT");
+ 	  break;
+ 	    
+ 	  
+ 	  
+ 	}
+ 	
+ 	
  
  	/* A Reply message must contain a Server ID option */
  	if (optinfo->serverID.duid_len == 0) {
***************
*** 1828,1842 ****
  	}
  
  	/*
- 	 * Call the configuration script, if specified, to handle various
- 	 * configuration parameters.
- 	 */
- 	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0) {
- 		dprintf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
- 		client6_script(ifp->scriptpath, state, optinfo);
- 	}
- 
- 	/*
  	 * Set refresh timer for configuration information specified in
  	 * information-request.  If the timer value is specified by the server
  	 * in an information refresh time option, use it; use the protocol
--- 1892,1897 ----
***************
*** 1888,1893 ****
--- 1943,1957 ----
  		    &optinfo->serverID, ev->authparam);
  	}
  
+ 	/*
+ 	 * Call the configuration script, if specified, to handle various
+ 	 * configuration parameters.
+ 	 */
+ 	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0) {
+ 		dprintf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
+ 		client6_script(ifp->scriptpath, state, optinfo);
+ 	}
+ 
  	dhcp6_remove_event(ev);
  
  	if (state == DHCP6S_RELEASE) {
