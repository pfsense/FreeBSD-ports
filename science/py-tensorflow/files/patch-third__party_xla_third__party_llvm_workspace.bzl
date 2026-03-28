--- third_party/xla/third_party/llvm/workspace.bzl.orig2025-01-01 00:00:00 UTC
+++ third_party/xla/third_party/llvm/workspace.bzl
@@ -24,6 +24,7 @@ def repo(name):
             "//third_party/llvm:toolchains.patch",
             "//third_party/llvm:zstd.patch",
             "//third_party/llvm:lit_test.patch",
+            "//third_party:fix-environ.patch",
         ],
         link_files = {"//third_party/llvm:run_lit.sh": "mlir/run_lit.sh"},
     )
