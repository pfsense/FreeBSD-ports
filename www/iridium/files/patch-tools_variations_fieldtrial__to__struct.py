--- tools/variations/fieldtrial_to_struct.py.orig	2026-03-24 16:59:08 UTC
+++ tools/variations/fieldtrial_to_struct.py
@@ -33,6 +33,8 @@ _platforms = [
     'linux',
     'mac',
     'windows',
+    'openbsd',
+    'freebsd',
 ]
 
 _form_factors = [
