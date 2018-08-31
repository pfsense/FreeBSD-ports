--- src/blocker/blocklist.c.orig	2018-06-21 19:46:24 UTC
+++ src/blocker/blocklist.c
@@ -29,14 +29,16 @@ unsigned int fw_block_subnet_size(int inet_family) {
 static void fw_block(const attack_t *attack) {
     unsigned int subnet_size = fw_block_subnet_size(attack->address.kind);
 
-    printf("block %s %d %u\n", attack->address.value, attack->address.kind, subnet_size);
+    printf("block %s %d %u %d\n", attack->address.value, attack->address.kind,
+        subnet_size, attack->service);
     fflush(stdout);
 }
 
 static void fw_release(const attack_t *attack) {
     unsigned int subnet_size = fw_block_subnet_size(attack->address.kind);
 
-    printf("release %s %d %u\n", attack->address.value, attack->address.kind, subnet_size);
+    printf("release %s %d %u %d\n", attack->address.value, attack->address.kind,
+        subnet_size, attack->service);
     fflush(stdout);
 }
 
