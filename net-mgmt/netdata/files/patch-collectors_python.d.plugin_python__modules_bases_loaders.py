--- collectors/python.d.plugin/python_modules/bases/loaders.py.orig	2020-02-21 01:50:30 UTC
+++ collectors/python.d.plugin/python_modules/bases/loaders.py
@@ -10,9 +10,9 @@ PY_VERSION = version_info[:2]
 
 try:
     if PY_VERSION > (3, 1):
-        from pyyaml3 import SafeLoader as YamlSafeLoader
+        from yaml import SafeLoader as YamlSafeLoader
     else:
-        from pyyaml2 import SafeLoader as YamlSafeLoader
+        from yaml import SafeLoader as YamlSafeLoader
 except ImportError:
     from yaml import SafeLoader as YamlSafeLoader
 
