--- build/config/linux/pkg-config.py.orig	2025-05-07 06:48:23 UTC
+++ build/config/linux/pkg-config.py
@@ -108,7 +108,7 @@ def main():
   # If this is run on non-Linux platforms, just return nothing and indicate
   # success. This allows us to "kind of emulate" a Linux build from other
   # platforms.
-  if "linux" not in sys.platform:
+  if not sys.platform.startswith(tuple(['linux', 'openbsd', 'freebsd'])):
     print("[[],[],[],[],[]]")
     return 0
 
