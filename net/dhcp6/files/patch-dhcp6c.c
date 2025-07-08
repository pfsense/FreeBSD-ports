--- dhcp6c.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6c.c
@@ -2,7 +2,7 @@
 /*
  * Copyright (C) 1998 and 1999 WIDE Project.
  * All rights reserved.
- * 
+ *
  * Redistribution and use in source and binary forms, with or without
  * modification, are permitted provided that the following conditions
  * are met:
@@ -14,7 +14,7 @@
  * 3. Neither the name of the project nor the names of its contributors
  *    may be used to endorse or promote products derived from this software
  *    without specific prior written permission.
- * 
+ *
  * THIS SOFTWARE IS PROVIDED BY THE PROJECT AND CONTRIBUTORS ``AS IS'' AND
  * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
  * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
@@ -69,16 +69,14 @@
 #include <ifaddrs.h>
 #include <fcntl.h>
 
-#include <dhcp6.h>
-#include <config.h>
-#include <common.h>
-#include <timer.h>
-#include <dhcp6c.h>
-#include <control.h>
-#include <dhcp6_ctl.h>
-#include <dhcp6c_ia.h>
-#include <prefixconf.h>
-#include <auth.h>
+#include "dhcp6.h"
+#include "config.h"
+#include "common.h"
+#include "timer.h"
+#include "dhcp6c.h"
+#include "dhcp6c_ia.h"
+#include "prefixconf.h"
+#include "auth.h"
 
 static int debug = 0;
 static int exit_ok = 0;
@@ -89,21 +87,16 @@ static sig_atomic_t sig_flags = 0;
 
 const dhcp6_mode_t dhcp6_mode = DHCP6_MODE_CLIENT;
 
-int sock;	/* inbound/outbound udp port */
-int ctlsock = -1;		/* control TCP port */
-char *ctladdr = DEFAULT_CLIENT_CONTROL_ADDR;
-char *ctlport = DEFAULT_CLIENT_CONTROL_PORT;
+static int sock;	/* inbound/outbound udp port */
 
-#define DEFAULT_KEYFILE SYSCONFDIR "/dhcp6cctlkey"
 #define CTLSKEW 300
 
-static char *conffile = DHCP6C_CONF;
+static const char *conffile = DHCP6C_CONF;
 
 static const struct sockaddr_in6 *sa6_allagent;
 static struct duid client_duid;
-static char *pid_file = DHCP6C_PIDFILE;
+static const char *pid_file = DHCP6C_PIDFILE;
 
-static char *ctlkeyfile = DEFAULT_KEYFILE;
 static struct keyinfo *ctlkey = NULL;
 static int ctldigestlen;
 
@@ -111,57 +104,53 @@ static int infreq_mode = 0;
 
 int opt_norelease;
 
-static inline int get_val32 __P((char **, int *, u_int32_t *));
-static inline int get_ifname __P((char **, int *, char *, int));
+static void usage(void);
+static void client6_init(void);
+static void client6_startall(int);
+static void free_resources(struct dhcp6_if *);
+static void client6_mainloop(void);
+static void check_exit(void);
+static void process_signals(void);
+static struct dhcp6_serverinfo *find_server(struct dhcp6_event *,
+						 struct duid *);
+static struct dhcp6_serverinfo *select_server(struct dhcp6_event *);
+static void client6_recv(void);
+static int client6_recvadvert(struct dhcp6_if *, struct dhcp6 *,
+				   ssize_t, struct dhcp6_optinfo *);
+static int client6_recvreply(struct dhcp6_if *, struct dhcp6 *,
+				  ssize_t, struct dhcp6_optinfo *);
+static void client6_signal(int);
+static struct dhcp6_event *find_event_withid(struct dhcp6_if *,
+						  uint32_t);
+static int construct_confdata(struct dhcp6_if *, struct dhcp6_event *);
+static int construct_reqdata(struct dhcp6_if *, struct dhcp6_optinfo *,
+    struct dhcp6_event *);
+static void destruct_iadata(struct dhcp6_eventdata *);
+static void tv_sub(struct timeval *, struct timeval *, struct timeval *);
+static struct dhcp6_timer *client6_expire_refreshtime(void *);
+static int process_auth(struct authparam *, struct dhcp6 *dh6, ssize_t,
+    struct dhcp6_optinfo *);
+static int set_auth(struct dhcp6_event *, struct dhcp6_optinfo *);
 
