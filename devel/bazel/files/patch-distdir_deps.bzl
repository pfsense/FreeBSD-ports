--- distdir_deps.bzl.orig	1980-01-01 00:00:00 UTC
+++ distdir_deps.bzl
@@ -130,6 +130,7 @@ DIST_DEPS = {
         "patch_args": ["-p1"],
         "patches": [
             "//third_party/grpc:grpc_1.41.0.patch",
+             "//third_party/grpc:extra-patch-bazel_grpc__deps.bzl",
             "//third_party/grpc:grpc_1.41.0.win_arm64.patch",
         ],
         "used_in": [
