--- third_party/dawn/src/common/Platform.h.orig	2022-05-11 07:17:27 UTC
+++ third_party/dawn/src/common/Platform.h
@@ -26,6 +26,10 @@
 #        error "Unsupported Windows platform."
 #    endif
 
+#elif defined(__OpenBSD__) || defined(__FreeBSD__)
+#    define DAWN_PLATFORM_BSD 1
+#    define DAWN_PLATFORM_LINUX 1
+#    define DAWN_PLATFORM_POSIX 1
 #elif defined(__linux__)
 #    define DAWN_PLATFORM_LINUX 1
 #    define DAWN_PLATFORM_POSIX 1
