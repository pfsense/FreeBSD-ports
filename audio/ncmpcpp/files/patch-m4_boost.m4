--- m4/boost.m4.orig	2024-10-24 12:28:08 UTC
+++ m4/boost.m4
@@ -22,7 +22,7 @@ m4_define([_BOOST_SERIAL], [m4_translit([
 # along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
 m4_define([_BOOST_SERIAL], [m4_translit([
-# serial 26
+# serial 39
 ], [#
 ], [])])
 
@@ -226,7 +226,7 @@ AC_LANG_POP([C++])dnl
   AC_CACHE_CHECK([for Boost's header version],
     [boost_cv_lib_version],
     [m4_pattern_allow([^BOOST_LIB_VERSION$])dnl
-     _BOOST_SED_CPP([[/^boost-lib-version = /{s///;s/[\" ]//g;p;q;}]],
+     _BOOST_SED_CPP([[/^.*boost-lib-version = /{s///;s/[\" ]//g;p;q;}]],
                     [#include <boost/version.hpp>
 boost-lib-version = BOOST_LIB_VERSION],
     [boost_cv_lib_version=`cat conftest.i`])])
@@ -288,14 +288,17 @@ fi
 
 # BOOST_FIND_LIBS([COMPONENT-NAME], [CANDIDATE-LIB-NAMES],
 #                 [PREFERRED-RT-OPT], [HEADER-NAME], [CXX-TEST],
-#                 [CXX-PROLOGUE])
+#                 [CXX-PROLOGUE], [CXX-POST-INCLUDE-PROLOGUE],
+#                 [ERROR_ON_UNUSABLE])
 # --------------------------------------------------------------
 # Look for the Boost library COMPONENT-NAME (e.g., `thread', for
 # libboost_thread) under the possible CANDIDATE-LIB-NAMES (e.g.,
 # "thread_win32 thread").  Check that HEADER-NAME works and check that
 # libboost_LIB-NAME can link with the code CXX-TEST.  The optional
 # argument CXX-PROLOGUE can be used to include some C++ code before
-# the `main' function.
+# the `main' function. The CXX-POST-INCLUDE-PROLOGUE can be used to
+# include some code before the `main' function, but after the
+# `#include <HEADER-NAME>'.
 #
 # Invokes BOOST_FIND_HEADER([HEADER-NAME]) (see above).
 #
@@ -309,6 +312,9 @@ fi
 # builds.  Some sample values for PREFERRED-RT-OPT: (nothing), mt, d, mt-d, gdp
 # ...  If you want to make sure you have a specific version of Boost
 # (eg, >= 1.33) you *must* invoke BOOST_REQUIRE before this macro.
+#
+# ERROR_ON_UNUSABLE can be set to "no" if the caller does not want their
+# configure to fail
 AC_DEFUN([BOOST_FIND_LIBS],
 [AC_REQUIRE([BOOST_REQUIRE])dnl
 AC_REQUIRE([_BOOST_FIND_COMPILER_TAG])dnl
@@ -317,26 +323,32 @@ else
 if test x"$boost_cv_inc_path" = xno; then
   AC_MSG_NOTICE([Boost not available, not searching for the Boost $1 library])
 else
-dnl The else branch is huge and wasn't intended on purpose.
+dnl The else branch is huge and wasn't indented on purpose.
 AC_LANG_PUSH([C++])dnl
 AS_VAR_PUSHDEF([Boost_lib], [boost_cv_lib_$1])dnl
 AS_VAR_PUSHDEF([Boost_lib_LDFLAGS], [boost_cv_lib_$1_LDFLAGS])dnl
 AS_VAR_PUSHDEF([Boost_lib_LDPATH], [boost_cv_lib_$1_LDPATH])dnl
 AS_VAR_PUSHDEF([Boost_lib_LIBS], [boost_cv_lib_$1_LIBS])dnl
-BOOST_FIND_HEADER([$4])
+AS_IF([test x"$8" = "xno"], [not_found_header='true'])
+BOOST_FIND_HEADER([$4], [$not_found_header])
 boost_save_CPPFLAGS=$CPPFLAGS
 CPPFLAGS="$CPPFLAGS $BOOST_CPPFLAGS"
 AC_CACHE_CHECK([for the Boost $1 library], [Boost_lib],
                [_BOOST_FIND_LIBS($@)])
 case $Boost_lib in #(
+  (yes) _AC_MSG_LOG_CONFTEST
+    AC_DEFINE(AS_TR_CPP([HAVE_BOOST_$1]), [1], [Defined if the Boost $1 library is available])dnl
+    AC_SUBST(AS_TR_CPP([BOOST_$1_LDFLAGS]), [$Boost_lib_LDFLAGS])dnl
+    AC_SUBST(AS_TR_CPP([BOOST_$1_LDPATH]), [$Boost_lib_LDPATH])dnl
+    AC_SUBST([BOOST_LDPATH], [$Boost_lib_LDPATH])dnl
+    AC_SUBST(AS_TR_CPP([BOOST_$1_LIBS]), [$Boost_lib_LIBS])dnl
+    ;;
   (no) _AC_MSG_LOG_CONFTEST
-    AC_MSG_ERROR([cannot find the flags to link with Boost $1])
+    AS_IF([test x"$8" != "xno"], [
+      AC_MSG_ERROR([cannot find flags to link with the Boost $1 library (libboost-$1)])
+    ])
     ;;
 esac
-AC_SUBST(AS_TR_CPP([BOOST_$1_LDFLAGS]), [$Boost_lib_LDFLAGS])dnl
-AC_SUBST(AS_TR_CPP([BOOST_$1_LDPATH]), [$Boost_lib_LDPATH])dnl
-AC_SUBST([BOOST_LDPATH], [$Boost_lib_LDPATH])dnl
-AC_SUBST(AS_TR_CPP([BOOST_$1_LIBS]), [$Boost_lib_LIBS])dnl
 CPPFLAGS=$boost_save_CPPFLAGS
 AS_VAR_POPDEF([Boost_lib])dnl
 AS_VAR_POPDEF([Boost_lib_LDFLAGS])dnl
@@ -349,16 +361,20 @@ fi
 
 # BOOST_FIND_LIB([LIB-NAME],
 #                [PREFERRED-RT-OPT], [HEADER-NAME], [CXX-TEST],
-#                [CXX-PROLOGUE])
+#                [CXX-PROLOGUE], [CXX-POST-INCLUDE-PROLOGUE],
+#                [ERROR_ON_UNUSABLE])
 # --------------------------------------------------------------
 # Backward compatibility wrapper for BOOST_FIND_LIBS.
+# ERROR_ON_UNUSABLE can be set to "no" if the caller does not want their
+# configure to fail
 AC_DEFUN([BOOST_FIND_LIB],
 [BOOST_FIND_LIBS([$1], $@)])
 
 
 # _BOOST_FIND_LIBS([LIB-NAME], [CANDIDATE-LIB-NAMES],
 #                 [PREFERRED-RT-OPT], [HEADER-NAME], [CXX-TEST],
-#                 [CXX-PROLOGUE])
+#                 [CXX-PROLOGUE], [CXX-POST-INCLUDE-PROLOGUE],
+#                 [ERROR_ON_UNUSABLE])
 # --------------------------------------------------------------
 # Real implementation of BOOST_FIND_LIBS: rely on these local macros:
 # Boost_lib, Boost_lib_LDFLAGS, Boost_lib_LDPATH, Boost_lib_LIBS
@@ -370,6 +386,9 @@ AC_DEFUN([BOOST_FIND_LIB],
 # usually installed.  If we can't find the standard variants, we try
 # to enforce -mt (for instance on MacOSX, libboost_thread.dylib
 # doesn't exist but there's -obviously- libboost_thread-mt.dylib).
+#
+# ERROR_ON_UNUSABLE can be set to "no" if the caller does not want their
+# configure to fail
 AC_DEFUN([_BOOST_FIND_LIBS],
 [Boost_lib=no
   case "$3" in #(
@@ -396,7 +415,8 @@ AC_DEFUN([_BOOST_FIND_LIBS],
     AC_MSG_ERROR([the libext variable is empty, did you invoke Libtool?])
   boost_save_ac_objext=$ac_objext
   # Generate the test file.
-  AC_LANG_CONFTEST([AC_LANG_PROGRAM([#include <$4>
+  AC_LANG_CONFTEST([AC_LANG_PROGRAM([$7
+#include <$4>
 $6], [$5])])
 dnl Optimization hacks: compiling C++ is slow, especially with Boost.  What
 dnl we're trying to do here is guess the right combination of link flags
@@ -416,7 +436,10 @@ dnl start the for loops).
 dnl start the for loops).
   AC_COMPILE_IFELSE([],
     [ac_objext=do_not_rm_me_plz],
-    [AC_MSG_ERROR([cannot compile a test that uses Boost $1])])
+    [AS_IF([test x"$8" != x"no"], [
+       AC_MSG_ERROR([cannot compile a test that uses Boost $1])
+     ])
+    ])
   ac_objext=$boost_save_ac_objext
   boost_failed_libs=
 # Don't bother to ident the following nested for loops, only the 2
@@ -426,12 +449,15 @@ for boost_rtopt_ in $boost_rtopt '' -d; do
 for boost_ver_ in -$boost_cv_lib_version ''; do
 for boost_mt_ in $boost_mt -mt ''; do
 for boost_rtopt_ in $boost_rtopt '' -d; do
-  for boost_lib in \
-    boost_$boost_lib_$boost_tag_$boost_mt_$boost_rtopt_$boost_ver_ \
-    boost_$boost_lib_$boost_tag_$boost_rtopt_$boost_ver_ \
-    boost_$boost_lib_$boost_tag_$boost_mt_$boost_ver_ \
-    boost_$boost_lib_$boost_tag_$boost_ver_
+  for boost_full_suffix in \
+    $boost_last_suffix \
+    x$boost_tag_$boost_mt_$boost_rtopt_$boost_ver_ \
+    x$boost_tag_$boost_rtopt_$boost_ver_ \
+    x$boost_tag_$boost_mt_$boost_ver_ \
+    x$boost_tag_$boost_ver_
   do
+    boost_real_suffix=`echo "$boost_full_suffix" | sed 's/^x//'`
+    boost_lib="boost_$boost_lib_$boost_real_suffix"
     # Avoid testing twice the same lib
     case $boost_failed_libs in #(
       (*@$boost_lib@*) continue;;
@@ -480,7 +506,7 @@ dnl generated only once above (before we start the for
            *)
             for boost_cv_rpath_link_ldflag in -Wl,-R, -Wl,-rpath,; do
               LDFLAGS="$boost_save_LDFLAGS -L$boost_ldpath $boost_cv_rpath_link_ldflag$boost_ldpath"
-              LIBS="$boost_save_LIBS $Boost_lib_LIBS"
+              LIBS="$Boost_lib_LIBS $boost_save_LIBS"
               _BOOST_AC_LINK_IFELSE([],
                 [boost_rpath_link_ldflag_found=yes
                 break],
@@ -496,6 +522,7 @@ dnl generated only once above (before we start the for
         test x"$boost_ldpath" != x &&
           Boost_lib_LDFLAGS="-L$boost_ldpath $boost_cv_rpath_link_ldflag$boost_ldpath"
         Boost_lib_LDPATH="$boost_ldpath"
+        boost_last_suffix="$boost_full_suffix"
         break 7
       else
         boost_failed_libs="$boost_failed_libs@$boost_lib@"
@@ -534,6 +561,14 @@ m4_popdef([BOOST_Library])dnl
 ])
 ])
 
+
+# BOOST_ANY()
+# ------------
+# Look for Boost.Any
+BOOST_DEFUN([Any],
+[BOOST_FIND_HEADER([boost/any.hpp])])
+
+
 # BOOST_ARRAY()
 # -------------
 # Look for Boost.Array
@@ -548,7 +583,13 @@ BOOST_FIND_HEADER([boost/asio.hpp])])
 [AC_REQUIRE([BOOST_SYSTEM])dnl
 BOOST_FIND_HEADER([boost/asio.hpp])])
 
+# BOOST_BIMAP()
+# ------------
+# Look for Boost.Bimap
+BOOST_DEFUN([Bimap],
+[BOOST_FIND_HEADER([boost/bimap.hpp])])
 
+
 # BOOST_ASSIGN()
 # -------------
 # Look for Boost.Assign
@@ -556,6 +597,24 @@ BOOST_DEFUN([Assign],
 [BOOST_FIND_HEADER([boost/assign.hpp])])
 
 
+# BOOST_ATOMIC([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
+# -------------------------------
+# Look for Boost.Atomic.  For the documentation of PREFERRED-RT-OPT, see the
+# documentation of BOOST_FIND_LIB above.
+BOOST_DEFUN([Atomic],
+[BOOST_FIND_LIB([atomic], [$1],
+                [boost/atomic.hpp],
+                [boost::atomic<int> a;],
+                [ ],
+                [#ifdef HAVE_UNISTD_H
+#include <unistd.h>
+#endif
+#ifdef HAVE_STDINT_H
+#include <stdint.h>
+#endif], [$2])
+])# BOOST_ATOMIC
+
+
 # BOOST_BIND()
 # ------------
 # Look for Boost.Bind.
@@ -563,7 +622,14 @@ BOOST_DEFUN([Bind],
 [BOOST_FIND_HEADER([boost/bind.hpp])])
 
 
-# BOOST_CHRONO()
+# BOOST_CAST()
+# ------------
+# Look for Boost.Cast
+BOOST_DEFUN([Cast],
+[BOOST_FIND_HEADER([boost/cast.hpp])])
+
+
+# BOOST_CHRONO([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # --------------
 # Look for Boost.Chrono.
 BOOST_DEFUN([Chrono],
@@ -571,7 +637,7 @@ if test $boost_major_version -ge 135; then
 # added as of 1.35.0.  If we have a version <1.35, we must not attempt to
 # find Boost.System as it didn't exist by then.
 if test $boost_major_version -ge 135; then
-  BOOST_SYSTEM([$1])
+  BOOST_SYSTEM([$1], [$2])
 fi # end of the Boost.System check.
 boost_filesystem_save_LIBS=$LIBS
 boost_filesystem_save_LDFLAGS=$LDFLAGS
@@ -580,7 +646,7 @@ BOOST_FIND_LIB([chrono], [$1],
 LDFLAGS="$LDFLAGS $BOOST_SYSTEM_LDFLAGS"
 BOOST_FIND_LIB([chrono], [$1],
                 [boost/chrono.hpp],
-                [boost::chrono::thread_clock d;])
+                [boost::chrono::thread_clock d;], [], [], [$2])
 if test $enable_static_boost = yes && test $boost_major_version -ge 135; then
   BOOST_CHRONO_LIBS="$BOOST_CHRONO_LIBS $BOOST_SYSTEM_LIBS"
 fi
@@ -589,7 +655,7 @@ LDFLAGS=$boost_filesystem_save_LDFLAGS
 ])# BOOST_CHRONO
 
 
-# BOOST_CONTEXT([PREFERRED-RT-OPT])
+# BOOST_CONTEXT([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------
 # Look for Boost.Context.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
@@ -597,18 +663,77 @@ LDFLAGS=$boost_filesystem_save_LDFLAGS
 # * This library was introduced in Boost 1.51.0
 # * The signatures of make_fcontext() and jump_fcontext were changed in 1.56.0
 # * A dependency on boost_thread appears in 1.57.0
+# * The implementation details were moved to boost::context::detail in 1.61.0
+# * 1.61 also introduces execution_context_v2, which is the "lowest common
+#   denominator" for boost::context presence since then.
+# * boost::context::fiber was introduced in 1.69 and execution_context_v2 was
+#   removed in 1.72
 BOOST_DEFUN([Context],
 [boost_context_save_LIBS=$LIBS
  boost_context_save_LDFLAGS=$LDFLAGS
 if test $boost_major_version -ge 157; then
-  BOOST_THREAD([$1])
+  BOOST_THREAD([$1], [$2])
   m4_pattern_allow([^BOOST_THREAD_(LIBS|LDFLAGS)$])dnl
   LIBS="$LIBS $BOOST_THREAD_LIBS"
   LDFLAGS="$LDFLAGS $BOOST_THREAD_LDFLAGS"
 fi
+
+if test $boost_major_version -ge 169; then
+
 BOOST_FIND_LIB([context], [$1],
-                [boost/context/all.hpp],[[
+                [boost/context/fiber.hpp], [[
+namespace ctx=boost::context;
+int a;
+ctx::fiber source{[&a](ctx::fiber&& sink){
+    a=0;
+    int b=1;
+    for(;;){
+        sink=std::move(sink).resume();
+        int next=a+b;
+        a=b;
+        b=next;
+    }
+    return std::move(sink);
+}};
+for (int j=0;j<10;++j) {
+    source=std::move(source).resume();
+}
+return a == 34;
+]], [], [], [$2])
 
+elif test $boost_major_version -ge 161; then
+
+BOOST_FIND_LIB([context], [$1],
+                [boost/context/execution_context_v2.hpp], [[
+namespace ctx=boost::context;
+int res=0;
+int n=35;
+ctx::execution_context<int> source(
+    [n, &res](ctx::execution_context<int> sink, int) mutable {
+        int a=0;
+        int b=1;
+        while(n-->0){
+            auto result=sink(a);
+            sink=std::move(std::get<0>(result));
+            auto next=a+b;
+            a=b;
+            b=next;
+        }
+        return sink;
+    });
+for(int i=0;i<10;++i){
+    auto result=source(i);
+    source=std::move(std::get<0>(result));
+    res = std::get<1>(result);
+}
+return res == 34;
+]], [], [], [$2])
+
+else
+
+BOOST_FIND_LIB([context], [$1],
+                [boost/context/fcontext.hpp],[[
+
 // creates a stack
 void * stack_pointer = new void*[4096];
 std::size_t const size = sizeof(void*[4096]);
@@ -662,7 +787,10 @@ static void f(intptr_t i) {
     ctx::jump_fcontext(&fc, fcm, i * 2);
 }
 #endif
-])
+], [], [], [$2])
+
+fi
+
 LIBS=$boost_context_save_LIBS
 LDFLAGS=$boost_context_save_LDFLAGS
 ])# BOOST_CONTEXT
@@ -677,7 +805,7 @@ BOOST_FIND_HEADER([boost/lexical_cast.hpp])
 ])# BOOST_CONVERSION
 
 
-# BOOST_COROUTINE([PREFERRED-RT-OPT])
+# BOOST_COROUTINE([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------
 # Look for Boost.Coroutine.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.  This library was introduced in Boost
@@ -687,10 +815,10 @@ boost_coroutine_save_LDFLAGS=$LDFLAGS
 boost_coroutine_save_LIBS=$LIBS
 boost_coroutine_save_LDFLAGS=$LDFLAGS
 # Link-time dependency from coroutine to context
-BOOST_CONTEXT([$1])
+BOOST_CONTEXT([$1], [$2])
 # Starting from Boost 1.55 a dependency on Boost.System is added
 if test $boost_major_version -ge 155; then
-  BOOST_SYSTEM([$1])
+  BOOST_SYSTEM([$1], [$2])
 fi
 m4_pattern_allow([^BOOST_(CONTEXT|SYSTEM)_(LIBS|LDFLAGS)])
 LIBS="$LIBS $BOOST_CONTEXT_LIBS $BOOST_SYSTEM_LIBS"
@@ -698,7 +826,8 @@ if test $boost_major_version -eq 153; then
 
 # in 1.53 coroutine was a header only library
 if test $boost_major_version -eq 153; then
-  BOOST_FIND_HEADER([boost/coroutine/coroutine.hpp])
+  AS_IF([test x"$2" = "xno"], [not_found_header='true'])
+  BOOST_FIND_HEADER([boost/coroutine/coroutine.hpp], [$not_found_header])
 else
   BOOST_FIND_LIB([coroutine], [$1],
 		  [boost/coroutine/coroutine.hpp],
@@ -709,7 +838,7 @@ else
   #else
   boost::coroutines::asymmetric_coroutine<int>::pull_type coro; coro.get();
   #endif
-  ])
+  ], [], [], [$2])
 fi
 # Link-time dependency from coroutine to context, existed only in 1.53, in 1.54
 # coroutine doesn't use context from its headers but from its library.
@@ -734,18 +863,25 @@ BOOST_DEFUN([CRC],
 ])# BOOST_CRC
 
 
-# BOOST_DATE_TIME([PREFERRED-RT-OPT])
+# BOOST_DATE_TIME([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------
 # Look for Boost.Date_Time.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([Date_Time],
 [BOOST_FIND_LIB([date_time], [$1],
                 [boost/date_time/posix_time/posix_time.hpp],
-                [boost::posix_time::ptime t;])
+                [boost::posix_time::ptime t;], [], [], [$2])
 ])# BOOST_DATE_TIME
 
 
-# BOOST_FILESYSTEM([PREFERRED-RT-OPT])
+# BOOST_EXCEPTION()
+# ------------
+# Look for Boost.Exception
+BOOST_DEFUN([Exception],
+[BOOST_FIND_HEADER([boost/exception/all.hpp])])
+
+
+# BOOST_FILESYSTEM([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ------------------------------------
 # Look for Boost.Filesystem.  For the documentation of PREFERRED-RT-OPT, see
 # the documentation of BOOST_FIND_LIB above.
@@ -756,7 +892,7 @@ if test $boost_major_version -ge 135; then
 # added as of 1.35.0.  If we have a version <1.35, we must not attempt to
 # find Boost.System as it didn't exist by then.
 if test $boost_major_version -ge 135; then
-  BOOST_SYSTEM([$1])
+  BOOST_SYSTEM([$1], [$2])
 fi # end of the Boost.System check.
 boost_filesystem_save_LIBS=$LIBS
 boost_filesystem_save_LDFLAGS=$LDFLAGS
@@ -764,7 +900,8 @@ BOOST_FIND_LIB([filesystem], [$1],
 LIBS="$LIBS $BOOST_SYSTEM_LIBS"
 LDFLAGS="$LDFLAGS $BOOST_SYSTEM_LDFLAGS"
 BOOST_FIND_LIB([filesystem], [$1],
-                [boost/filesystem/path.hpp], [boost::filesystem::path p;])
+                [boost/filesystem/path.hpp], [boost::filesystem::path p;],
+                [], [], [$2])
 if test $enable_static_boost = yes && test $boost_major_version -ge 135; then
   BOOST_FILESYSTEM_LIBS="$BOOST_FILESYSTEM_LIBS $BOOST_SYSTEM_LIBS"
 fi
@@ -809,6 +946,13 @@ BOOST_DEFUN([Function],
 [BOOST_FIND_HEADER([boost/function.hpp])])
 
 
+# BOOST_FUSION()
+# -----------------
+# Look for Boost.Fusion
+BOOST_DEFUN([Fusion],
+[BOOST_FIND_HEADER([boost/fusion/sequence.hpp])])
+
+
 # BOOST_GEOMETRY()
 # ----------------
 # Look for Boost.Geometry (new since 1.47.0).
@@ -817,7 +961,7 @@ BOOST_DEFUN([Geometry],
 ])# BOOST_GEOMETRY
 
 
-# BOOST_GRAPH([PREFERRED-RT-OPT])
+# BOOST_GRAPH([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -------------------------------
 # Look for Boost.Graphs.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
@@ -826,34 +970,43 @@ if test $boost_major_version -ge 140; then
 boost_graph_save_LDFLAGS=$LDFLAGS
 # Link-time dependency from graph to regex was added as of 1.40.0.
 if test $boost_major_version -ge 140; then
-  BOOST_REGEX([$1])
+  BOOST_REGEX([$1], [$2])
   m4_pattern_allow([^BOOST_REGEX_(LIBS|LDFLAGS)$])dnl
   LIBS="$LIBS $BOOST_REGEX_LIBS"
   LDFLAGS="$LDFLAGS $BOOST_REGEX_LDFLAGS"
 fi
 BOOST_FIND_LIB([graph], [$1],
-                [boost/graph/adjacency_list.hpp], [boost::adjacency_list<> g;])
+                [boost/graph/adjacency_list.hpp], [boost::adjacency_list<> g;],
+                [], [], [$2])
 LIBS=$boost_graph_save_LIBS
 LDFLAGS=$boost_graph_save_LDFLAGS
 ])# BOOST_GRAPH
 
 
-# BOOST_IOSTREAMS([PREFERRED-RT-OPT])
+# BOOST_HASH()
+# ------------
+# Look for Boost.Functional/Hash
+BOOST_DEFUN([Hash],
+[BOOST_FIND_HEADER([boost/functional/hash.hpp])])
+
+
+# BOOST_IOSTREAMS([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------
 # Look for Boost.IOStreams.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([IOStreams],
 [BOOST_FIND_LIB([iostreams], [$1],
                 [boost/iostreams/device/file_descriptor.hpp],
-                [boost::iostreams::file_descriptor fd; fd.close();])
+                [boost::iostreams::file_descriptor fd; fd.close();],
+                [], [], [$2])
 ])# BOOST_IOSTREAMS
 
 
-# BOOST_HASH()
+# BOOST_ITERATOR()
 # ------------
-# Look for Boost.Functional/Hash
-BOOST_DEFUN([Hash],
-[BOOST_FIND_HEADER([boost/functional/hash.hpp])])
+# Look for Boost.Iterator
+BOOST_DEFUN([Iterator],
+[BOOST_FIND_HEADER([boost/iterator/iterator_adaptor.hpp])])
 
 
 # BOOST_LAMBDA()
@@ -863,7 +1016,7 @@ BOOST_DEFUN([Lambda],
 [BOOST_FIND_HEADER([boost/lambda/lambda.hpp])])
 
 
-# BOOST_LOCALE()
+# BOOST_LOCALE([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # --------------
 # Look for Boost.Locale
 BOOST_DEFUN([Locale],
@@ -872,40 +1025,40 @@ if test $boost_major_version -ge 150; then
 boost_locale_save_LDFLAGS=$LDFLAGS
 # require SYSTEM for boost-1.50.0 and up
 if test $boost_major_version -ge 150; then
-  BOOST_SYSTEM([$1])
+  BOOST_SYSTEM([$1], [$2])
   m4_pattern_allow([^BOOST_SYSTEM_(LIBS|LDFLAGS)$])dnl
   LIBS="$LIBS $BOOST_SYSTEM_LIBS"
   LDFLAGS="$LDFLAGS $BOOST_SYSTEM_LDFLAGS"
 fi # end of the Boost.System check.
 BOOST_FIND_LIB([locale], [$1],
     [boost/locale.hpp],
-    [[boost::locale::generator gen; std::locale::global(gen(""));]])
+    [[boost::locale::generator gen; std::locale::global(gen(""));]], [], [], [$2])
 LIBS=$boost_locale_save_LIBS
 LDFLAGS=$boost_locale_save_LDFLAGS
 ])# BOOST_LOCALE
 