-static void usage __P((void));
-static void client6_init __P((void));
-static void client6_startall __P((int));
-static void free_resources __P((struct dhcp6_if *));
-static void client6_mainloop __P((void));
-static int client6_do_ctlcommand __P((char *, ssize_t));
-static void client6_reload __P((void));
-static int client6_ifctl __P((char *ifname, u_int16_t));
-static void check_exit __P((void));
-static void process_signals __P((void));
-static struct dhcp6_serverinfo *find_server __P((struct dhcp6_event *,
-						 struct duid *));
-static struct dhcp6_serverinfo *select_server __P((struct dhcp6_event *));
-static void client6_recv __P((void));
-static int client6_recvadvert __P((struct dhcp6_if *, struct dhcp6 *,
-				   ssize_t, struct dhcp6_optinfo *));
-static int client6_recvreply __P((struct dhcp6_if *, struct dhcp6 *,
-				  ssize_t, struct dhcp6_optinfo *));
-static void client6_signal __P((int));
-static struct dhcp6_event *find_event_withid __P((struct dhcp6_if *,
-						  u_int32_t));
-static int construct_confdata __P((struct dhcp6_if *, struct dhcp6_event *));
-static int construct_reqdata __P((struct dhcp6_if *, struct dhcp6_optinfo *,
-    struct dhcp6_event *));
-static void destruct_iadata __P((struct dhcp6_eventdata *));
-static void tv_sub __P((struct timeval *, struct timeval *, struct timeval *));
-static struct dhcp6_timer *client6_expire_refreshtime __P((void *));
-static int process_auth __P((struct authparam *, struct dhcp6 *dh6, ssize_t,
-    struct dhcp6_optinfo *));
-static int set_auth __P((struct dhcp6_event *, struct dhcp6_optinfo *));
+struct dhcp6_timer *client6_timo(void *);
+int client6_start(struct dhcp6_if *);
+static void info_printf(const char *, ...);
 
-struct dhcp6_timer *client6_timo __P((void *));
-int client6_start __P((struct dhcp6_if *));
-static void info_printf __P((const char *, ...));
+static void init_cli_if(int argc, char **argv);
 
-extern int client6_script __P((char *, int, struct dhcp6_optinfo *));
+int use_all_config_if;
+static int saved_cli_if_count;
+static char **saved_cli_if;
 
 #define MAX_ELAPSED_TIME 0xffff
 
 int
