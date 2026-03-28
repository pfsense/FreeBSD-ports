--- third_party/systemlibs/pybind11.BUILD.orig
+++ third_party/systemlibs/pybind11.BUILD
@@ -2,7 +2,12 @@
 
 cc_library(
     name = "pybind11",
-    deps = [
-        "@xla@xla//third_party/python_runtime:headers",
-    ],
 )
+
+config_setting(
+    name = "msvc_compiler",
+    flag_values = {
+        "@bazel_tools//tools/cpp:compiler": "msvc-cl",
+    },
+    visibility = ["//visibility:public"],
+)
