--- libs/JUCE/modules/juce_core/juce_core.cpp.orig	2022-04-12 19:06:54 UTC
+++ libs/JUCE/modules/juce_core/juce_core.cpp
@@ -42,7 +42,7 @@
 #include <thread>
 
 #if ! JUCE_ANDROID
- #include <sys/timeb.h>
+// #include <sys/timeb.h>
  #include <cwctype>
 #endif
 
