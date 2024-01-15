--- build.sh.orig	2022-12-22 22:17:08 UTC
+++ build.sh
@@ -583,7 +583,7 @@ echo "      RPATH=$RPATH"
 mkdir -p $BUILD
 
 # source the version script
-. $(dirname $(which $0))/version.sh
+. version.sh
 
 # qmake needs absolute paths, so we get them now:
 #   OSX does not have `readlink -f` command. Use equivalent Perl script.
@@ -596,11 +596,7 @@ else
 fi
 
 if [ "$IS_MAC" = "no" ]; then
-  if ( gmake -v >/dev/null 2>/dev/null ); then
     MAKE_PRG=gmake
-  else
-    MAKE_PRG=make
-  fi
 else
   MAKE_PRG=make
 fi
@@ -625,83 +621,85 @@ $QMAKE -v
 # Force a minimum rebuild because of version info
 touch $CURR_DIR/src/version/version.h
 
-qmake_options=(
+qmake_options="
   -recursive
-  CONFIG+="$CONFIG"
-  RUBYLIBFILE="$RUBYLIBFILE"
-  RUBYVERSIONCODE="$RUBYVERSIONCODE"
-  HAVE_RUBY="$HAVE_RUBY"
-  PYTHON="$PYTHON"
-  PYTHONLIBFILE="$PYTHONLIBFILE"
-  PYTHONINCLUDE="$PYTHONINCLUDE"
-  PYTHONEXTSUFFIX="$PYTHONEXTSUFFIX"
-  HAVE_PYTHON="$HAVE_PYTHON"
-  HAVE_QTBINDINGS="$HAVE_QTBINDINGS"
-  HAVE_QT_UITOOLS="$HAVE_QT_UITOOLS"
-  HAVE_QT_NETWORK="$HAVE_QT_NETWORK"
-  HAVE_QT_SQL="$HAVE_QT_SQL"
-  HAVE_QT_SVG="$HAVE_QT_SVG"
-  HAVE_QT_PRINTSUPPORT="$HAVE_QT_PRINTSUPPORT"
-  HAVE_QT_MULTIMEDIA="$HAVE_QT_MULTIMEDIA"
-  HAVE_QT_DESIGNER="$HAVE_QT_DESIGNER"
-  HAVE_QT_XML="$HAVE_QT_XML"
-  HAVE_64BIT_COORD="$HAVE_64BIT_COORD"
-  HAVE_QT="$HAVE_QT"
-  HAVE_CURL="$HAVE_CURL"
-  HAVE_EXPAT="$HAVE_EXPAT"
-  HAVE_PNG="$HAVE_PNG"
-  PREFIX="$BIN"
-  RPATH="$RPATH"
-  KLAYOUT_VERSION="$KLAYOUT_VERSION"
-  KLAYOUT_VERSION_DATE="$KLAYOUT_VERSION_DATE"
-  KLAYOUT_VERSION_REV="$KLAYOUT_VERSION_REV"
-)
+  CONFIG+=\"$CONFIG\"
+  RUBYLIBFILE=\"$RUBYLIBFILE\"
+  RUBYVERSIONCODE=\"$RUBYVERSIONCODE\"
+  HAVE_RUBY=\"$HAVE_RUBY\"
+  PYTHON=\"$PYTHON\"
+  PYTHONLIBFILE=\"$PYTHONLIBFILE\"
+  PYTHONINCLUDE=\"$PYTHONINCLUDE\"
+  PYTHONEXTSUFFIX=\"$PYTHONEXTSUFFIX\"
+  HAVE_PYTHON=\"$HAVE_PYTHON\"
+  HAVE_QTBINDINGS=\"$HAVE_QTBINDINGS\"
+  HAVE_QT_UITOOLS=\"$HAVE_QT_UITOOLS\"
+  HAVE_QT_NETWORK=\"$HAVE_QT_NETWORK\"
+  HAVE_QT_SQL=\"$HAVE_QT_SQL\"
+  HAVE_QT_SVG=\"$HAVE_QT_SVG\"
+  HAVE_QT_PRINTSUPPORT=\"$HAVE_QT_PRINTSUPPORT\"
+  HAVE_QT_MULTIMEDIA=\"$HAVE_QT_MULTIMEDIA\"
+  HAVE_QT_DESIGNER=\"$HAVE_QT_DESIGNER\"
+  HAVE_QT_XML=\"$HAVE_QT_XML\"
+  HAVE_64BIT_COORD=\"$HAVE_64BIT_COORD\"
+  HAVE_QT=\"$HAVE_QT\"
+  HAVE_CURL=\"$HAVE_CURL\"
+  HAVE_EXPAT=\"$HAVE_EXPAT\"
+  HAVE_PNG=\"$HAVE_PNG\"
+  PREFIX=\"$BIN\"
+  RPATH=\"$RPATH\"
+  KLAYOUT_VERSION=\"$KLAYOUT_VERSION\"
+  KLAYOUT_VERSION_DATE=\"$KLAYOUT_VERSION_DATE\"
+  KLAYOUT_VERSION_REV=\"$KLAYOUT_VERSION_REV\"
+"
 
 # NOTE: qmake does not like include paths which clash with paths built into the compiler
 # hence we don't add RUBYINCLUDE or RUBYINCLUDE2 in this case (found on CentOS 8 where Ruby
 # headers are installed in /usr/include)
 if [ "$RUBYINCLUDE" != "/usr/include" ] && [  "$RUBYINCLUDE" != "/usr/local/include" ]; then
