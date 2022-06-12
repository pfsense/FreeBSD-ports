--- chrome/browser/browser_process_impl.h.orig	2022-05-11 07:16:47 UTC
+++ chrome/browser/browser_process_impl.h
@@ -373,7 +373,7 @@ class BrowserProcessImpl : public BrowserProcess,
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if defined(OS_WIN) || (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+#if defined(OS_WIN) || (defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)) || defined(OS_BSD)
   base::RepeatingTimer autoupdate_timer_;
 
   // Gets called by autoupdate timer to see if browser needs restart and can be
