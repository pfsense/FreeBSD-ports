--- arpwatch.c.orig	2019-02-06 23:18:28.123873500 -0500
+++ arpwatch.c	2019-02-12 10:27:47.850346300 -0500
@@ -107,6 +107,8 @@
 
 char *prog;
 
+char *Watcher = NULL;
+
 int can_checkpoint;
 int swapped;
 int nobogons;
@@ -170,7 +172,7 @@
 	interface = NULL;
 	rfilename = NULL;
 	pd = NULL;
-	while ((op = getopt(argc, argv, "df:i:n:Nr:")) != EOF)
+	while ((op = getopt(argc, argv, "dvzf:i:m:n:Nr:")) != EOF)
 		switch (op) {
 
 		case 'd':
@@ -202,6 +204,16 @@
 			rfilename = optarg;
 			break;
 
+		case 'm':
+			Watcher = optarg;
+			break;
+		case 'v':
+			vrrpflag = 1;
+			break;
+		case 'z':
+			zeroflag = 1;
+			break;
+
 		default:
 			usage();
 		}
@@ -321,7 +333,6 @@
 
 	(void)setsignal(SIGINT, die);
 	(void)setsignal(SIGTERM, die);
-	(void)setsignal(SIGHUP, die);
 	if (rfilename == NULL) {
 		(void)setsignal(SIGQUIT, checkpoint);
 		(void)setsignal(SIGALRM, checkpoint);
@@ -391,6 +402,12 @@
 		return;
 	}
 
+	/* Check for CARP-generated ARP replies and ignore them */
+	if (vrrpflag == 1 && MEMCMP(sha, vrrp_prefix, 5) == 0) {
+		/* do nothing */
+		return;
+	}
+
 	/* Double check ethernet addresses */
 	if (MEMCMP(sea, sha, 6) != 0) {
 		dosyslog(LOG_INFO, "ethernet mismatch", sia, sea, sha);
@@ -751,6 +768,6 @@
 
 	(void)fprintf(stderr, "Version %s\n", version);
 	(void)fprintf(stderr, "usage: %s [-dN] [-f datafile] [-i interface]"
-	    " [-n net[/width]] [-r file]\n", prog);
+	    " [-m email] [-n net[/width]] [-r file]\n", prog);
 	exit(1);
 }
