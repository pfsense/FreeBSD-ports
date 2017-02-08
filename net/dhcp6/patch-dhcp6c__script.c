--- dhcp6c_script.c.orig	2016-12-19 08:16:42 UTC
+++ dhcp6c_script.c
@@ -71,6 +71,7 @@ static char nispserver_str[] = "new_nisp
 static char nispname_str[] = "new_nisp_name";
 static char bcmcsserver_str[] = "new_bcmcs_servers";
 static char bcmcsname_str[] = "new_bcmcs_name";
+static char reason[32];
 
 int
 client6_script(scriptpath, state, optinfo)
@@ -84,10 +85,32 @@ client6_script(scriptpath, state, optinf
 	int nispservers, nispnamelen;
 	int bcmcsservers, bcmcsnamelen;
 	char **envp, *s;
-	char reason[] = "REASON=NBI";
 	struct dhcp6_listval *v;
 	pid_t pid, wpid;
 
+	switch(state) {
+	  case DHCP6S_INFOREQ:
+	    sprintf(reason,"REASON=INFO");
+	    break;
+	  case DHCP6S_REQUEST:
+	    sprintf(reason,"REASON=REPLY");
+	    break;
+	 case DHCP6S_RENEW:
+	    sprintf(reason,"REASON=RENEW");
+	    break;
+	case DHCP6S_REBIND:
+	    sprintf(reason,"REASON=REBIND");
+	    break;
+	case DHCP6S_RELEASE:
+	    sprintf(reason,"REASON=RELEASE");
+	    break;
+	case DHCP6S_EXIT:
+	    sprintf(reason,"REASON=EXIT");
+	    break;  
+	default:
+	    sprintf(reason,"REASON=OTHER");
+	}
+
 	/* if a script is not specified, do nothing */
 	if (scriptpath == NULL || strlen(scriptpath) == 0)
 		return -1;
@@ -107,6 +130,9 @@ client6_script(scriptpath, state, optinf
 	envc = 2;     /* we at least include the reason and the terminator */
 
 	/* count the number of variables */
+	if(state == DHCP6S_EXIT) {
+		goto skip1;
+	}
 	for (v = TAILQ_FIRST(&optinfo->dns_list); v; v = TAILQ_NEXT(v, link))
 		dnsservers++;
 	envc += dnsservers ? 1 : 0;
@@ -165,6 +191,7 @@ client6_script(scriptpath, state, optinf
 	/*
 	 * Copy the parameters as environment variables
 	 */
+skip1:
 	i = 0;
 	/* reason */
 	if ((envp[i++] = strdup(reason)) == NULL) {
@@ -173,6 +200,9 @@ client6_script(scriptpath, state, optinf
 		ret = -1;
 		goto clean;
 	}
+	if(state == DHCP6S_EXIT) {
+		goto skip2;
+	}
 	/* "var=addr1 addr2 ... addrN" + null char for termination */
 	if (dnsservers) {
 		elen = sizeof (dnsserver_str) +
@@ -379,7 +409,7 @@ client6_script(scriptpath, state, optinf
 			strlcat(s, " ", elen);
 		}
 	}
-
+skip2:
 	/* launch the script */
 	pid = fork();
 	if (pid < 0) {
