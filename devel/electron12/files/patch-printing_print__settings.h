--- printing/print_settings.h.orig	2021-04-14 01:08:53 UTC
+++ printing/print_settings.h
@@ -20,11 +20,11 @@
 #include "ui/gfx/geometry/rect.h"
 #include "ui/gfx/geometry/size.h"
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 #include <map>
 
 #include "base/values.h"
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 namespace printing {
 
@@ -81,9 +81,9 @@ class PRINTING_EXPORT PrintSettings {
     }
   };
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   using AdvancedSettings = std::map<std::string, base::Value>;
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
   PrintSettings();
   PrintSettings(const PrintSettings&) = delete;
@@ -222,12 +222,12 @@ class PRINTING_EXPORT PrintSettings {
     pages_per_sheet_ = pages_per_sheet;
   }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   AdvancedSettings& advanced_settings() { return advanced_settings_; }
   const AdvancedSettings& advanced_settings() const {
     return advanced_settings_;
   }
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 #if BUILDFLAG(IS_CHROMEOS_ASH)
   void set_send_user_info(bool send_user_info) {
@@ -321,10 +321,10 @@ class PRINTING_EXPORT PrintSettings {
   // Number of pages per sheet.
   int pages_per_sheet_;
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // Advanced settings.
   AdvancedSettings advanced_settings_;
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 #if BUILDFLAG(IS_CHROMEOS_ASH)
   // Whether to send user info.
