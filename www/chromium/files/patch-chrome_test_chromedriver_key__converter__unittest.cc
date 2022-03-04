--- chrome/test/chromedriver/key_converter_unittest.cc.orig	2021-05-12 22:05:46 UTC
+++ chrome/test/chromedriver/key_converter_unittest.cc
@@ -264,7 +264,7 @@ TEST(KeyConverter, AllShorthandKeys) {
       ->Generate(&key_events);
   builder.Generate(&key_events);
   builder.SetKeyCode(ui::VKEY_TAB);
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   builder.SetText("\t", "\t")->Generate(&key_events);
 #else
   builder.SetText(std::string(), std::string());
@@ -272,7 +272,7 @@ TEST(KeyConverter, AllShorthandKeys) {
   key_events.push_back(builder.SetType(kKeyUpEventType)->Build());
 #endif
   builder.SetKeyCode(ui::VKEY_BACK);
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   builder.SetText("\b", "\b")->Generate(&key_events);
 #else
   builder.SetText(std::string(), std::string());
@@ -283,7 +283,7 @@ TEST(KeyConverter, AllShorthandKeys) {
   CheckEventsReleaseModifiers("\n\r\n\t\b ", key_events);
 }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 // Fails on bots: crbug.com/174962
 #define MAYBE_AllEnglishKeyboardSymbols DISABLED_AllEnglishKeyboardSymbols
 #else
@@ -340,7 +340,7 @@ TEST(KeyConverter, AllEnglishKeyboardTextChars) {
 TEST(KeyConverter, AllSpecialWebDriverKeysOnEnglishKeyboard) {
   ui::ScopedKeyboardLayout keyboard_layout(ui::KEYBOARD_LAYOUT_ENGLISH_US);
   const char kTextForKeys[] = {
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
       0, 0, 0, 0, '\t', 0, '\r', '\r', 0, 0, 0, 0, 0,
 #else
       0, 0, 0, 0, 0, 0, '\r', '\r', 0, 0, 0, 0, 0,
