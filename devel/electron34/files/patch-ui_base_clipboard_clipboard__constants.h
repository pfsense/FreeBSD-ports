--- ui/base/clipboard/clipboard_constants.h.orig	2025-01-27 17:37:37 UTC
+++ ui/base/clipboard/clipboard_constants.h
@@ -41,7 +41,7 @@ extern const char kMimeTypeWindowDrag[];
 
 // ----- LINUX & CHROMEOS & FUCHSIA MIME TYPES -----
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_FUCHSIA)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
 COMPONENT_EXPORT(UI_BASE_CLIPBOARD_TYPES)
 extern const char kMimeTypeLinuxUtf8String[];
 COMPONENT_EXPORT(UI_BASE_CLIPBOARD_TYPES)
