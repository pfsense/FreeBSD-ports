--- base/rand_util.h.orig	2022-05-11 07:16:46 UTC
+++ base/rand_util.h
@@ -77,7 +77,7 @@ void RandomShuffle(Itr first, Itr last) {
   std::shuffle(first, last, RandomBitGenerator());
 }
 
-#if defined(OS_POSIX)
+#if defined(OS_POSIX) && !defined(OS_OPENBSD)
 BASE_EXPORT int GetUrandomFD();
 #endif
 
