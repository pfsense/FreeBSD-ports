--- src/3rdparty/chromium/ui/base/ime/init/input_method_initializer.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/base/ime/init/input_method_initializer.cc
@@ -10,7 +10,7 @@
 
 #if defined(OS_CHROMEOS)
 #include "ui/base/ime/chromeos/ime_bridge.h"
-#elif defined(USE_AURA) && defined(OS_LINUX)
+#elif defined(USE_AURA) && (defined(OS_LINUX) || defined(OS_BSD))
 #include "base/check.h"
 #include "ui/base/ime/linux/fake_input_method_context_factory.h"
 #elif defined(OS_WIN)
@@ -20,7 +20,7 @@ namespace {
 
 namespace {
 
-#if !defined(OS_CHROMEOS) && defined(USE_AURA) && defined(OS_LINUX)
+#if !defined(OS_CHROMEOS) && defined(USE_AURA) && (defined(OS_LINUX) || defined(OS_BSD))
 const ui::LinuxInputMethodContextFactory*
     g_linux_input_method_context_factory_for_testing;
 #endif
@@ -48,7 +48,7 @@ void InitializeInputMethodForTesting() {
 void InitializeInputMethodForTesting() {
 #if defined(OS_CHROMEOS)
   IMEBridge::Initialize();
-#elif defined(USE_AURA) && defined(OS_LINUX)
+#elif defined(USE_AURA) && (defined(OS_LINUX) || defined(OS_BSD))
   if (!g_linux_input_method_context_factory_for_testing)
     g_linux_input_method_context_factory_for_testing =
         new FakeInputMethodContextFactory();
@@ -67,7 +67,7 @@ void ShutdownInputMethodForTesting() {
 void ShutdownInputMethodForTesting() {
 #if defined(OS_CHROMEOS)
   IMEBridge::Shutdown();
-#elif defined(USE_AURA) && defined(OS_LINUX)
+#elif defined(USE_AURA) && (defined(OS_LINUX) || defined(OS_BSD))
   const LinuxInputMethodContextFactory* factory =
       LinuxInputMethodContextFactory::instance();
   CHECK(!factory || factory == g_linux_input_method_context_factory_for_testing)