-main(argc, argv)
-	int argc;
-	char **argv;
+main(int argc, char *argv[])
 {
 	int ch, pid;
 	char *progname;
 	FILE *pidfp;
-	struct dhcp6_if *ifp;
+	struct cf_namelist *ifnamep;
 
 #ifndef HAVE_ARC4RANDOM
 	srandom(time(NULL) & getpid());
@@ -172,7 +161,7 @@ main(argc, argv)
 	else
 		progname++;
 
-	while ((ch = getopt(argc, argv, "c:dDfik:np:")) != -1) {
+	while ((ch = getopt(argc, argv, "c:dDfinp:")) != -1) {
 		switch (ch) {
 		case 'c':
 			conffile = optarg;
@@ -189,9 +178,6 @@ main(argc, argv)
 		case 'i':
 			infreq_mode = 1;
 			break;
-		case 'k':
-			ctlkeyfile = optarg;
-			break;
 		case 'n':
 			opt_norelease = 1;
 			break;
@@ -206,27 +192,29 @@ main(argc, argv)
 	argc -= optind;
 	argv += optind;
 
-	if (argc == 0) {
-		usage();
-		exit(0);
-	}
-
 	if (foreground == 0)
 		openlog(progname, LOG_NDELAY|LOG_PID, LOG_DAEMON);
 
 	setloglevel(debug);
 
 	client6_init();
-	while (argc-- > 0) { 
-		if ((ifp = ifinit(argv[0])) == NULL) {
-			d_printf(LOG_ERR, FNAME, "failed to initialize %s",
-			    argv[0]);
-			exit(1);
-		}
-		argv++;
+
+	/*
+	 * Doing away with the need for command line interfaces If this is set
+	 * config.c initializes the interface after parsing it.	This makes cfparse.y
+	 * have valid entries in dhcp6_if before it invokes configure_commit() at the
+	 * end of it's parse. Only one parse pass needed now.
+	 */
+	use_all_config_if = (argc == 0);
+
+	if (!use_all_config_if) {
+		saved_cli_if = argv;
+		saved_cli_if_count = argc;
+		init_cli_if(saved_cli_if_count, saved_cli_if);
+
 	}
 
-	if (infreq_mode == 0 && (cfparse(conffile)) != 0) {
+	if (infreq_mode == 0 && cfparse(conffile)) {
 		d_printf(LOG_ERR, FNAME, "failed to parse configuration file");
 		exit(1);
 	}
@@ -253,13 +241,25 @@ usage()
 {
 
 	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfin] "
-	    "[-p pid-file] interface [interfaces...]\n");
+	    "[-p pid-file] [interfaces...]\n");
 }
 
 /*------------------------------------------------------------*/
 
+static void
+init_cli_if(int argc, char **argv) {
+	while (argc-- > 0) {
+		if (ifinit(argv[0]) == NULL) {
+			d_printf(LOG_ERR, FNAME, "failed to initialize %s",
+			    argv[0]);
+			exit(1);
+		}
+		argv++;
+	}
+}
+
 void
-client6_init()
+client6_init(void)
 {
 	struct addrinfo hints, *res;
 	static struct sockaddr_in6 sa6_allagent_storage;
@@ -271,12 +271,6 @@ client6_init()
 		exit(1);
 	}
 
-	if (dhcp6_ctl_authinit(ctlkeyfile, &ctlkey, &ctldigestlen) != 0) {
-		d_printf(LOG_NOTICE, FNAME,
-		    "failed initialize control message authentication");
-		/* run the server anyway */
-	}
-
 	memset(&hints, 0, sizeof(hints));
 	hints.ai_family = PF_INET6;
 	hints.ai_socktype = SOCK_DGRAM;
@@ -371,16 +365,6 @@ client6_init()
 	sa6_allagent = (const struct sockaddr_in6 *)&sa6_allagent_storage;
 	freeaddrinfo(res);
 
-	/* set up control socket */
-	if (ctlkey == NULL)
-		d_printf(LOG_NOTICE, FNAME, "skip opening control port");
-	else if (dhcp6_ctl_init(ctladdr, ctlport,
-	    DHCP6CTL_DEF_COMMANDQUEUELEN, &ctlsock)) {
-		d_printf(LOG_ERR, FNAME,
-		    "failed to initialize control channel");
-		exit(1);
-	}
-
 	if (signal(SIGHUP, client6_signal) == SIG_ERR) {
 		d_printf(LOG_WARNING, FNAME, "failed to set signal: %s",
 		    strerror(errno));
@@ -391,6 +375,11 @@ client6_init()
 		    strerror(errno));
 		exit(1);
 	}
+	if (signal(SIGINT, client6_signal) == SIG_ERR) {
+		d_printf(LOG_WARNING, FNAME, "failed to set signal: %s",
+		    strerror(errno));
+		exit(1);
+	}
 	if (signal(SIGUSR1, client6_signal) == SIG_ERR) {
 		d_printf(LOG_WARNING, FNAME, "failed to set signal: %s",
 		    strerror(errno));
@@ -399,8 +388,7 @@ client6_init()
 }
 
 int
-client6_start(ifp)
-	struct dhcp6_if *ifp;
+client6_start(struct dhcp6_if *ifp)
 {
 	struct dhcp6_event *ev;
 
@@ -438,8 +426,7 @@ client6_start(ifp)
 }
 
 static void
-client6_startall(isrestart)
-	int isrestart;
+client6_startall(int isrestart)
 {
 	struct dhcp6_if *ifp;
 
@@ -455,8 +442,7 @@ client6_startall(isrestart)
 }
 
 static void
-free_resources(freeifp)
-	struct dhcp6_if *freeifp;
+free_resources(struct dhcp6_if *freeifp)
 {
 	struct dhcp6_if *ifp;
 
@@ -485,7 +471,7 @@ free_resources(freeifp)
 }
 
 static void
-check_exit()
+check_exit(void)
 {
 	struct dhcp6_if *ifp;
 
@@ -501,41 +487,58 @@ check_exit()
 			return;
 	}
 	for (ifp = dhcp6_if; ifp; ifp = ifp->next)
-		client6_script(ifp->scriptpath, DHCP6S_EXIT, NULL);
+		client6_script(ifp->scriptpath, DHCP6S_EXIT, NULL, ifp);
 
 	/* We have no existing event.  Do exit. */
 	d_printf(LOG_INFO, FNAME, "exiting");
 
 	unlink(pid_file);
+
+	if (foreground) {
+		fflush(stdout);
+		fflush(stderr);
+	}
+
 	exit(0);
 }
 
 static void
-process_signals()
+process_signals(void)
 {
+	struct cf_namelist *ifnamep;
+	struct dhcp6_if *ifp;
+
+	if ((sig_flags & SIGF_USR1)) {
+		d_printf(LOG_INFO, FNAME, "exit with%s release",
+			opt_norelease ? "out" : "");
+		opt_norelease ^= 1;
+		/* rest is same as TERM, both flags get set */
+	}
+
 	if ((sig_flags & SIGF_TERM)) {
 		exit_ok = 1;
 		free_resources(NULL);
 		check_exit();
 	}
+
 	if ((sig_flags & SIGF_HUP)) {
 		d_printf(LOG_INFO, FNAME, "restarting");
 		free_resources(NULL);
+		if (!use_all_config_if) {
+			init_cli_if(saved_cli_if_count, saved_cli_if);
+		}
+		if (cfparse(conffile)) {
+			d_printf(LOG_WARNING, FNAME,
+			    "failed to reload configuration file");
+		}
 		client6_startall(1);
 	}
-	if ((sig_flags & SIGF_USR1)) {
-		d_printf(LOG_INFO, FNAME, "exit without release");
-		exit_ok = 1;
-		opt_norelease = 1;
-		free_resources(NULL);
-		check_exit();
-	}
 
 	sig_flags = 0;
 }
 
 static void
-client6_mainloop()
+client6_mainloop(void)
 {
 	struct timeval *w;
 	int ret, maxsock;
@@ -550,11 +553,6 @@ client6_mainloop()
 		FD_ZERO(&r);
 		FD_SET(sock, &r);
 		maxsock = sock;
-		if (ctlsock >= 0) {
-			FD_SET(ctlsock, &r);
-			maxsock = (sock > ctlsock) ? sock : ctlsock;
-			(void)dhcp6_ctl_setreadfds(&r, &maxsock);
-		}
 
 		ret = select(maxsock + 1, &r, NULL, NULL, w);
 
@@ -573,48 +571,15 @@ client6_mainloop()
 		}
 		if (FD_ISSET(sock, &r))
 			client6_recv();
-		if (ctlsock >= 0) {
-			if (FD_ISSET(ctlsock, &r)) {
-				(void)dhcp6_ctl_acceptcommand(ctlsock,
-				    client6_do_ctlcommand);
-			}
-			(void)dhcp6_ctl_readcommand(&r);
-		}
 	}
 }
 
 static inline int
