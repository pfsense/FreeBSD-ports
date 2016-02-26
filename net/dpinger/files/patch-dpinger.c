--- dpinger.c.orig	2016-02-26 04:09:30 UTC
+++ dpinger.c
@@ -40,6 +40,9 @@
 
 #include <netdb.h>
 #include <net/if.h>
+#include <net/if_var.h>
+#include <ifaddrs.h>
+#include <sys/ioctl.h>
 #include <sys/socket.h>
 #include <sys/un.h>
 #include <sys/stat.h>
@@ -47,6 +50,7 @@
 #include <netinet/ip.h>
 #include <netinet/ip_icmp.h>
 #include <netinet/icmp6.h>
+#include <netinet/in_var.h>
 #include <arpa/inet.h>
 
 #include <pthread.h>
@@ -880,6 +884,70 @@ fatal(
 
 
 //
+// Wait while interface address is in 'tentative' state
+//
+static int
+in_tentative()
+{
+    struct ifaddrs *            ifa;
+    struct ifaddrs *            ifa_list;
+    int                         s, llflag, result = 0;
+    struct in6_ifreq            ifr6;
+    struct sockaddr_in6 *       sin6;
+
+    if (getifaddrs(&ifa_list) == -1)
+    {
+        perror("getifaddrs");
+        fatal("Error getting network interfaces list");
+    }
+    ifa = ifa_list;
+
+    while (ifa)
+    {
+        if (ifa->ifa_addr && ifa->ifa_addr->sa_family == AF_INET6)
+        {
+            struct sockaddr *p = (struct sockaddr *)(void *)ifa->ifa_addr;
+            if (memcmp(p, &bind_addr, sizeof(struct in6_addr)) == 0)
+            {
+                break;
+            }
+        }
+        ifa = ifa->ifa_next;
+    }
+
+    if (ifa != NULL)
+    {
+        if ((s = socket(PF_INET6, SOCK_DGRAM, 0)) < 0)
+        {
+            freeifaddrs(ifa_list);
+            perror("socket");
+            fatal("Error connecting to socket (ioctl)");
+        }
+
+        sin6 = (struct sockaddr_in6 *)(void *)ifa->ifa_addr;
+        memset(&ifr6, 0, sizeof(ifr6));
+        strncpy(ifr6.ifr_name, ifa->ifa_name, sizeof(ifr6.ifr_name));
+        memcpy(&ifr6.ifr_ifru.ifru_addr, sin6, sin6->sin6_len);
+        if (ioctl(s, SIOCGIFAFLAG_IN6, &ifr6) < 0) {
+            freeifaddrs(ifa_list);
+            perror("ioctl");
+            fatal("Error reading interface flags");
+        }
+        llflag = ifr6.ifr_ifru.ifru_flags6;
+        if ((llflag & IN6_IFF_TENTATIVE) != 0)
+        {
+            result = 1;
+        }
+        close(s);
+    }
+
+    freeifaddrs(ifa_list);
+
+    return (result);
+}
+
+
+//
 // Parse command line arguments
 //
 static void
@@ -1133,6 +1201,7 @@ main(
     int                         buflen = PACKET_BUFLEN;
     ssize_t                     rs;
     int                         r;
+    int                         ntries;
 
     // Handle command line args
     parse_args(argc, argv);
@@ -1159,6 +1228,16 @@ main(
     // Bind our sockets to an address if requested
     if (bind_addr_len)
     {
+        if (af_family == AF_INET6)
+        {
+            ntries = 0;
+            // after 10s leave, bind() will fail
+            while (in_tentative() && ntries < 10)
+            {
+                sleep(1);
+                ntries++;
+            }
+        }
         r = bind(send_sock, (struct sockaddr *) &bind_addr, bind_addr_len);
         if (r == -1)
         {
