--- chrome/browser/profiles/profile_impl.cc.orig	2023-07-24 14:27:53 UTC
+++ chrome/browser/profiles/profile_impl.cc
@@ -262,6 +262,10 @@
 #include "chrome/browser/spellchecker/spellcheck_service.h"
 #endif
 
+#if BUILDFLAG(IS_BSD)
+#include "sandbox/policy/sandbox.h"
+#endif
+
 using bookmarks::BookmarkModel;
 using content::BrowserThread;
 using content::DownloadManagerDelegate;
@@ -606,7 +610,7 @@ void ProfileImpl::LoadPrefsForNormalStartup(bool async
 #else
   {
 #endif  // BUILDFLAG(IS_CHROMEOS_LACROS)
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     ProfileManager* profile_manager = g_browser_process->profile_manager();
     ProfileAttributesEntry* entry =
         profile_manager->GetProfileAttributesStorage()
@@ -881,7 +885,17 @@ void ProfileImpl::DoFinalInit(CreateMode create_mode) 
 }
 
 base::FilePath ProfileImpl::last_selected_directory() {
+#if BUILDFLAG(IS_OPENBSD)
+  // If unveil(2) is used, force the file dialog directory to something we
+  // know is available.
+  auto* sandbox = sandbox::policy::SandboxLinux::GetInstance();
+  if (sandbox->unveil_initialized())
+    return GetPrefs()->GetFilePath(prefs::kDownloadDefaultDirectory);
+  else
+    return GetPrefs()->GetFilePath(prefs::kSelectFileLastDirectory);
+#else
   return GetPrefs()->GetFilePath(prefs::kSelectFileLastDirectory);
+#endif
 }
 
 void ProfileImpl::set_last_selected_directory(const base::FilePath& path) {
