--- chrome/app/chrome_command_ids.h.orig	2022-03-28 18:11:04 UTC
+++ chrome/app/chrome_command_ids.h
@@ -66,7 +66,7 @@
 #define IDC_NAME_WINDOW                 34049
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch of lacros-chrome is complete.
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD)
 #define IDC_USE_SYSTEM_TITLE_BAR        34051
 #define IDC_RESTORE_WINDOW              34052
 #endif
