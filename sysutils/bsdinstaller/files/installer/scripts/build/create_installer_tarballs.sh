#!/bin/sh -x

# $Id: create_installer_tarballs.sh,v 1.35 2005/08/18 04:11:33 cpressey Exp $
# Create tarballs from the contents of the CVS repo.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults
. $SCRIPTDIR/pver.conf

PVERSUFFIX=""
if [ "X$RELEASEBUILD" != "XYES" ]; then
	PVERSUFFIX=.`date "+%Y.%m%d"`
fi

# Remove old distfiles.
if [ "X$REMOVEOLDDISTFILES" = "XYES" ]; then
	rm -rf $DISTFILESDIR/libaura-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/libinstaller-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/*dfui*.????.????.tar.gz
	rm -rf $DISTFILESDIR/lua50-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/luaapp-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/luagettext-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/luafilename-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/luapty-*.????.????.tar.gz
	rm -rf $DISTFILESDIR/bsdinstaller-*.????.????.tar.gz
fi

test -d $DISTFILESDIR || mkdir $DISTFILESDIR

cd $CVSDIR/$CVSMODULE/src && \
    make clean && \
(find $CVSDIR/$CVSMODULE -name '*.core' -print0 | xargs -0 rm -f) && \
(find $CVSDIR/$CVSMODULE -name '.#*' -print0  | xargs -0 rm -f) && \
cd $CVSDIR/$CVSMODULE/ports && \
    rm -rf `find . -name 'work' -print` && \
cd $CVSDIR && \
    tar zcvf $DISTFILESDIR/bsdinstaller-${INSTALLER_VER}${PVERSUFFIX}.tar.gz --exclude CVS installer && \
cd $CVSDIR/$CVSMODULE/src/lib && \
    tar zcvf $DISTFILESDIR/libaura-${LIBAURA_VER}${PVERSUFFIX}.tar.gz --exclude CVS libaura && \
    tar zcvf $DISTFILESDIR/libdfui-${LIBDFUI_VER}${PVERSUFFIX}.tar.gz --exclude CVS libdfui && \
    tar zcvf $DISTFILESDIR/libinstaller-${LIBINSTALLER_VER}${PVERSUFFIX}.tar.gz --exclude CVS libinstaller && \
cd $CVSDIR/$CVSMODULE/src/lib/lua && \
    tar zcvf $DISTFILESDIR/luapty-${LUA50_PTY_VER}${PVERSUFFIX}.tar.gz --exclude CVS pty && \
    tar zcvf $DISTFILESDIR/luagettext-${LUA50_GETTEXT_VER}${PVERSUFFIX}.tar.gz --exclude CVS gettext && \
    tar zcvf $DISTFILESDIR/luadfui-${LUA50_DFUI_VER}${PVERSUFFIX}.tar.gz --exclude CVS dfui && \
    tar zcvf $DISTFILESDIR/luafilename-${LUA50_FILENAME_VER}${PVERSUFFIX}.tar.gz --exclude CVS filename && \
    tar zcvf $DISTFILESDIR/luaapp-${LUA50_APP_VER}${PVERSUFFIX}.tar.gz --exclude CVS app && \
cd $CVSDIR/$CVSMODULE/src/frontends && \
    tar zcvf $DISTFILESDIR/dfuife_curses-${DFUIFE_CURSES_VER}${PVERSUFFIX}.tar.gz --exclude CVS ncurses && \
    tar zcvf $DISTFILESDIR/dfuife_cgi-${DFUIFE_CGI_VER}${PVERSUFFIX}.tar.gz --exclude CVS cgi && \
    tar zcvf $DISTFILESDIR/dfuife_qt-${DFUIFE_QT_VER}${PVERSUFFIX}.tar.gz --exclude CVS qt && \
cd $CVSDIR/$CVSMODULE/src/backend && \
    tar zcvf $DISTFILESDIR/dfuibe_installer-${DFUIBE_INSTALLER_VER}${PVERSUFFIX}.tar.gz --exclude CVS installer && \
    tar zcvf $DISTFILESDIR/dfuibe_lua-${DFUIBE_LUA_VER}${PVERSUFFIX}.tar.gz --exclude CVS lua