-get_val32(bpp, lenp, valp)
-	char **bpp;
-	int *lenp;
-	u_int32_t *valp;
+get_ifname(char **bpp, int *lenp, char *ifbuf, int ifbuflen)
 {
 	char *bp = *bpp;
-	int len = *lenp;
-	u_int32_t i32;
-
-	if (len < sizeof(*valp))
-		return (-1);
-
-	memcpy(&i32, bp, sizeof(i32));
-	*valp = ntohl(i32);
-
-	*bpp = bp + sizeof(*valp);
-	*lenp = len - sizeof(*valp);
-
-	return (0);
-}
-
-static inline int
-get_ifname(bpp, lenp, ifbuf, ifbuflen)
-	char **bpp;
-	int *lenp;
-	char *ifbuf;
-	int ifbuflen;
-{
-	char *bp = *bpp;
 	int len = *lenp, ifnamelen;
-	u_int32_t i32;
+	uint32_t i32;
 
 	if (get_val32(bpp, lenp, &i32))
 		return (-1);
@@ -634,196 +599,8 @@ get_ifname(bpp, lenp, ifbuf, ifbuflen)
 	return (0);
 }
 
-static int
-client6_do_ctlcommand(buf, len)
-	char *buf;
-	ssize_t len;
-{
-	struct dhcp6ctl *ctlhead;
-	u_int16_t command, version;
-	u_int32_t p32, ts, ts0;
-	int commandlen;
-	char *bp;
-	char ifname[IFNAMSIZ];
-	time_t now;
-
-	memset(ifname, 0, sizeof(ifname));
-
-	ctlhead = (struct dhcp6ctl *)buf;
-
-	command = ntohs(ctlhead->command);
-	commandlen = (int)(ntohs(ctlhead->len));
-	version = ntohs(ctlhead->version);
-	if (len != sizeof(struct dhcp6ctl) + commandlen) {
-		d_printf(LOG_ERR, FNAME,
-		    "assumption failure: command length mismatch");
-		return (DHCP6CTL_R_FAILURE);
-	}
-
-	/* replay protection and message authentication */
-	if ((now = time(NULL)) < 0) {
-		d_printf(LOG_ERR, FNAME, "failed to get current time: %s",
-		    strerror(errno));
-		return (DHCP6CTL_R_FAILURE);
-	}
-	ts0 = (u_int32_t)now;
-	ts = ntohl(ctlhead->timestamp);
-	if (ts + CTLSKEW < ts0 || (ts - CTLSKEW) > ts0) {
-		d_printf(LOG_INFO, FNAME, "timestamp is out of range");
-		return (DHCP6CTL_R_FAILURE);
-	}
-
-	if (ctlkey == NULL) {	/* should not happen!! */
-		d_printf(LOG_ERR, FNAME, "no secret key for control channel");
-		return (DHCP6CTL_R_FAILURE);
-	}
-	if (dhcp6_verify_mac(buf, len, DHCP6CTL_AUTHPROTO_UNDEF,
-	    DHCP6CTL_AUTHALG_HMACMD5, sizeof(*ctlhead), ctlkey) != 0) {
-		d_printf(LOG_INFO, FNAME, "authentication failure");
-		return (DHCP6CTL_R_FAILURE);
-	}
-
-	bp = buf + sizeof(*ctlhead) + ctldigestlen;
-	commandlen -= ctldigestlen;
-
-	if (version > DHCP6CTL_VERSION) {
-		d_printf(LOG_INFO, FNAME, "unsupported version: %d", version);
-		return (DHCP6CTL_R_FAILURE);
-	}
-
-	switch (command) {
-	case DHCP6CTL_COMMAND_RELOAD:
-		if (commandlen != 0) {
-			d_printf(LOG_INFO, FNAME, "invalid command length "
-			    "for reload: %d", commandlen);
-			return (DHCP6CTL_R_DONE);
-		}
-		client6_reload();
-		break;
-	case DHCP6CTL_COMMAND_START:
-		if (get_val32(&bp, &commandlen, &p32))
-			return (DHCP6CTL_R_FAILURE);
-		switch (p32) {
-		case DHCP6CTL_INTERFACE:
-			if (get_ifname(&bp, &commandlen, ifname,
-			    sizeof(ifname))) {
-				return (DHCP6CTL_R_FAILURE);
-			}
-			if (client6_ifctl(ifname, DHCP6CTL_COMMAND_START))
-				return (DHCP6CTL_R_FAILURE);
-			break;
-		default:
-			d_printf(LOG_INFO, FNAME,
-			    "unknown start target: %ul", p32);
-			return (DHCP6CTL_R_FAILURE);
-		}
-		break;
-	case DHCP6CTL_COMMAND_STOP:
-		if (commandlen == 0) {
-			exit_ok = 1;
-			free_resources(NULL);
-			check_exit();
-		} else {
-			if (get_val32(&bp, &commandlen, &p32))
-				return (DHCP6CTL_R_FAILURE);
-
-			switch (p32) {
-			case DHCP6CTL_INTERFACE:
-				if (get_ifname(&bp, &commandlen, ifname,
-				    sizeof(ifname))) {
-					return (DHCP6CTL_R_FAILURE);
-				}
-				if (client6_ifctl(ifname,
-				    DHCP6CTL_COMMAND_STOP)) {
-					return (DHCP6CTL_R_FAILURE);
-				}
-				break;
-			default:
-				d_printf(LOG_INFO, FNAME,
-				    "unknown start target: %ul", p32);
-				return (DHCP6CTL_R_FAILURE);
-			}
-		}
-		break;
-	default:
-		d_printf(LOG_INFO, FNAME,
-		    "unknown control command: %d (len=%d)",
-		    (int)command, commandlen);
-		return (DHCP6CTL_R_FAILURE);
-	}
-
-  	return (DHCP6CTL_R_DONE);
-}
-
-static void
-client6_reload()
-{
-	/* reload the configuration file */
-	if (cfparse(conffile) != 0) {
-		d_printf(LOG_WARNING, FNAME,
-		    "failed to reload configuration file");
-		return;
-	}
-
-	d_printf(LOG_NOTICE, FNAME, "client reloaded");
-
-	return;
-}
-
-static int
-client6_ifctl(ifname, command)
-	char *ifname;
-	u_int16_t command;
-{
-	struct dhcp6_if *ifp;
-
-	if ((ifp = find_ifconfbyname(ifname)) == NULL) {
-		d_printf(LOG_INFO, FNAME,
-		    "failed to find interface configuration for %s",
-		    ifname);
-		return (-1);
-	}
-
-	d_printf(LOG_DEBUG, FNAME, "%s interface %s",
-	    command == DHCP6CTL_COMMAND_START ? "start" : "stop", ifname);
-
-	switch(command) {
-	case DHCP6CTL_COMMAND_START:
-		/*
-		 * The ifid might have changed, so reset it before releasing the
-		 * lease.
-		 */
-		if (ifreset(ifp)) {
-			d_printf(LOG_NOTICE, FNAME, "failed to reset %s",
-			    ifname);
-			return (-1);
-		}
-		free_resources(ifp);
-		if (client6_start(ifp)) {
-			d_printf(LOG_NOTICE, FNAME, "failed to restart %s",
-			    ifname);
-			return (-1);
-		}
-		break;
-	case DHCP6CTL_COMMAND_STOP:
-		free_resources(ifp);
-		if (ifp->timer != NULL) {
-			d_printf(LOG_DEBUG, FNAME,
-			    "removed existing timer on %s", ifp->ifname);
-			dhcp6_remove_timer(&ifp->timer);
-		}
-		break;
-	default:		/* impossible case, should be a bug */
-		d_printf(LOG_ERR, FNAME, "unknown command: %d", (int)command);
-		break;
-	}
-
-	return (0);
-}
-
 static struct dhcp6_timer *
