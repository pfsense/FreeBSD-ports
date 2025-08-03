--- dhcp6relay_script.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6relay_script.c
@@ -87,7 +87,7 @@ relay6_script(scriptpath, client, dh6, len)
 	/* only replies are interesting */
 	if (dh6->dh6_msgtype != DH6_REPLY) {
 		if (dh6->dh6_msgtype != DH6_ADVERTISE) {
-			d_printf(LOG_INFO, FNAME, "forward msg#%d to client?",
+			dprintf(LOG_INFO, FNAME, "forward msg#%d to client?",
 			    dh6->dh6_msgtype);
 			return -1;
 		}
@@ -99,7 +99,7 @@ relay6_script(scriptpath, client, dh6, len)
 	dhcp6_init_options(&optinfo);
 	if (dhcp6_get_options((struct dhcp6opt *)(dh6 + 1), optend,
 	    &optinfo) < 0) {
-		d_printf(LOG_INFO, FNAME, "failed to parse options");
+		dprintf(LOG_INFO, FNAME, "failed to parse options");
 		return -1;
 	}
 
@@ -118,7 +118,7 @@ relay6_script(scriptpath, client, dh6, len)
 
 	/* allocate an environments array */
 	if ((envp = malloc(sizeof (char *) * envc)) == NULL) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to allocate environment buffer");
 		dhcp6_clear_options(&optinfo);
 		return -1;
@@ -132,14 +132,14 @@ relay6_script(scriptpath, client, dh6, len)
 	/* address */
 	t = addr2str((struct sockaddr *) client);
 	if (t == NULL) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to get address of client");
 		ret = -1;
 		goto clean;
 	}
 	elen = sizeof (client_str) + 1 + strlen(t) + 1;
 	if ((s = envp[i++] = malloc(elen)) == NULL) {
-		d_printf(LOG_NOTICE, FNAME,
+		dprintf(LOG_NOTICE, FNAME,
 		    "failed to allocate string for client");
 		ret = -1;
 		goto clean;
@@ -167,7 +167,7 @@ relay6_script(scriptpath, client, dh6, len)
 	/* launch the script */
 	pid = fork();
 	if (pid < 0) {
-		d_printf(LOG_ERR, FNAME, "failed to fork: %s", strerror(errno));
+		dprintf(LOG_ERR, FNAME, "failed to fork: %s", strerror(errno));
 		ret = -1;
 		goto clean;
 	} else if (pid) {
@@ -178,9 +178,9 @@ relay6_script(scriptpath, client, dh6, len)
 		} while (wpid != pid && wpid > 0);
 
 		if (wpid < 0)
-			d_printf(LOG_ERR, FNAME, "wait: %s", strerror(errno));
+			dprintf(LOG_ERR, FNAME, "wait: %s", strerror(errno));
 		else {
-			d_printf(LOG_DEBUG, FNAME,
+			dprintf(LOG_DEBUG, FNAME,
 			    "script \"%s\" terminated", scriptpath);
 		}
 	} else {
@@ -191,7 +191,7 @@ relay6_script(scriptpath, client, dh6, len)
 		argv[1] = NULL;
 
 		if (safefile(scriptpath)) {
-			d_printf(LOG_ERR, FNAME,
+			dprintf(LOG_ERR, FNAME,
 			    "script \"%s\" cannot be executed safely",
 			    scriptpath);
 			exit(1);
@@ -208,7 +208,7 @@ relay6_script(scriptpath, client, dh6, len)
 
 		execve(scriptpath, argv, envp);
 
-		d_printf(LOG_ERR, FNAME, "child: exec failed: %s",
+		dprintf(LOG_ERR, FNAME, "child: exec failed: %s",
 		    strerror(errno));
 		exit(0);
 	}
@@ -254,12 +254,12 @@ iapd2str(num, iav)
 			break;
 
 		default:
-			d_printf(LOG_ERR, FNAME, "impossible subopt");
+			dprintf(LOG_ERR, FNAME, "impossible subopt");
 		}
 	}
 
 	if ((r = strdup(s)) == NULL)
-		d_printf(LOG_ERR, FNAME, "failed to allocate iapd_%d", num);
+		dprintf(LOG_ERR, FNAME, "failed to allocate iapd_%d", num);
 	return r;
 }
 
@@ -294,11 +294,11 @@ iana2str(num, iav)
 			break;
 
 		default:
-			d_printf(LOG_ERR, FNAME, "impossible subopt");
+			dprintf(LOG_ERR, FNAME, "impossible subopt");
 		}
 	}
 
 	if ((r = strdup(s)) == NULL)
-		d_printf(LOG_ERR, FNAME, "failed to allocate iana_%d", num);
+		dprintf(LOG_ERR, FNAME, "failed to allocate iana_%d", num);
 	return r;
 }
