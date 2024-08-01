--- src/3rdparty/chromium/ui/gfx/x/xlib_support.cc.orig	2023-12-12 22:08:45 UTC
+++ src/3rdparty/chromium/ui/gfx/x/xlib_support.cc
@@ -42,10 +42,18 @@ void InitXlib() {
   if (xlib_loader->loaded())
     return;
 
+#if BUILDFLAG(IS_BSD)
+  CHECK(xlib_loader->Load("libX11.so"));
+#else
   CHECK(xlib_loader->Load("libX11.so.6"));
+#endif
 
   auto* xlib_xcb_loader = GetXlibXcbLoader();
+#if BUILDFLAG(IS_BSD)
+  CHECK(xlib_xcb_loader->Load("libX11-xcb.so"));
+#else
   CHECK(xlib_xcb_loader->Load("libX11-xcb.so.1"));
+#endif
 
   CHECK(xlib_loader->XInitThreads());
 
