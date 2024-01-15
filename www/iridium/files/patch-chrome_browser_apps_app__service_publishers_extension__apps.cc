--- chrome/browser/apps/app_service/publishers/extension_apps.cc.orig	2023-07-24 14:27:53 UTC
+++ chrome/browser/apps/app_service/publishers/extension_apps.cc
@@ -25,7 +25,7 @@ ExtensionApps::~ExtensionApps() = default;
 
 bool ExtensionApps::Accepts(const extensions::Extension* extension) {
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
   if (extensions::IsExtensionUnsupportedDeprecatedApp(profile(),
                                                       extension->id())) {
     return false;
