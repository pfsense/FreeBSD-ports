--- tensorflow/workspace3.bzl.orig	2026-03-22 22:50:53.707183000 -0700
+++ tensorflow/workspace3.bzl	2026-03-22 22:50:32.286898000 -0700
@@ -51,7 +51,16 @@
             "https://github.com/bazel-contrib/bazel_features/releases/download/v1.25.0/bazel_features-v1.25.0.tar.gz",
         ),
     )
+    tf_http_archive(
+        name = "proto_bazel_features",
+        sha256 = "4fd9922d464686820ffd8fcefa28ccffa147f7cdc6b6ac0d8b07fde565c65d66",
+        strip_prefix = "bazel_features-1.25.0",
+        urls = tf_mirror_urls(
+            "https://github.com/bazel-contrib/bazel_features/releases/download/v1.25.0/bazel_features-v1.25.0.tar.gz",
+        ),
+    )
 
+
     # Maven dependencies.
     RULES_JVM_EXTERNAL_TAG = "4.3"
     tf_http_archive(
