--- tensorflow/workspace0.bzl.orig
+++ tensorflow/workspace0.bzl
@@ -5,7 +5,6 @@
 load("@build_bazel_apple_support//lib:repositories.bzl", "apple_support_dependencies")
 load("@build_bazel_rules_apple//apple:repositories.bzl", "apple_rules_dependencies")
 load("@build_bazel_rules_swift//swift:repositories.bzl", "swift_rules_dependencies")
-load("@com_github_grpc_grpc//bazel:grpc_extra_deps.bzl", "grpc_extra_deps")
 load("@local_config_android//:android.bzl", "android_workspace")
 load("@rules_foreign_cc//foreign_cc:repositories.bzl", "rules_foreign_cc_dependencies")
 load("//third_party:repo.bzl", "tf_http_archive", "tf_mirror_urls")
@@ -100,7 +99,6 @@
     # at the end of the WORKSPACE file.
     _tf_bind()
 
-    grpc_extra_deps()
     rules_foreign_cc_dependencies()
     config_googleapis()
 
