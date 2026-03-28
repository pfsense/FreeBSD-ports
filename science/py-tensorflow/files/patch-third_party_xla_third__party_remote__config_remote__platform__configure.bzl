--- third_party/xla/third_party/remote_config/remote_platform_configure.bzl.orig
+++ third_party/xla/third_party/remote_config/remote_platform_configure.bzl
@@ -7,6 +7,8 @@ def _remote_platform_configure_impl(repository_ctx):
         if os.startswith("windows"):
             platform = "windows"
         elif os.startswith("mac os"):
             platform = "osx"
+        elif os.startswith("freebsd"):
+            platform = "freebsd"
         else:
             platform = "linux"
