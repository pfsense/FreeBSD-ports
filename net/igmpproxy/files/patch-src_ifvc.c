--- src/ifvc.c.orig	2009-10-05 18:07:06 UTC
+++ src/ifvc.c
@@ -32,6 +32,11 @@
 */
 
 #include "igmpproxy.h"
+#ifdef __FreeBSD__
+#include <ifaddrs.h>
+#else
+#include <linux/sockios.h>
+#endif
 
 struct IfDesc IfDescVc[ MAX_IF ], *IfDescEp = IfDescVc;
 
@@ -41,80 +46,81 @@ struct IfDesc IfDescVc[ MAX_IF ], *IfDes
 **          
 */
 void buildIfVc() {
-    struct ifreq IfVc[ sizeof( IfDescVc ) / sizeof( IfDescVc[ 0 ] )  ];
-    struct ifreq *IfEp;
-
-    int Sock;
+    struct ifaddrs *ifap;
+    struct IfDesc *dp;
+    struct SubnetList *allowednet, *currsubnet;
 
-    if ( (Sock = socket( AF_INET, SOCK_DGRAM, 0 )) < 0 )
-        my_log( LOG_ERR, errno, "RAW socket open" );
+    my_log(LOG_DEBUG, 0, "buildIfVc: Starting...");
 
     /* get If vector
      */
-    {
-        struct ifconf IoCtlReq;
-
-        IoCtlReq.ifc_buf = (void *)IfVc;
-        IoCtlReq.ifc_len = sizeof( IfVc );
-
-        if ( ioctl( Sock, SIOCGIFCONF, &IoCtlReq ) < 0 )
-            my_log( LOG_ERR, errno, "ioctl SIOCGIFCONF" );
-
-        IfEp = (void *)((char *)IfVc + IoCtlReq.ifc_len);
+    if (getifaddrs(&ifap) < 0) {
+        my_log( LOG_ERR, errno, "buildIfVc: getifaddrs() failed" );
+        return;
     }
 
     /* loop over interfaces and copy interface info to IfDescVc
      */
     {
-        struct ifreq  *IfPt, *IfNext;
+        struct ifaddrs *ifa;
 
         // Temp keepers of interface params...
         uint32_t addr, subnet, mask;
 
-        for ( IfPt = IfVc; IfPt < IfEp; IfPt = IfNext ) {
-            struct ifreq IfReq;
+        for (ifa = ifap; ifa; ifa = ifa->ifa_next) {
             char FmtBu[ 32 ];
 
-	    IfNext = (struct ifreq *)((char *)&IfPt->ifr_addr +
-#ifdef HAVE_STRUCT_SOCKADDR_SA_LEN
-				    IfPt->ifr_addr.sa_len
-#else
-				    sizeof(struct sockaddr_in)
-#endif
-		    );
-	    if (IfNext < IfPt + 1)
-		    IfNext = IfPt + 1;
+            /* don't retrieve any further info if MAX_IF is reached
+             */
+            if ( IfDescEp >= &IfDescVc[ MAX_IF ] ) {
+                my_log(LOG_WARNING, 0, "buildIfVc: Too many interfaces, skipping all since %s", ifa->ifa_name);
+                break;
+            }
 
-            strncpy( IfDescEp->Name, IfPt->ifr_name, sizeof( IfDescEp->Name ) );
+            /* don't retrieve any info from invalid interfaces
+             */
+            if ( ifa->ifa_addr == NULL ) {
+                my_log(LOG_WARNING, 0, "buildIfVc: Interface without address, skipping %s (bug?)", ifa->ifa_name);
+                continue;
+            }
+
+            /* don't retrieve any info from non-IPv4 interfaces
+             */
+            if ( ifa->ifa_addr->sa_family != AF_INET ) {
+                my_log(LOG_DEBUG, 0, "buildIfVc: Interface is not AF_INET, skipping %s (family %d)", ifa->ifa_name, ifa->ifa_addr->sa_family);
+                continue;
+            }
+
+            strncpy( IfDescEp->Name, ifa->ifa_name, sizeof( IfDescEp->Name ) );
 
             // Currently don't set any allowed nets...
-            //IfDescEp->allowednets = NULL;
+            IfDescEp->allowednets = NULL;
 
             // Set the index to -1 by default.
             IfDescEp->index = -1;
 
-            /* don't retrieve more info for non-IP interfaces
-             */
-            if ( IfPt->ifr_addr.sa_family != AF_INET ) {
-                IfDescEp->InAdr.s_addr = 0;  /* mark as non-IP interface */
-                IfDescEp++;
-                continue;
-            }
-
             // Get the interface adress...
-            IfDescEp->InAdr = ((struct sockaddr_in *)&IfPt->ifr_addr)->sin_addr;
-            addr = IfDescEp->InAdr.s_addr;
-
-            memcpy( IfReq.ifr_name, IfDescEp->Name, sizeof( IfReq.ifr_name ) );
-            IfReq.ifr_addr.sa_family = AF_INET;
-            ((struct sockaddr_in *)&IfReq.ifr_addr)->sin_addr.s_addr = addr;
+            IfDescEp->InAdr = ((struct sockaddr_in *)ifa->ifa_addr)->sin_addr;
+            addr = ((struct sockaddr_in *)ifa->ifa_addr)->sin_addr.s_addr;
 
             // Get the subnet mask...
-            if (ioctl(Sock, SIOCGIFNETMASK, &IfReq ) < 0)
-                my_log(LOG_ERR, errno, "ioctl SIOCGIFNETMASK for %s", IfReq.ifr_name);
-            mask = ((struct sockaddr_in *)&IfReq.ifr_addr)->sin_addr.s_addr;
+            mask = ((struct sockaddr_in *)ifa->ifa_netmask)->sin_addr.s_addr;
             subnet = addr & mask;
 
+            dp = getIfByName(ifa->ifa_name, 1);
+            if (dp != NULL && dp->allowednets != NULL) {
+                allowednet = (struct SubnetList *)malloc(sizeof(struct SubnetList));
+                if (allowednet == NULL) my_log(LOG_ERR, 0, "Out of memory !");
+                allowednet->next = NULL;
+                allowednet->subnet_mask = mask;
+                allowednet->subnet_addr = subnet;
+                currsubnet = dp->allowednets;
+                while (currsubnet->next != NULL)
+                    currsubnet = currsubnet->next;
+                currsubnet->next = allowednet;
+                continue;
+            }
+
             /* get if flags
             **
             ** typical flags:
@@ -124,10 +130,7 @@ void buildIfVc() {
             ** grex  0x00C1 -> NoArp, Running, Up
             ** ipipx 0x00C1 -> NoArp, Running, Up
             */
-            if ( ioctl( Sock, SIOCGIFFLAGS, &IfReq ) < 0 )
-                my_log( LOG_ERR, errno, "ioctl SIOCGIFFLAGS" );
-
-            IfDescEp->Flags = IfReq.ifr_flags;
+            IfDescEp->Flags = ifa->ifa_flags;
 
             // Insert the verified subnet as an allowed net...
             IfDescEp->allowednets = (struct SubnetList *)malloc(sizeof(struct SubnetList));
@@ -143,7 +146,6 @@ void buildIfVc() {
             IfDescEp->robustness    = DEFAULT_ROBUSTNESS;
             IfDescEp->threshold     = DEFAULT_THRESHOLD;   /* ttl limit */
             IfDescEp->ratelimit     = DEFAULT_RATELIMIT; 
-            
 
             // Debug log the result...
             my_log( LOG_DEBUG, 0, "buildIfVc: Interface %s Addr: %s, Flags: 0x%04x, Network: %s",
@@ -156,7 +158,7 @@ void buildIfVc() {
         } 
     }
 
-    close( Sock );
+    freeifaddrs( ifap );
 }
 
 /*
@@ -166,12 +168,15 @@ void buildIfVc() {
 **          - NULL if no interface 'IfName' exists
 **          
 */
-struct IfDesc *getIfByName( const char *IfName ) {
+struct IfDesc *getIfByName( const char *IfName, int iponly ) {
     struct IfDesc *Dp;
 
-    for ( Dp = IfDescVc; Dp < IfDescEp; Dp++ )
+    for ( Dp = IfDescVc; Dp < IfDescEp; Dp++ ) {
+        if (iponly && Dp->InAdr.s_addr == 0)
+            continue;
         if ( ! strcmp( IfName, Dp->Name ) )
             return Dp;
+    }
 
     return NULL;
 }
