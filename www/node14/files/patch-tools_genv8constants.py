--- tools/genv8constants.py.orig	2020-08-20 20:43:20 UTC
+++ tools/genv8constants.py
@@ -20,7 +20,7 @@ if len(sys.argv) != 3:
 outfile = open(sys.argv[1], 'w')
 try:
   pipe = subprocess.Popen([ 'objdump', '-z', '-D', sys.argv[2] ],
-      bufsize=-1, stdout=subprocess.PIPE).stdout
+      bufsize=-1, stdout=subprocess.PIPE, universal_newlines=True).stdout
 except OSError as e:
   if e.errno == errno.ENOENT:
     print('''
