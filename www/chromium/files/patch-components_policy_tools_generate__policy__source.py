--- components/policy/tools/generate_policy_source.py.orig	2022-03-25 21:59:56 UTC
+++ components/policy/tools/generate_policy_source.py
@@ -49,8 +49,10 @@ PLATFORM_STRINGS = {
     'ios': ['ios'],
     'chrome.win': ['win'],
     'chrome.linux': ['linux'],
+    'chrome.openbsd': ['openbsd'],
+    'chrome.freebsd': ['freebsd'],
     'chrome.mac': ['mac'],
-    'chrome.*': ['win', 'mac', 'linux', 'fuchsia'],
+    'chrome.*': ['win', 'mac', 'linux', 'fuchsia', 'openbsd', 'freebsd'],
     'chrome.win7': ['win'],
 }
 
