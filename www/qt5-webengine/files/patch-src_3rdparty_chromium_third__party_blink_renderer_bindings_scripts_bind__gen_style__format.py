--- src/3rdparty/chromium/third_party/blink/renderer/bindings/scripts/bind_gen/style_format.py.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/third_party/blink/renderer/bindings/scripts/bind_gen/style_format.py
@@ -29,6 +29,9 @@ def init(root_src_dir):
     elif sys.platform.startswith(("cygwin", "win")):
         platform = "win"
         exe_suffix = ".exe"
+    elif sys.platform.startswith("freebsd"):
+        platform = "freebsd"
+        exe_suffix = ""
     else:
         assert False, "Unknown platform: {}".format(sys.platform)
     buildtools_platform_dir = os.path.join(root_src_dir, "buildtools",
