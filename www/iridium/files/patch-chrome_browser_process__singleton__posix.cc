--- chrome/browser/process_singleton_posix.cc.orig	2022-04-01 07:48:30 UTC
+++ chrome/browser/process_singleton_posix.cc
@@ -98,12 +98,12 @@
 #include "net/base/network_interfaces.h"
 #include "ui/base/l10n/l10n_util.h"
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/ui/process_singleton_dialog_linux.h"
 #endif
 
 #if defined(TOOLKIT_VIEWS) && \
-    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD))
 #include "ui/views/linux_ui/linux_ui.h"
 #endif
 
@@ -357,7 +357,7 @@ bool DisplayProfileInUseError(const base::FilePath& lo
   if (g_disable_prompt)
     return g_user_opted_unlock_in_use_profile;
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   std::u16string relaunch_button_text =
       l10n_util::GetStringUTF16(IDS_PROFILE_IN_USE_LINUX_RELAUNCH);
   return ShowProcessSingletonDialog(error, relaunch_button_text);
@@ -926,7 +926,7 @@ ProcessSingleton::NotifyResult ProcessSingleton::Notif
     return PROCESS_NONE;
   } else if (strncmp(buf, kACKToken, base::size(kACKToken) - 1) == 0) {
 #if defined(TOOLKIT_VIEWS) && \
-    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+    (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD))
     // Likely NULL in unit tests.
     views::LinuxUI* linux_ui = views::LinuxUI::instance();
     if (linux_ui)
