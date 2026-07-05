--- Onboard/osk/osk_util.c.orig	2025-07-03 16:13:44 UTC
+++ Onboard/osk/osk_util.c
@@ -37,7 +37,7 @@ typedef struct {
 
     GdkDisplay *display;
     Atom atom_net_active_window;
-    PyObject* signal_callbacks[_NSIG];
+    PyObject* signal_callbacks[NSIG];
     PyObject* onboard_toplevels;
 
     Atom* watched_root_properties;
