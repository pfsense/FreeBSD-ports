--- scipy/_lib/boost/boost/math/tools/config.hpp.orig	2021-11-01 02:28:55 UTC
+++ scipy/_lib/boost/boost/math/tools/config.hpp
@@ -28,7 +28,7 @@
 
 #include <boost/math/tools/user.hpp>
 
-#if (defined(__CYGWIN__) || defined(__FreeBSD__) || defined(__NetBSD__) || defined(__EMSCRIPTEN__)\
+#if (defined(__CYGWIN__) || defined(__NetBSD__) || defined(__EMSCRIPTEN__)\
    || (defined(__hppa) && !defined(__OpenBSD__)) || (defined(__NO_LONG_DOUBLE_MATH) && (DBL_MANT_DIG != LDBL_MANT_DIG))) \
    && !defined(BOOST_MATH_NO_LONG_DOUBLE_MATH_FUNCTIONS)
 #  define BOOST_MATH_NO_LONG_DOUBLE_MATH_FUNCTIONS
