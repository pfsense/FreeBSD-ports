--- third_party/xla/third_party/py/python_init_rules.bzl.orig
+++ third_party/xla/third_party/py/python_init_rules.bzl
@@ -41,5 +41,6 @@
             "@xla//third_party/py:rules_python_pip_version.patch",
             "@xla//third_party/py:rules_python_freethreaded.patch",
             "@xla//third_party/py:rules_python_versions.patch",
+            "@xla//third_party/py:rules_python_freebsd.patch",
         ] + extra_patches,
     )
