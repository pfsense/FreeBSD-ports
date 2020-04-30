--- ui/webui/resources/js/cr.js.orig	2019-12-12 12:41:35 UTC
+++ ui/webui/resources/js/cr.js
@@ -436,6 +436,11 @@ var cr = cr || function(global) {
     get doc() {
       return document;
     },
+    
+    /** Whether this is on *BSD. */
+    get isBSD() {
+      return /BSD/.test(navigator.userAgent);
+    },
 
     /** Whether we are using a Mac or not. */
     get isMac() {
