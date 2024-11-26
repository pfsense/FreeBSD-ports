--- tools/libs/light/libxl_utils.c.orig	2024-11-26 13:47:04 UTC
+++ tools/libs/light/libxl_utils.c
@@ -51,7 +51,7 @@ char *libxl_domid_to_name(libxl_ctx *ctx, uint32_t dom
 char *libxl_domid_to_name(libxl_ctx *ctx, uint32_t domid)
 {
     unsigned int len;
-    char path[strlen("/local/domain") + 12];
+    char path[/*strlen("/local/domain")*/ 15 + 12];
     char *s;
 
     snprintf(path, sizeof(path), "/local/domain/%d/name", domid);
@@ -147,7 +147,7 @@ char *libxl_cpupoolid_to_name(libxl_ctx *ctx, uint32_t
 char *libxl_cpupoolid_to_name(libxl_ctx *ctx, uint32_t poolid)
 {
     unsigned int len;
-    char path[strlen("/local/pool") + 12];
+    char path[/*strlen("/local/pool")*/ 15 + 12];
     char *s;
 
     snprintf(path, sizeof(path), "/local/pool/%d/name", poolid);
