--- chrome/browser/ui/webui/settings/appearance_handler.cc.orig	2022-05-11 07:16:49 UTC
+++ chrome/browser/ui/webui/settings/appearance_handler.cc
@@ -31,7 +31,7 @@ void AppearanceHandler::RegisterMessages() {
                           base::Unretained(this)));
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if defined(OS_LINUX) && !BUILDFLAG(IS_CHROMEOS_LACROS)
+#if (defined(OS_LINUX) || defined(OS_BSD)) && !BUILDFLAG(IS_CHROMEOS_LACROS)
   web_ui()->RegisterDeprecatedMessageCallback(
       "useSystemTheme",
       base::BindRepeating(&AppearanceHandler::HandleUseSystemTheme,
@@ -45,7 +45,7 @@ void AppearanceHandler::HandleUseDefaultTheme(const ba
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if defined(OS_LINUX) && !BUILDFLAG(IS_CHROMEOS_LACROS)
+#if (defined(OS_LINUX) || defined(OS_BSD)) && !BUILDFLAG(IS_CHROMEOS_LACROS)
 void AppearanceHandler::HandleUseSystemTheme(const base::ListValue* args) {
   if (profile_->IsChild())
     NOTREACHED();