-# BOOST_LOG([PREFERRED-RT-OPT])
+# BOOST_LOG([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------
 # Look for Boost.Log.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([Log],
 [boost_log_save_LIBS=$LIBS
 boost_log_save_LDFLAGS=$LDFLAGS
-BOOST_SYSTEM([$1])
-BOOST_FILESYSTEM([$1])
-BOOST_DATE_TIME([$1])
+BOOST_SYSTEM([$1], [$2])
+BOOST_FILESYSTEM([$1], [$2])
+BOOST_DATE_TIME([$1], [$2])
 m4_pattern_allow([^BOOST_(SYSTEM|FILESYSTEM|DATE_TIME)_(LIBS|LDFLAGS)$])dnl
 LIBS="$LIBS $BOOST_DATE_TIME_LIBS $BOOST_FILESYSTEM_LIBS $BOOST_SYSTEM_LIBS"
 LDFLAGS="$LDFLAGS $BOOST_DATE_TIME_LDFLAGS $BOOST_FILESYSTEM_LDFLAGS $BOOST_SYSTEM_LDFLAGS"
 BOOST_FIND_LIB([log], [$1],
     [boost/log/core/core.hpp],
-    [boost::log::attribute a; a.get_value();])
+    [boost::log::attribute a; a.get_value();], [], [], [$2])
 LIBS=$boost_log_save_LIBS
 LDFLAGS=$boost_log_save_LDFLAGS
 ])# BOOST_LOG
 
 