-client6_expire_refreshtime(arg)
-	void *arg;
+client6_expire_refreshtime(void *arg)
 {
 	struct dhcp6_if *ifp = arg;
 
@@ -837,8 +614,7 @@ client6_expire_refreshtime(arg)
 }
 
 struct dhcp6_timer *
-client6_timo(arg)
-	void *arg;
+client6_timo(void *arg)
 {
 	struct dhcp6_event *ev = (struct dhcp6_event *)arg;
 	struct dhcp6_if *ifp;
@@ -941,9 +717,7 @@ client6_timo(arg)
 }
 
 static int
-construct_confdata(ifp, ev)
-	struct dhcp6_if *ifp;
-	struct dhcp6_event *ev;
+construct_confdata(struct dhcp6_if *ifp, struct dhcp6_event *ev)
 {
 	struct ia_conf *iac;
 	struct dhcp6_eventdata *evd = NULL;
@@ -1025,15 +799,13 @@ construct_confdata(ifp, ev)
 	if (ial)
 		free(ial);
 	dhcp6_remove_event(ev);	/* XXX */
-	
+
 	return (-1);
 }
 
 static int
-construct_reqdata(ifp, optinfo, ev)
-	struct dhcp6_if *ifp;
-	struct dhcp6_optinfo *optinfo;
-	struct dhcp6_event *ev;
+construct_reqdata(struct dhcp6_if *ifp,
+    struct dhcp6_optinfo *optinfo, struct dhcp6_event *ev)
 {
 	struct ia_conf *iac;
 	struct dhcp6_eventdata *evd = NULL;
@@ -1121,13 +893,12 @@ construct_reqdata(ifp, optinfo, ev)
 	if (ial)
 		free(ial);
 	dhcp6_remove_event(ev);	/* XXX */
-	
+
 	return (-1);
 }
 
 static void
