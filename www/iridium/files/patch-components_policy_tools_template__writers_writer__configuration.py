--- components/policy/tools/template_writers/writer_configuration.py.orig	2023-07-24 14:27:53 UTC
+++ components/policy/tools/template_writers/writer_configuration.py
@@ -59,7 +59,7 @@ def GetConfigurationForBuild(defines):
             },
         },
         'admx_prefix': 'chromium',
-        'linux_policy_path': '/etc/iridium-browser/policies/',
+        'linux_policy_path': '/etc/iridium/policies/',
         'bundle_id': 'org.chromium',
     }
   elif '_google_chrome' in defines:
