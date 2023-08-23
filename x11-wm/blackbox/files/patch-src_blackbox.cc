--- src/blackbox.cc.orig	2005-10-18 11:33:25 UTC
+++ src/blackbox.cc
@@ -27,6 +27,8 @@
 #include "Slit.hh"
 #include "Window.hh"
 
+#include <cstdlib>
+
 #include <Pen.hh>
 #include <PixmapCache.hh>
 #include <Util.hh>
@@ -36,6 +38,7 @@
 #include <sys/stat.h>
 #include <assert.h>
 #include <signal.h>
+#include <stdlib.h>
 #include <unistd.h>
 
 // #define FOCUS_DEBUG
@@ -113,7 +116,7 @@ void Blackbox::shutdown(void) {
   XSetInputFocus(XDisplay(), PointerRoot, RevertToPointerRoot, XTime());
 
   std::for_each(screen_list, screen_list + screen_list_count,
-                std::mem_fun(&BScreen::shutdown));
+                std::mem_fn(&BScreen::shutdown));
 
   XSync(XDisplay(), false);
 
@@ -367,7 +370,7 @@ void Blackbox::timeout(bt::Timer *) {
   menuTimestamps.clear();
 
   std::for_each(screen_list, screen_list + screen_list_count,
-                std::mem_fun(&BScreen::reconfigure));
+                std::mem_fn(&BScreen::reconfigure));
 
   bt::Font::clearCache();
   bt::PixmapCache::clearCache();
@@ -422,7 +425,7 @@ Blackbox::Blackbox(char **m_argv, const char *dpy_name
   if (managed == 0) {
     fprintf(stderr, "%s: no managable screens found, exiting...\n",
             applicationName().c_str());
-    ::exit(3);
+    std::exit(3);
   }
 
   screen_list_count = managed;
@@ -620,5 +623,5 @@ void Blackbox::rereadMenu(void) {
   menuTimestamps.clear();
 
   std::for_each(screen_list, screen_list + screen_list_count,
-                std::mem_fun(&BScreen::rereadMenu));
+                std::mem_fn(&BScreen::rereadMenu));
 }
