--- src/get_myeaddr.c.orig	2021-01-18 19:16:15 UTC
+++ src/get_myeaddr.c
@@ -55,7 +55,7 @@
 
 
 int
-get_myeaddr(int sockfd, struct in_addr *my_ipaddr, struct ether_addr *my_eaddr, const char *ifname)
+get_myeaddr(int sockfd, struct in_addr *my_ipaddr, struct libnet_ether_addr *my_eaddr, const char *ifname)
 {
 /* If SIOCGIFHWADDR is defined, 
      We use the SIOCGIFHWADDR ioctl to do our work as follows:
@@ -92,7 +92,7 @@ get_myeaddr(int sockfd, struct in_addr *my_ipaddr, str
 		return(-1);
 	}
 
-	bcopy(ifr.ifr_hwaddr.sa_data, my_eaddr, sizeof (struct ether_addr));
+	bcopy(ifr.ifr_hwaddr.sa_data, my_eaddr, sizeof (struct libnet_ether_addr));
 
 #elif defined SIOCGARP /* not SIOCGIFHWADDR */
 
@@ -154,7 +154,7 @@ get_myeaddr(int sockfd, struct in_addr *my_ipaddr, str
 		return(-1);
 	}
 
-	bcopy(arpreq.arp_ha.sa_data, my_eaddr, sizeof (struct ether_addr));
+	bcopy(arpreq.arp_ha.sa_data, my_eaddr, sizeof (struct libnet_ether_addr));
 
 #elif defined HAVE_GETIFADDRS /* not SIOCGARP */
 
@@ -180,7 +180,7 @@ get_myeaddr(int sockfd, struct in_addr *my_ipaddr, str
 				found = 1;
 				/* copy the result to my_eaddr */
 				sdl = (struct sockaddr_dl *) ifp->ifa_addr;
-				bcopy((const void *)LLADDR(sdl), my_eaddr, sizeof (struct ether_addr));
+				bcopy((const void *)LLADDR(sdl), my_eaddr, sizeof (struct libnet_ether_addr));
 
 			}
 		}
