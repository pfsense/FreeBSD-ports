--- benchmarks/matmul/matmul-seq.c.orig	2026-03-24 09:36:43 UTC
+++ benchmarks/matmul/matmul-seq.c
@@ -107,7 +107,7 @@ int main(int argc, char *argv[])
 
 int main(int argc, char *argv[])
 {
-    int c;
+    signed int c;
     while ((c=getopt(argc, argv, "w:q:h")) != -1) {
         switch (c) {
             case 'h':
