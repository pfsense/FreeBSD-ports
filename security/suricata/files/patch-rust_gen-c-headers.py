--- rust/gen-c-headers.py.orig	2019-03-06 15:57:47 UTC
+++ rust/gen-c-headers.py
@@ -169,7 +169,7 @@ def gen_headers(filename):
     if not should_regen(filename, output_filename):
         return
 
-    buf = open(filename).read()
+    buf = open(filename, encoding="utf-8").read()
     writer = StringIO()
 
     for fn in re.findall(
