--- ui/color/color_provider_utils.cc.orig	2025-05-31 17:16:41 UTC
+++ ui/color/color_provider_utils.cc
@@ -194,7 +194,7 @@ std::string_view SystemThemeName(ui::SystemTheme syste
   switch (system_theme) {
     case ui::SystemTheme::kDefault:
       return "kDefault";
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     case ui::SystemTheme::kGtk:
       return "kGtk";
     case ui::SystemTheme::kQt:
