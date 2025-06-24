--- ui/base/accelerators/accelerator.cc.orig	2025-06-19 07:37:57 UTC
+++ ui/base/accelerators/accelerator.cc
@@ -331,7 +331,7 @@ std::vector<std::u16string> Accelerator::GetLongFormMo
     modifiers.push_back(l10n_util::GetStringUTF16(IDS_APP_SEARCH_KEY));
 #elif BUILDFLAG(IS_WIN)
     modifiers.push_back(l10n_util::GetStringUTF16(IDS_APP_WINDOWS_KEY));
-#elif BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     modifiers.push_back(l10n_util::GetStringUTF16(IDS_APP_SUPER_KEY));
 #else
     NOTREACHED();
