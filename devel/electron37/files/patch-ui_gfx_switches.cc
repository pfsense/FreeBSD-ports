--- ui/gfx/switches.cc.orig	2025-06-30 07:04:30 UTC
+++ ui/gfx/switches.cc
@@ -36,7 +36,7 @@ const char kScreenInfo[] = "screen-info";
 // See //components/headless/screen_info/README.md for more details.
 const char kScreenInfo[] = "screen-info";
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // Which X11 display to connect to. Emulates the GTK+ "--display=" command line
 // argument. In use only with Ozone/X11.
 const char kX11Display[] = "display";