-# BOOST_LOG_SETUP([PREFERRED-RT-OPT])
+# BOOST_LOG_SETUP([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------
 # Look for Boost.Log.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
@@ -918,7 +1071,7 @@ BOOST_FIND_LIB([log_setup], [$1],
 LDFLAGS="$LDFLAGS $BOOST_LOG_LDFLAGS"
 BOOST_FIND_LIB([log_setup], [$1],
     [boost/log/utility/setup/from_settings.hpp],
-    [boost::log::basic_settings<char> bs; bs.empty();])
+    [boost::log::basic_settings<char> bs; bs.empty();], [], [], [$2])
 LIBS=$boost_log_setup_save_LIBS
 LDFLAGS=$boost_log_setup_save_LDFLAGS
 ])# BOOST_LOG_SETUP
@@ -936,7 +1089,7 @@ BOOST_DEFUN([Math],
 [BOOST_FIND_HEADER([boost/math/special_functions.hpp])])
 
 
-# BOOST_MPI([PREFERRED-RT-OPT])
+# BOOST_MPI([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -------------------------------
 # Look for Boost MPI.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.  Uses MPICXX variable if it is
@@ -953,12 +1106,20 @@ BOOST_FIND_LIB([mpi], [$1],
                [boost/mpi.hpp],
                [int argc = 0;
                 char **argv = 0;
-                boost::mpi::environment env(argc,argv);])
+                boost::mpi::environment env(argc,argv);],
+               [], [], [$2])
 CXX=${boost_save_CXX}
 CXXCPP=${boost_save_CXXCPP}
 ])# BOOST_MPI
 
 
