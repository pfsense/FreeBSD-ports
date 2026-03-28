--- tensorflow/workspace2.bzl.orig
+++ tensorflow/workspace2.bzl
@@ -178,6 +178,7 @@
         name = "XNNPACK",
         sha256 = "44bf8a258cfd0d7b500b6058a2bb5c7387c8cebba295cfca985a68d16513f7c8",
         strip_prefix = "XNNPACK-25b42dfddb0ee22170d73ff0d4b333ea1e6edfeb",
+        patch_file = ["//third_party:fix-xnnpack.patch", "//third_party:xnnpack-posix-c-source.patch", "//third_party:xnnpack-freebsd-x86.patch"],
         urls = tf_mirror_urls("https://github.com/google/XNNPACK/archive/25b42dfddb0ee22170d73ff0d4b333ea1e6edfeb.zip"),
     )
     # LINT.ThenChange(//tensorflow/lite/tools/cmake/modules/xnnpack.cmake)
@@ -200,7 +201,8 @@
     # LINT.IfChange(pthreadpool)
     tf_http_archive(
         name = "pthreadpool",
         sha256 = "f602ab141bdc5d5872a79d6551e9063b5bfa7ad6ad60cceaa641de5c45c86d70",
         strip_prefix = "pthreadpool-0e6ca13779b57d397a5ba6bfdcaa8a275bc8ea2e",
+        patch_file = ["//third_party:pthreadpool-alloca.patch"],
         urls = tf_mirror_urls("https://github.com/google/pthreadpool/archive/0e6ca13779b57d397a5ba6bfdcaa8a275bc8ea2e.zip"),
     )
@@ -407,7 +409,10 @@
     maybe(
         tf_http_archive,
         name = "com_google_protobuf",
-        patch_file = ["@xla//third_party/protobuf:protobuf.patch"],
+        patch_file = [
+            "@xla//third_party/protobuf:protobuf.patch",
+            "//third_party:fix-protobuf-java.patch",
+        ],
         sha256 = "6e09bbc950ba60c3a7b30280210cd285af8d7d8ed5e0a6ed101c72aff22e8d88",
         strip_prefix = "protobuf-6.31.1",
         urls = tf_mirror_urls("https://github.com/protocolbuffers/protobuf/archive/refs/tags/v6.31.1.zip"),
@@ -471,6 +476,15 @@
         sha256 = "dd6a2fa311ba8441bbefd2764c55b99136ff10f7ea42954be96006a2723d33fc",
         strip_prefix = "grpc-1.74.0",
         system_build_file = "//third_party/systemlibs:grpc.BUILD",
+        system_link_files = {
+            "//third_party/systemlibs:grpc.bazel.BUILD": "bazel/BUILD.bazel",
+            "//third_party/systemlibs:grpc.bazel.cc_grpc_library.bzl": "bazel/cc_grpc_library.bzl",
+            "//third_party/systemlibs:grpc.bazel.generate_cc.bzl": "bazel/generate_cc.bzl",
+            "//third_party/systemlibs:grpc.bazel.grpc_deps.bzl": "bazel/grpc_deps.bzl",
+            "//third_party/systemlibs:grpc.bazel.grpc_extra_deps.bzl": "bazel/grpc_extra_deps.bzl",
+            "//third_party/systemlibs:grpc.bazel.protobuf.bzl": "bazel/protobuf.bzl",
+            "//third_party/systemlibs:grpc.bazel.python_rules.bzl": "bazel/python_rules.bzl",
+        },
         patch_file = [
             "@xla//third_party/grpc:grpc.patch",
         ],
@@ -895,6 +909,7 @@
         name = "riegeli",
         sha256 = "590ec559107fc7082e1a7d70e9c9bfb8624c79dabca0a05fe1bcba1d7a591ec8",
         strip_prefix = "riegeli-a37c3dbdd5d2a15113d363c7a7c41c30453e482f",
+        patch_file = ["//third_party:riegeli-xopen-source.patch"],
         urls = tf_mirror_urls("https://github.com/google/riegeli/archive/a37c3dbdd5d2a15113d363c7a7c41c30453e482f.zip"),
     )
 
