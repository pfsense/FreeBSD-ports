--- src/3rdparty/chromium/tools/json_schema_compiler/model.py.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/tools/json_schema_compiler/model.py
@@ -676,7 +676,7 @@ class Platforms(object):
   CHROMEOS = _PlatformInfo("chromeos")
   CHROMEOS_TOUCH = _PlatformInfo("chromeos_touch")
   LACROS = _PlatformInfo("lacros")
-  LINUX = _PlatformInfo("linux")
+  LINUX = _PlatformInfo("bsd")
   MAC = _PlatformInfo("mac")
   WIN = _PlatformInfo("win")
 