+# BOOST_MPL()
+# ------------------
+# Look for Boost.MPL
+BOOST_DEFUN([MPL],
+[BOOST_FIND_HEADER([boost/mpl/for_each.hpp])])
+
+
 # BOOST_MULTIARRAY()
 # ------------------
 # Look for Boost.MultiArray
@@ -966,6 +1127,13 @@ BOOST_DEFUN([MultiArray],
 [BOOST_FIND_HEADER([boost/multi_array.hpp])])
 
 
+# BOOST_MULTIINDEXCCONTAINER()
+# ------------------
+# Look for Boost.MultiIndexContainer
+BOOST_DEFUN([MultiIndexContainer],
+[BOOST_FIND_HEADER([boost/multi_index_container.hpp])])
+
+
 # BOOST_NUMERIC_UBLAS()
 # --------------------------
 # Look for Boost.NumericUblas (Basic Linear Algebra)
@@ -996,6 +1164,25 @@ BOOST_DEFUN([Preprocessor],
 [BOOST_FIND_HEADER([boost/preprocessor/repeat.hpp])])
 
 
+# BOOST_PROPERTY_TREE([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
+# -----------------------------------------
+# Look for Boost.Property_Tree.  For the documentation of PREFERRED-RT-OPT,
+# see the documentation of BOOST_FIND_LIB above.
+BOOST_DEFUN([Property_Tree],
+[BOOST_FIND_LIB([property_tree], [$1],
+                [boost/property_tree/ptree.hpp],
+                [boost::property_tree::ptree pt; boost::property_tree::read_xml d("test", pt);],
+                [], [], [$2])
+])# BOOST_PROPERTY_TREE
+
+
+# BOOST_RANDOM()
+# --------------------
+# Look for Boost.Random
+BOOST_DEFUN([Random],
+[BOOST_FIND_HEADER([boost/random/random_number_generator.hpp])])
+
+
 # BOOST_RANGE()
 # --------------------
 # Look for Boost.Range