-destruct_iadata(evd)
-	struct dhcp6_eventdata *evd;
+destruct_iadata(struct dhcp6_eventdata *evd)
 {
 	struct dhcp6_list *ial;
 
@@ -1142,8 +913,7 @@ destruct_iadata(evd)
 }
 
 static struct dhcp6_serverinfo *
-select_server(ev)
-	struct dhcp6_event *ev;
+select_server(struct dhcp6_event *ev)
 {
 	struct dhcp6_serverinfo *s;
 
@@ -1164,26 +934,24 @@ select_server(ev)
 }
 
 static void
-client6_signal(sig)
-	int sig;
+client6_signal(int sig)
 {
 
 	switch (sig) {
+	case SIGUSR1:
+		sig_flags |= SIGF_USR1;
 	case SIGTERM:
+	case SIGINT:
 		sig_flags |= SIGF_TERM;
 		break;
 	case SIGHUP:
 		sig_flags |= SIGF_HUP;
 		break;
-	case SIGUSR1:
-		sig_flags |= SIGF_USR1;
-		break;
 	}
 }
 
 void
-client6_send(ev)
-	struct dhcp6_event *ev;
+client6_send(struct dhcp6_event *ev)
 {
 	struct dhcp6_if *ifp;
 	char buf[BUFSIZ];
@@ -1299,7 +1067,7 @@ client6_send(ev)
 			/*
 			 * Perhaps we are nervous too much, but without this
 			 * additional check, we would see an overflow in 248
-			 * days (of no responses). 
+			 * days (of no responses).
 			 */
 			et = MAX_ELAPSED_TIME;
 		} else {
@@ -1333,7 +1101,7 @@ client6_send(ev)
 			if (dhcp6_copy_list(&optinfo.iana_list,
 			    (struct dhcp6_list *)evd->data)) {
 				d_printf(LOG_NOTICE, FNAME,
-				    "failed to add an IAPD");
+				    "failed to add an IANA");
 				goto end;
 			}
 			break;
@@ -1351,6 +1119,8 @@ client6_send(ev)
 		goto end;
 	}
 
