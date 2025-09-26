--- if.c.orig	2017-02-28 19:06:15 UTC
+++ if.c
@@ -45,12 +45,10 @@
 #include <ifaddrs.h>
 #include <errno.h>
 
-#include <dhcp6.h>
-#include <config.h>
-#include <common.h>
+#include "dhcp6.h"
+#include "config.h"
+#include "common.h"
 
-extern int errno;
-
 struct dhcp6_if *dhcp6_if;
 
 struct dhcp6_if *
@@ -61,7 +59,7 @@ ifinit(ifname)
 
 	if ((ifp = find_ifconfbyname(ifname)) != NULL) {
 		d_printf(LOG_NOTICE, FNAME, "duplicated interface: %s", ifname);
-		return (NULL);
+		return (ifp);
 	}
 
 	if ((ifp = malloc(sizeof(*ifp))) == NULL) {
@@ -82,6 +80,7 @@ ifinit(ifname)
 
 	TAILQ_INIT(&ifp->reqopt_list);
 	TAILQ_INIT(&ifp->iaconf_list);
+	TAILQ_INIT(&ifp->rawops);
 
 	ifp->authproto = DHCP6_AUTHPROTO_UNDEF;
 	ifp->authalgorithm = DHCP6_AUTHALG_UNDEF;
@@ -105,7 +104,7 @@ ifinit(ifname)
 			if (ifa->ifa_addr->sa_family != AF_INET6)
 				continue;
 
-			sin6 = (struct sockaddr_in6 *)ifa->ifa_addr;
+			sin6 = (struct sockaddr_in6 *)(void *)ifa->ifa_addr;
 			if (IN6_IS_ADDR_LINKLOCAL(&sin6->sin6_addr))
 				continue;
 
@@ -131,7 +130,7 @@ ifreset(ifp)
 	struct dhcp6_if *ifp;
 {
 	unsigned int ifid;
-	u_int32_t linkid;
+	uint32_t linkid;
 
 	if ((ifid = if_nametoindex(ifp->ifname)) == 0) {
 		d_printf(LOG_ERR, FNAME, "invalid interface(%s): %s",