@@ -1016,14 +1203,15 @@ BOOST_DEFUN([Uuid],
 [BOOST_FIND_HEADER([boost/uuid/uuid.hpp])])
 
 
-# BOOST_PROGRAM_OPTIONS([PREFERRED-RT-OPT])
+# BOOST_PROGRAM_OPTIONS([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -----------------------------------------
 # Look for Boost.Program_options.  For the documentation of PREFERRED-RT-OPT,
 # see the documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([Program_Options],
 [BOOST_FIND_LIB([program_options], [$1],
                 [boost/program_options.hpp],
-                [boost::program_options::options_description d("test");])
+                [boost::program_options::options_description d("test");],
+                [], [], [$2])
 ])# BOOST_PROGRAM_OPTIONS
 
 
@@ -1039,7 +1227,7 @@ $1="$$1 $BOOST_PYTHON_$1"])
 $1="$$1 $BOOST_PYTHON_$1"])
 
 
-# BOOST_PYTHON([PREFERRED-RT-OPT])
+# BOOST_PYTHON([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # --------------------------------
 # Look for Boost.Python.  For the documentation of PREFERRED-RT-OPT,
 # see the documentation of BOOST_FIND_LIB above.
@@ -1050,7 +1238,7 @@ BOOST_FIND_LIBS([python], [python python3], [$1],
 m4_pattern_allow([^BOOST_PYTHON_MODULE$])dnl
 BOOST_FIND_LIBS([python], [python python3], [$1],
                 [boost/python.hpp],
-                [], [BOOST_PYTHON_MODULE(empty) {}])
+                [], [BOOST_PYTHON_MODULE(empty) {}], [], [$2])
 CPPFLAGS=$boost_python_save_CPPFLAGS
 LDFLAGS=$boost_python_save_LDFLAGS
 LIBS=$boost_python_save_LIBS
