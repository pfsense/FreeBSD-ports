--- plugins/builtin/include/content/views/view_pattern_editor.hpp.orig	2023-04-04 10:04:22 UTC
+++ plugins/builtin/include/content/views/view_pattern_editor.hpp
@@ -16,6 +16,7 @@
 #include <thread>
 #include <vector>
 #include <functional>
+#include <hpx/functional.hpp>
 
 #include <TextEditor.h>
 
@@ -65,7 +66,7 @@ namespace hex::plugin::builtin {
         bool m_syncPatternSourceCode = false;
         bool m_autoLoadPatterns = true;
 
-        std::map<prv::Provider*, std::move_only_function<void()>> m_sectionWindowDrawer;
+        std::map<prv::Provider*, hpx::move_only_function<void()>> m_sectionWindowDrawer;
 
         ui::HexEditor m_sectionHexEditor;
 
