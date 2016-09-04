--- src/config.c.orig
+++ src/config.c
@@ -350,7 +350,7 @@
     }
 
     tmpSubnet = (struct SubnetList*) malloc(sizeof(struct SubnetList));
-    tmpSubnet->subnet_addr = addr;
+    tmpSubnet->subnet_addr = (addr & mask);
     tmpSubnet->subnet_mask = ntohl(mask);
     tmpSubnet->next = NULL;
 