@@ -1064,18 +1252,26 @@ BOOST_DEFUN([Ref],
 [BOOST_FIND_HEADER([boost/ref.hpp])])
 
 
-# BOOST_REGEX([PREFERRED-RT-OPT])
+# BOOST_REGEX([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # -------------------------------
 # Look for Boost.Regex.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([Regex],
 [BOOST_FIND_LIB([regex], [$1],
                 [boost/regex.hpp],
-                [boost::regex exp("*"); boost::regex_match("foo", exp);])
+                [boost::regex exp("*"); boost::regex_match("foo", exp);],
+                [], [], [$2])
 ])# BOOST_REGEX
 
 
-# BOOST_SERIALIZATION([PREFERRED-RT-OPT])
+# BOOST_SCOPE_EXIT()
+# ------------
+# Look for Boost.ScopeExit.
+BOOST_DEFUN([SCOPE_EXIT],
+[BOOST_FIND_HEADER([boost/scope_exit.hpp])])
+
+
+# BOOST_SERIALIZATION([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ---------------------------------------
 # Look for Boost.Serialization.  For the documentation of PREFERRED-RT-OPT, see
 # the documentation of BOOST_FIND_LIB above.
@@ -1083,18 +1279,20 @@ BOOST_DEFUN([Serialization],
 [BOOST_FIND_LIB([serialization], [$1],
                 [boost/archive/text_oarchive.hpp],
                 [std::ostream* o = 0; // Cheap way to get an ostream...
-                boost::archive::text_oarchive t(*o);])
+                boost::archive::text_oarchive t(*o);],
+                [], [], [$2])
 ])# BOOST_SERIALIZATION
 
 
