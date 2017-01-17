--- dhcp6c.c.orig	2016-12-19 08:16:42 UTC
+++ dhcp6c.c
@@ -109,6 +109,7 @@ static int ctldigestlen;
 static int infreq_mode = 0;
 
 int opt_norelease;
+int opt_noscript;
 
 static inline int get_val32 __P((char **, int *, u_int32_t *));
 static inline int get_ifname __P((char **, int *, char *, int));
@@ -171,7 +172,7 @@ main(argc, argv)
 	else
 		progname++;
 
-	while ((ch = getopt(argc, argv, "c:dDfik:np:")) != -1) {
+	while ((ch = getopt(argc, argv, "c:dDfik:nxp:")) != -1) {
 		switch (ch) {
 		case 'c':
 			conffile = optarg;
@@ -194,6 +195,9 @@ main(argc, argv)
 		case 'n':
 			opt_norelease = 1;
 			break;
+		case 'x':
+			opt_noscript = 1;
+			break;
 		case 'p':
 			pid_file = optarg;
 			break;
@@ -251,7 +255,7 @@ static void
 usage()
 {
 
-	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfin] "
+	fprintf(stderr, "usage: dhcp6c [-c configfile] [-dDfinx] "
 	    "[-p pid-file] interface [interfaces...]\n");
 }
 
@@ -1751,23 +1755,23 @@ client6_recvreply(ifp, dh6, len, optinfo
 
 	switch (state) {
 	case DHCP6S_INFOREQ:
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
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Info Reply");
+ 		break;  
+ 	case DHCP6S_REQUEST:
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Reply");
+ 		break;
+ 	case DHCP6S_RENEW:
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Renew");
+ 		break;
+ 	case DHCP6S_REBIND:
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Rebind");
+ 		break;
+ 	case DHCP6S_RELEASE:
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Release");
+ 		break;
+ 	case DHCP6S_SOLICIT:
+		d_printf(LOG_INFO, FNAME, "dhcp6c Received Solicit");
+ 		break;             
 	}
 
 	/* A Reply message must contain a Server ID option */
@@ -1931,10 +1935,13 @@ client6_recvreply(ifp, dh6, len, optinfo
 	 * Call the configuration script, if specified, to handle various
 	 * configuration parameters.
 	 */
-	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0) {
-		d_printf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
-		client6_script(ifp->scriptpath, state, optinfo);
-	}
+	if (ifp->scriptpath != NULL && strlen(ifp->scriptpath) != 0 && !(opt_noscript && state == DHCP6S_REQUEST)) {
+	/* Do not call script if the no_scrip option is set and this is the response to a request. Let RTSOLD call the script */
+ 		d_printf(LOG_DEBUG, FNAME, "executes %s", ifp->scriptpath);
+ 		client6_script(ifp->scriptpath, state, optinfo);
+	} else {
+	  d_printf(LOG_DEBUG, FNAME, "Option no-script active, Script execution bypassed", ifp->scriptpath);
+ 	}
 
 	dhcp6_remove_event(ev);
 
