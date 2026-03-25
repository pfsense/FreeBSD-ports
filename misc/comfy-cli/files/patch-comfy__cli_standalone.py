--- comfy_cli/standalone.py.orig	2026-03-22 01:45:24 UTC
+++ comfy_cli/standalone.py
@@ -68,7 +68,12 @@ def download_standalone_python(
     """grab a pre-built distro from the python-build-standalone project. See
     https://gregoryszorc.com/docs/python-build-standalone/main/"""
     platform = get_os() if platform is None else platform
     proc = get_proc() if proc is None else proc
-    target = _platform_targets[(platform, proc)]
+    if (platform, proc) not in _platform_targets:
+        raise NotImplementedError(
+            f"Standalone Python download is not supported on {platform.value}/{proc.value}. "
+            "The python-build-standalone project does not provide builds for this platform."
+        )
+    target = _platform_targets[(platform, proc)]
 
     if tag == "latest":
         # try to fetch json with info about latest release
