--- tensorflow/workspace1.bzl.orig
+++ tensorflow/workspace1.bzl
@@ -1,6 +1,5 @@
 """TensorFlow workspace initialization. Consult the WORKSPACE on how to use it."""
 
-load("@com_github_grpc_grpc//bazel:grpc_deps.bzl", "grpc_deps")
 load("@com_google_benchmark//:bazel/benchmark_deps.bzl", "benchmark_deps")
 load("@io_bazel_rules_closure//closure:defs.bzl", "closure_repositories")
 load("@rules_pkg//:deps.bzl", "rules_pkg_dependencies")
@@ -33,7 +32,6 @@ def workspace(with_rules_cc = True):
 
     android_configure(name = "local_config_android")
 
-    grpc_deps()
     benchmark_deps()
 
 # Alias so it can be loaded without assigning to a different symbol to prevent