+	rawop_copy_list(&optinfo.rawops, &ifp->rawops);
+
 	/* set options in the message */
 	if ((optlen = dhcp6_set_options(dh6->dh6_msgtype,
 	    (struct dhcp6opt *)(dh6 + 1),
@@ -1408,8 +1178,7 @@ client6_send(ev)
 
 /* result will be a - b */
 static void
-tv_sub(a, b, result)
-	struct timeval *a, *b, *result;
+tv_sub(struct timeval *a, struct timeval *b, struct timeval *result)
 {
 	if (a->tv_sec < b->tv_sec ||
 	    (a->tv_sec == b->tv_sec && a->tv_usec < b->tv_usec)) {
@@ -1430,7 +1199,7 @@ tv_sub(a, b, result)
 }
 
 static void
-client6_recv()
+client6_recv(void)
 {
 	char rbuf[BUFSIZ], cmsgbuf[BUFSIZ];
 	struct msghdr mhdr;
@@ -1466,7 +1235,7 @@ client6_recv()
 		if (cm->cmsg_level == IPPROTO_IPV6 &&
 		    cm->cmsg_type == IPV6_PKTINFO &&
 		    cm->cmsg_len == CMSG_LEN(sizeof(struct in6_pktinfo))) {
-			pi = (struct in6_pktinfo *)(CMSG_DATA(cm));
+			pi = (struct in6_pktinfo *)(void *)(CMSG_DATA(cm));
 		}
 	}
 	if (pi == NULL) {
@@ -1480,7 +1249,7 @@ client6_recv()
 		return;
 	}
 
-	if (len < sizeof(*dh6)) {
+	if ((size_t)len < sizeof(*dh6)) {
 		d_printf(LOG_INFO, FNAME, "short packet (%d bytes)", len);
 		return;
 	}
@@ -1519,16 +1288,14 @@ client6_recv()
 }
 
 static int
-client6_recvadvert(ifp, dh6, len, optinfo)
-	struct dhcp6_if *ifp;
-	struct dhcp6 *dh6;
-	ssize_t len;
-	struct dhcp6_optinfo *optinfo;
+client6_recvadvert(struct dhcp6_if *ifp, struct dhcp6 *dh6,
+    ssize_t len, struct dhcp6_optinfo *optinfo)
 {
 	struct dhcp6_serverinfo *newserver, **sp;
 	struct dhcp6_event *ev;
 	struct dhcp6_eventdata *evd;
 	struct authparam *authparam = NULL, authparam0;
+	int have_ia = -1;
 
 	/* find the corresponding event based on the received xid */
 	ev = find_event_withid(ifp, ntohl(dh6->dh6_xid) & DH6_XIDMASK);
@@ -1567,15 +1334,16 @@ client6_recvadvert(ifp, dh6, len, optinfo)
 	 * includes a Status Code option containing the value NoPrefixAvail
 	 * [RFC3633 Section 11.1].
 	 * Likewise, the client MUST ignore any Advertise message that includes
-	 * a Status Code option containing the value NoAddrsAvail. 
+	 * a Status Code option containing the value NoAddrsAvail.
 	 * [RFC3315 Section 17.1.3].
 	 * We only apply this when we are going to request an address or
 	 * a prefix.
 	 */
 	for (evd = TAILQ_FIRST(&ev->data_list); evd;
 	    evd = TAILQ_NEXT(evd, link)) {
-		u_int16_t stcode;
-		char *stcodestr;
+		struct dhcp6_listval *lv, *slv;
+		uint16_t stcode;
+		const char *stcodestr;
 
 		switch (evd->type) {
 		case DHCP6_EVDATA_IAPD:
@@ -1589,14 +1357,66 @@ client6_recvadvert(ifp, dh6, len, optinfo)
 		default:
 			continue;
 		}
+
 		if (dhcp6_find_listval(&optinfo->stcode_list,
 		    DHCP6_LISTVAL_STCODE, &stcode, 0)) {
 			d_printf(LOG_INFO, FNAME,
 			    "advertise contains %s status", stcodestr);
 			return (-1);
 		}
+
+		if (have_ia > 0 ||
+		    TAILQ_EMPTY((struct dhcp6_list *)evd->data)) {
+			continue;
+		}
+
+		have_ia = 0;
+
+		/* parse list of IA_PD */
+		if (evd->type == DHCP6_EVDATA_IAPD) {
+			TAILQ_FOREACH(lv, (struct dhcp6_list *)evd->data, link) {
+				slv = dhcp6_find_listval(&optinfo->iapd_list,
+				    DHCP6_LISTVAL_IAPD, &lv->val_ia, 0);
+				if (slv == NULL) {
+					continue;
+				}
+				TAILQ_FOREACH(slv, &slv->sublist, link) {
+					if (slv->type == DHCP6_LISTVAL_PREFIX6) {
+						have_ia = 1;
+						break;
+					}
+				}
+			}
+		}
+
+		/* parse list of IA_NA */
+		if (evd->type == DHCP6_EVDATA_IANA) {
+			TAILQ_FOREACH(lv, (struct dhcp6_list *)evd->data, link) {
+				slv = dhcp6_find_listval(&optinfo->iana_list,
+				    DHCP6_LISTVAL_IANA, &lv->val_ia, 0);
+				if (slv == NULL) {
+					continue;
+				}
+				TAILQ_FOREACH(slv, &slv->sublist, link) {
+					if (slv->type == DHCP6_LISTVAL_STATEFULADDR6) {
+						have_ia = 1;
+						break;
+					}
+				}
+			}
+		}
 	}
 
+	/*
+	 * Ignore message with none of requested addresses and/or
+	 * a prefixes as if NoAddrsAvail/NoPrefixAvail Status Code
+	 * was included.
+	 */
+	if (have_ia == 0) {
+		d_printf(LOG_INFO, FNAME, "advertise contains no address/prefix");
+		return (-1);
+	}
+
 	if (ev->state != DHCP6S_SOLICIT ||
 	    (ifp->send_flags & DHCIFF_RAPID_COMMIT) || infreq_mode) {
 		/*
@@ -1722,9 +1542,7 @@ client6_recvadvert(ifp, dh6, len, optinfo)
 }
 
 static struct dhcp6_serverinfo *
-find_server(ev, duid)
-	struct dhcp6_event *ev;
-	struct duid *duid;
+find_server(struct dhcp6_event *ev, struct duid *duid)
 {
 	struct dhcp6_serverinfo *s;
 
@@ -1737,11 +1555,8 @@ find_server(ev, duid)
 }
 
 static int
-client6_recvreply(ifp, dh6, len, optinfo)
-	struct dhcp6_if *ifp;
-	struct dhcp6 *dh6;
-	ssize_t len;
-	struct dhcp6_optinfo *optinfo;
+client6_recvreply(struct dhcp6_if *ifp, struct dhcp6 *dh6,
+    ssize_t len, struct dhcp6_optinfo *optinfo)
 {
 	struct dhcp6_listval *lv;
 	struct dhcp6_event *ev;
@@ -1766,26 +1581,8 @@ client6_recvreply(ifp, dh6, len, optinfo)
 		return (-1);
 	}
 
-	switch (state) {
-	case DHCP6S_INFOREQ:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received INFOREQ");
-		break;  
-	case DHCP6S_REQUEST:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received REQUEST");
-		break;
-	case DHCP6S_RENEW:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received INFO");
-		break;
-	case DHCP6S_REBIND:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received REBIND");
-		break;
-	case DHCP6S_RELEASE:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received RELEASE");
-		break;
-	case DHCP6S_SOLICIT:
-		d_printf(LOG_INFO, FNAME, "dhcp6c Received SOLICIT");
-		break;          
-	}
+	d_printf(LOG_INFO, FNAME, "Received REPLY for %s",
+	    dhcp6_event_statestr(ev));
 
 	/* A Reply message must contain a Server ID option */
 	if (optinfo->serverID.duid_len == 0) {
@@ -1921,7 +1718,7 @@ client6_recvreply(ifp, dh6, len, optinfo)
 				 */
 				d_printf(LOG_WARNING, FNAME,
 				    "refresh time is too large: %lu",
-				    (u_int32_t)refreshtime);
+				    (uint32_t)refreshtime);
 				tv.tv_sec = 0x7fffffff;	/* XXX */
 			}
 
@@ -1948,7 +1745,7 @@ client6_recvreply(ifp, dh6, len, optinfo)
 	 * Call the configuration script, if specified, to handle various
 	 * configuration parameters.
 	 */
-	client6_script(ifp->scriptpath, state, optinfo);
+	client6_script(ifp->scriptpath, state, optinfo, ifp);
 
 	dhcp6_remove_event(ev);
 
@@ -1974,9 +1771,7 @@ client6_recvreply(ifp, dh6, len, optinfo)
 }
 
 static struct dhcp6_event *
-find_event_withid(ifp, xid)
-	struct dhcp6_if *ifp;
-	u_int32_t xid;
+find_event_withid(struct dhcp6_if *ifp, uint32_t xid)
 {
 	struct dhcp6_event *ev;
 
@@ -1990,11 +1785,8 @@ find_event_withid(ifp, xid)
 }
 
 static int
-process_auth(authparam, dh6, len, optinfo)
-	struct authparam *authparam;
-	struct dhcp6 *dh6;
-	ssize_t len;
-	struct dhcp6_optinfo *optinfo;
+process_auth(struct authparam *authparam, struct dhcp6 *dh6,
+    ssize_t len, struct dhcp6_optinfo *optinfo)
 {
 	struct keyinfo *key = NULL;
 	int authenticated = 0;
@@ -2046,7 +1838,7 @@ process_auth(authparam, dh6, len, optinfo)
 			 * (from Section 21.4.5.1 of RFC3315)
 			 */
 			if (optinfo->delayedauth_keyid != key->keyid ||
-			    optinfo->delayedauth_realmlen != key->realmlen ||
+			    (size_t)optinfo->delayedauth_realmlen != key->realmlen ||
 			    memcmp(optinfo->delayedauth_realmval, key->realm,
 			    key->realmlen) != 0) {
 				d_printf(LOG_INFO, FNAME,
@@ -2116,9 +1908,7 @@ process_auth(authparam, dh6, len, optinfo)
 }
 
 static int
-set_auth(ev, optinfo)
-	struct dhcp6_event *ev;
-	struct dhcp6_optinfo *optinfo;
+set_auth(struct dhcp6_event *ev, struct dhcp6_optinfo *optinfo)
 {
 	struct authparam *authparam = ev->authparam;
 
