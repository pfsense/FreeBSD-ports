--- src/ifvc.c.orig	2016-02-28 22:06:37 UTC
+++ src/ifvc.c
@@ -43,6 +43,8 @@ struct IfDesc IfDescVc[ MAX_IF ], *IfDes
 void buildIfVc() {
     struct ifreq IfVc[ sizeof( IfDescVc ) / sizeof( IfDescVc[ 0 ] )  ];
     struct ifreq *IfEp;
+    struct IfDesc *dp;
+    struct SubnetList *allowednet, *currsubnet;
 
     int Sock;
 
@@ -115,6 +117,20 @@ void buildIfVc() {
             mask = ((struct sockaddr_in *)&IfReq.ifr_addr)->sin_addr.s_addr;
             subnet = addr & mask;
 
+            dp = getIfByName(IfPt->ifr_name, 1);
+            if (dp != NULL && dp->allowednets != NULL) {
+                allowednet = (struct SubnetList *)malloc(sizeof(struct SubnetList));
+                if (allowednet == NULL) my_log(LOG_ERR, 0, "Out of memory !");
+		allowednet->next = NULL;
+		allowednet->subnet_mask = mask;
+		allowednet->subnet_addr = subnet;
+                currsubnet = dp->allowednets;
+                while (currsubnet->next != NULL)
+                    currsubnet = currsubnet->next;
+		currsubnet->next = allowednet;
+                continue;
+            }
+
             /* get if flags
             **
             ** typical flags:
@@ -166,12 +182,15 @@ void buildIfVc() {
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
