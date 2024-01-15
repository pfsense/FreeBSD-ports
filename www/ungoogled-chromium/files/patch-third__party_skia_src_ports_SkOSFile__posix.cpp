--- third_party/skia/src/ports/SkOSFile_posix.cpp.orig	2022-10-01 07:40:07 UTC
+++ third_party/skia/src/ports/SkOSFile_posix.cpp
@@ -25,7 +25,7 @@
 #endif
 
 void sk_fsync(FILE* f) {
-#if !defined(SK_BUILD_FOR_ANDROID) && !defined(__UCLIBC__) && !defined(_NEWLIB_VERSION)
+#if !defined(SK_BUILD_FOR_ANDROID) && !defined(__UCLIBC__) && !defined(_NEWLIB_VERSION) && !defined(__OpenBSD__)
     int fd = fileno(f);
     fsync(fd);
 #endif
