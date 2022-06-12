--- ui/webui/resources/js/cr.js.orig	2022-05-11 07:17:08 UTC
+++ ui/webui/resources/js/cr.js
@@ -441,6 +441,11 @@ var cr = cr || function(global) {
       return returnValue;
     },
 
+    /** Whether this is on *BSD. */
+    get isBSD() {
+      return /BSD/.test(navigator.userAgent);
+    },
+
     /** Whether this is on vanilla Linux (not chromeOS). */
     get isLinux() {
       return /Linux/.test(navigator.userAgent);
