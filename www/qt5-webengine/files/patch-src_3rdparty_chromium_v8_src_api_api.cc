--- src/3rdparty/chromium/v8/src/api/api.cc.orig	2020-04-08 09:41:36 UTC
+++ src/3rdparty/chromium/v8/src/api/api.cc
@@ -5653,7 +5653,7 @@ bool v8::V8::Initialize() {
   return true;
 }
 
-#if V8_OS_LINUX || V8_OS_MACOSX
+#if V8_OS_LINUX || V8_OS_MACOSX || V8_OS_OPENBSD || V8_OS_FREEBSD
 bool TryHandleWebAssemblyTrapPosix(int sig_code, siginfo_t* info,
                                    void* context) {
 #if V8_TARGET_ARCH_X64 && !V8_OS_ANDROID
