--- chrome/browser/ui/webui/about/about_ui.h.orig	2025-06-30 07:04:30 UTC
+++ chrome/browser/ui/webui/about/about_ui.h
@@ -43,7 +43,7 @@ class TermsUIConfig : public AboutUIConfigBase {
 };
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_OPENBSD)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // chrome://linux-proxy-config
 class LinuxProxyConfigUI : public AboutUIConfigBase {
  public:
