--- src/phonenumbers/base/synchronization/lock.h.orig	2020-01-18 23:13:17 UTC
+++ src/phonenumbers/base/synchronization/lock.h
@@ -63,7 +63,7 @@ class Lock { (private)
 // Dummy lock implementation on non-POSIX platforms. If you are running on a
 // different platform and care about thread-safety, please compile with
 // -DI18N_PHONENUMBERS_USE_BOOST.
-#elif !defined(__linux__) && !defined(__APPLE__)
+#elif !defined(__linux__) && !defined(__APPLE__) && !defined(__FreeBSD__)
 
 namespace i18n {
 namespace phonenumbers {