-  qmake_options+=( RUBYINCLUDE="$RUBYINCLUDE" )
+  qmake_options="$qmake_options RUBYINCLUDE=\"$RUBYINCLUDE\""
 fi
 if [ "$RUBYINCLUDE2" != "/usr/include" ] && [  "$RUBYINCLUDE2" != "/usr/local/include" ]; then
-  qmake_options+=( RUBYINCLUDE2="$RUBYINCLUDE2" )
+  qmake_options="$qmake_options RUBYINCLUDE2=\"$RUBYINCLUDE2\""
 fi
 
 # This should speed up build time considerably
 # https://ortogonal.github.io/ccache-and-qmake-qtcreator/
 if [ "$QMAKE_CCACHE" = 1 ]; then
-  qmake_options+=(
-    CONFIG+="ccache"
-  )
+  qmake_options="$qmake_options
+    CONFIG+=\"ccache\"
+  "
 fi
 
 if [ $BUILD_EXPERT = 1 ]; then
-  qmake_options+=(
-    QMAKE_AR="$AR cqs"
-    QMAKE_LINK_C="$CC"
-    QMAKE_LINK_C_SHLIB="$CC"
-    QMAKE_LINK="$CXX"
-    QMAKE_LINK_SHLIB="$CXX"
-    QMAKE_OBJCOPY="$OBJCOPY"
+  qmake_options="
+    QMAKE_AR=\"$AR cqs\"
+    QMAKE_LINK_C=\"$CC\"
+    QMAKE_LINK_C_SHLIB=\"$CC\"
+    QMAKE_LINK=\"$CXX\"
+    QMAKE_LINK_SHLIB=\"$CXX\"
+    QMAKE_OBJCOPY=\"$OBJCOPY\"
     QMAKE_RANLIB=
     QMAKE_STRIP=
-    QMAKE_CC="$CC"
-    QMAKE_CXX="$CXX"
-    QMAKE_CFLAGS="$CFLAGS"
+    QMAKE_CC=\"$CC\"
+    QMAKE_CXX=\"$CXX\"
+    QMAKE_CFLAGS=\"$CFLAGS\"
     QMAKE_CFLAGS_RELEASE=
     QMAKE_CFLAGS_DEBUG=
-    QMAKE_CXXFLAGS="$CXXFLAGS"
+    QMAKE_CXXFLAGS=\"$CXXFLAGS\"
     QMAKE_CXXFLAGS_RELEASE=
     QMAKE_CXXFLAGS_DEBUG=
-    QMAKE_LIBS="$LIBS"
-    QMAKE_LFLAGS="$LDFLAGS"
+    QMAKE_LIBS=\"$LIBS\"
+    QMAKE_LFLAGS=\"$LDFLAGS\"
     QMAKE_LFLAGS_RELEASE=
     QMAKE_LFLAGS_DEBUG=
-  )
+  "
 fi
 
-echo $QMAKE "$CURR_DIR/src/klayout.pro" "${qmake_options[@]}"
-$QMAKE "$CURR_DIR/src/klayout.pro" "${qmake_options[@]}"
+echo $QMAKE "$CURR_DIR/src/klayout.pro" ${qmake_options} \
+    QMAKE_CXXFLAGS="$CXXFLAGS" QMAKE_CFLAGS="$CFLAGS" QMAKE_LFLAGS="$LDFLAGS"
+$QMAKE "$CURR_DIR/src/klayout.pro" ${qmake_options} \
+    QMAKE_CXXFLAGS="$CXXFLAGS" QMAKE_CFLAGS="$CFLAGS" QMAKE_LFLAGS="$LDFLAGS"
 
 cd $CURR_DIR
 echo ""