-# BOOST_SIGNALS([PREFERRED-RT-OPT])
+# BOOST_SIGNALS([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ---------------------------------
 # Look for Boost.Signals.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
 BOOST_DEFUN([Signals],
 [BOOST_FIND_LIB([signals], [$1],
                 [boost/signal.hpp],
-                [boost::signal<void ()> s;])
+                [boost::signal<void ()> s;],
+                [], [], [$2])
 ])# BOOST_SIGNALS
 
 
@@ -1130,19 +1328,24 @@ BOOST_DEFUN([String_Algo],
 ])
 
 
-# BOOST_SYSTEM([PREFERRED-RT-OPT])
+# BOOST_SYSTEM([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # --------------------------------
 # Look for Boost.System.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.  This library was introduced in Boost
-# 1.35.0.
+# 1.35.0 and is header only since 1.70.
 BOOST_DEFUN([System],
-[BOOST_FIND_LIB([system], [$1],
+[
+if test $boost_major_version -ge 170; then
+  BOOST_FIND_HEADER([boost/system/error_code.hpp])
+else
+  BOOST_FIND_LIB([system], [$1],
                 [boost/system/error_code.hpp],
-                [boost::system::error_code e; e.clear();])
+                [boost::system::error_code e; e.clear();], [], [], [$2])
+fi
 ])# BOOST_SYSTEM
 
 
-# BOOST_TEST([PREFERRED-RT-OPT])
+# BOOST_TEST([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ------------------------------
 # Look for Boost.Test.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
@@ -1152,11 +1355,11 @@ BOOST_FIND_LIB([unit_test_framework], [$1],
                [boost/test/unit_test.hpp], [BOOST_CHECK(2 == 2);],
                [using boost::unit_test::test_suite;
                test_suite* init_unit_test_suite(int argc, char ** argv)
-               { return NULL; }])
+               { return NULL; }], [], [$2])
 ])# BOOST_TEST
 
 
-# BOOST_THREAD([PREFERRED-RT-OPT])
+# BOOST_THREAD([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ---------------------------------
 # Look for Boost.Thread.  For the documentation of PREFERRED-RT-OPT, see the
 # documentation of BOOST_FIND_LIB above.
@@ -1170,7 +1373,7 @@ if test $boost_major_version -ge 149; then
 boost_thread_save_CPPFLAGS=$CPPFLAGS
 # Link-time dependency from thread to system was added as of 1.49.0.
 if test $boost_major_version -ge 149; then
-BOOST_SYSTEM([$1])
+BOOST_SYSTEM([$1], [$2])
 fi # end of the Boost.System check.
 m4_pattern_allow([^BOOST_SYSTEM_(LIBS|LDFLAGS)$])dnl
 LIBS="$LIBS $BOOST_SYSTEM_LIBS $boost_cv_pthread_flag"
@@ -1189,7 +1392,7 @@ BOOST_FIND_LIBS([thread], [thread$boost_thread_lib_ext
 fi
 BOOST_FIND_LIBS([thread], [thread$boost_thread_lib_ext],
                 [$1],
-                [boost/thread.hpp], [boost::thread t; boost::mutex m;])
+                [boost/thread.hpp], [boost::thread t; boost::mutex m;], [], [], [$2])
 
 case $host_os in
   (*mingw*) boost_thread_w32_socket_link=-lws2_32;;
@@ -1265,7 +1468,7 @@ BOOST_FIND_HEADER([boost/ptr_container/ptr_map.hpp])
 ])# BOOST_POINTER_CONTAINER
 
 
-# BOOST_WAVE([PREFERRED-RT-OPT])
+# BOOST_WAVE([PREFERRED-RT-OPT], [ERROR_ON_UNUSABLE])
 # ------------------------------
 # NOTE: If you intend to use Wave/Spirit with thread support, make sure you
 # call BOOST_THREAD first.
@@ -1283,7 +1486,7 @@ BOOST_FIND_LIB([wave], [$1],
 $BOOST_DATE_TIME_LDFLAGS $BOOST_THREAD_LDFLAGS"
 BOOST_FIND_LIB([wave], [$1],
                 [boost/wave.hpp],
-                [boost::wave::token_id id; get_token_name(id);])
+                [boost::wave::token_id id; get_token_name(id);], [], [], [$2])
 LIBS=$boost_wave_save_LIBS
 LDFLAGS=$boost_wave_save_LDFLAGS
 ])# BOOST_WAVE
@@ -1351,10 +1554,11 @@ AC_CACHE_CHECK([for the flags needed to use pthreads],
                            -pthreads -mthreads -lpthread --thread-safe -mt";;
   esac
   # Generate the test file.
-  AC_LANG_CONFTEST([AC_LANG_PROGRAM([#include <pthread.h>],
-    [pthread_t th; pthread_join(th, 0);
-    pthread_attr_init(0); pthread_cleanup_push(0, 0);
-    pthread_create(0,0,0,0); pthread_cleanup_pop(0);])])
+  AC_LANG_CONFTEST([AC_LANG_PROGRAM([#include <pthread.h>
+    void *f(void*){ return 0; }],
+    [pthread_t th; pthread_create(&th,0,f,0); pthread_join(th,0);
+    pthread_attr_t attr; pthread_attr_init(&attr); pthread_cleanup_push(0, 0);
+    pthread_cleanup_pop(0);])])
   for boost_pthread_flag in '' $boost_pthread_flags; do
     boost_pthread_ok=false
 dnl Re-use the test file already generated.
@@ -1416,12 +1620,77 @@ if test x$boost_cv_inc_path != xno; then
   # I'm not sure about my test for `il' (be careful: Intel's ICC pre-defines
   # the same defines as GCC's).
   for i in \
+    "defined __clang__ && __clang_major__ == 14 && __clang_minor__ == 0 @ clang140" \
+    "defined __clang__ && __clang_major__ == 13 && __clang_minor__ == 0 @ clang130" \
+    "defined __clang__ && __clang_major__ == 12 && __clang_minor__ == 0 @ clang120" \
+    "defined __clang__ && __clang_major__ == 11 && __clang_minor__ == 1 @ clang111" \
+    "defined __clang__ && __clang_major__ == 11 && __clang_minor__ == 0 @ clang110" \
+    "defined __clang__ && __clang_major__ == 10 && __clang_minor__ == 0 @ clang100" \
+    "defined __clang__ && __clang_major__ == 9 && __clang_minor__ == 0 @ clang90" \
+    "defined __clang__ && __clang_major__ == 8 && __clang_minor__ == 0 @ clang80" \
+    "defined __clang__ && __clang_major__ == 7 && __clang_minor__ == 0 @ clang70" \
+    "defined __clang__ && __clang_major__ == 6 && __clang_minor__ == 0 @ clang60" \
+    "defined __clang__ && __clang_major__ == 5 && __clang_minor__ == 0 @ clang50" \
+    "defined __clang__ && __clang_major__ == 4 && __clang_minor__ == 0 @ clang40" \
+    "defined __clang__ && __clang_major__ == 3 && __clang_minor__ == 9 @ clang39" \
+    "defined __clang__ && __clang_major__ == 3 && __clang_minor__ == 8 @ clang38" \
+    "defined __clang__ && __clang_major__ == 3 && __clang_minor__ == 7 @ clang37" \
+    _BOOST_mingw_test(11, 2) \
+    _BOOST_gcc_test(11, 2) \
+    _BOOST_mingw_test(11, 1) \
+    _BOOST_gcc_test(11, 1) \
+    _BOOST_mingw_test(10, 3) \
+    _BOOST_gcc_test(10, 3) \
+    _BOOST_mingw_test(10, 2) \
+    _BOOST_gcc_test(10, 2) \
+    _BOOST_mingw_test(10, 1) \
+    _BOOST_gcc_test(10, 1) \
+    _BOOST_mingw_test(9, 3) \
+    _BOOST_gcc_test(9, 3) \
+    _BOOST_mingw_test(9, 2) \
+    _BOOST_gcc_test(9, 2) \
+    _BOOST_mingw_test(9, 1) \
+    _BOOST_gcc_test(9, 1) \
+    _BOOST_mingw_test(9, 0) \
+    _BOOST_gcc_test(9, 0) \
+    _BOOST_mingw_test(8, 5) \
+    _BOOST_gcc_test(8, 5) \
+    _BOOST_mingw_test(8, 4) \
+    _BOOST_gcc_test(8, 4) \
+    _BOOST_mingw_test(8, 3) \
+    _BOOST_gcc_test(8, 3) \
+    _BOOST_mingw_test(8, 2) \
+    _BOOST_gcc_test(8, 2) \
+    _BOOST_mingw_test(8, 1) \
+    _BOOST_gcc_test(8, 1) \
+    _BOOST_mingw_test(8, 0) \
+    _BOOST_gcc_test(8, 0) \
+    _BOOST_mingw_test(7, 4) \
+    _BOOST_gcc_test(7, 4) \
+    _BOOST_mingw_test(7, 3) \
+    _BOOST_gcc_test(7, 3) \
+    _BOOST_mingw_test(7, 2) \
+    _BOOST_gcc_test(7, 2) \
+    _BOOST_mingw_test(7, 1) \
+    _BOOST_gcc_test(7, 1) \
+    _BOOST_mingw_test(7, 0) \
+    _BOOST_gcc_test(7, 0) \
+    _BOOST_mingw_test(6, 5) \
+    _BOOST_gcc_test(6, 5) \
+    _BOOST_mingw_test(6, 4) \
+    _BOOST_gcc_test(6, 4) \
+    _BOOST_mingw_test(6, 3) \
+    _BOOST_gcc_test(6, 3) \
     _BOOST_mingw_test(6, 2) \
     _BOOST_gcc_test(6, 2) \
     _BOOST_mingw_test(6, 1) \
     _BOOST_gcc_test(6, 1) \
     _BOOST_mingw_test(6, 0) \
     _BOOST_gcc_test(6, 0) \
+    _BOOST_mingw_test(5, 5) \
+    _BOOST_gcc_test(5, 5) \
+    _BOOST_mingw_test(5, 4) \
+    _BOOST_gcc_test(5, 4) \
     _BOOST_mingw_test(5, 3) \
     _BOOST_gcc_test(5, 3) \
     _BOOST_mingw_test(5, 2) \
