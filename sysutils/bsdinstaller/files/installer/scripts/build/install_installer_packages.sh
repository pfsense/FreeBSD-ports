#!/bin/sh -x

# $Id: install_installer_packages.sh,v 1.43 2005/08/29 18:21:24 cpressey Exp $
# Install packages for the installer into the ISO-to-be, using
# DragonFly's src/nrelease/Makefile.  This assumes a release (or
# quickrel etc) has already been built; it simply (re)installs pkgs.
# Note that this will "su root" for you at the right moment.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults
. $SCRIPTDIR/pver.conf

PVERSUFFIX=""
if [ "X$RELEASEBUILD" != "XYES" ]; then
	PVERSUFFIX=.`date "+%Y.%m%d"`
fi

# Build list of packages to install on ISO-to-be.

if [ "X$ONE_BIG_PKG" = "XYES" ]; then
	INSTALLER_PACKAGES="bsdinstaller-${INSTALLER_VER}${PVERSUFFIX}"
else
	INSTALLER_PACKAGES="libaura-${LIBAURA_VER}${PVERSUFFIX}
			    libdfui-${LIBDFUI_VER}${PVERSUFFIX}"
	
	# dfuibe_installer might not be installed anymore (moving to Lua.)
	if [ "X$INSTALL_DFUIBE_INSTALLER" = "XYES" ]; then
		INSTALLER_PACKAGES="${INSTALLER_PACKAGES}
				    libinstaller-${LIBINSTALLER_VER}${PVERSUFFIX}
				    dfuibe_installer-${DFUIBE_INSTALLER_VER}${PVERSUFFIX}"
	fi
	
	INSTALLER_PACKAGES="${INSTALLER_PACKAGES}
			    dfuife_curses-${DFUIFE_CURSES_VER}${PVERSUFFIX}
			    dfuife_cgi-${DFUIFE_CGI_VER}${PVERSUFFIX}
			    thttpd-notimeout-${THTTPD_NOTIMEOUT_VER}"
fi

# dfuife_qt is not installed by default, since it requires X11.
if [ "X$INSTALL_DFUIFE_QT" = "XYES" ]; then
	INSTALLER_PACKAGES="$INSTALLER_PACKAGES
			    jpeg-${JPEG_VER}
			    lcms-${LCMS_VER}
			    libmng-${LIBMNG_VER}
			    qt-${QT_VER}
			    dfuife_qt-${DFUIFE_QT_VER}${PVERSUFFIX}"
	WITH_X11="YES"
	export options_UNSET="${options_UNSET} X11 "
fi

if [ "X$WITH_X11" = "XYES" ]; then
	# Call the separate script to install the X11 packages
	# if they are not already there on the ISO-to-be.
	sh $SCRIPTDIR/install_x11_packages.sh
fi

if [ "X$WITH_NLS" = "XYES" ]; then
	INSTALLER_PACKAGES="libiconv-${LIBICONV_VER}
			    gettext-${GETTEXT_VER}
			    $INSTALLER_PACKAGES"
	if [ "X$WITH_X11" != "XYES" ]; then
		INSTALLER_PACKAGES="expat-${EXPAT_VER}
				    $INSTALLER_PACKAGES"
	fi
fi

if [ "X$ONE_BIG_PKG" != "XYES" ]; then
	if [ "X$INSTALL_DFUIBE_LUA" = "XYES" ]; then
		INSTALLER_PACKAGES="$INSTALLER_PACKAGES
				    lua50-${LUA50_VER}
				    lua50-compat51-${LUA50_COMPAT51_VER}
				    lua50-posix-${LUA50_POSIX_VER}
				    lua50-pty-${LUA50_PTY_VER}${PVERSUFFIX}
				    lua50-filename-${LUA50_FILENAME_VER}${PVERSUFFIX}
				    lua50-app-${LUA50_APP_VER}${PVERSUFFIX}
				    lua50-dfui-${LUA50_DFUI_VER}${PVERSUFFIX}
				    lua50-socket-${LUA50_SOCKET_VER}"
		if [ "X$WITH_NLS" = "XYES" ]; then
			INSTALLER_PACKAGES="$INSTALLER_PACKAGES
					    lua50-gettext-${LUA50_GETTEXT_VER}${PVERSUFFIX}"
		fi
		INSTALLER_PACKAGES="$INSTALLER_PACKAGES
				    dfuibe_lua-${DFUIBE_LUA_VER}${PVERSUFFIX}"
	fi
fi

# Remove old versions of internal packages from the ISO-to-be.
CLEAN_PACKAGES=""
for PKG in $INSTALLER_PACKAGES; do
	if echo $PKG | grep -q $PVERSUFFIX; then
		ANYPKG=`echo "$PKG" | sed 's/\\-.*$/\\-\\*/'`
		CLEAN_PACKAGES="$CLEAN_PACKAGES '$ANYPKG'"
	fi
done

# remove extraneous spacery.
CLEAN_PACKAGES=`echo $CLEAN_PACKAGES`
INSTALLER_PACKAGES=`echo $INSTALLER_PACKAGES`
EXTRA_ROOTSKELS=`echo $EXTRA_ROOTSKELS`

cd $NRELEASEDIR && \
su root -c \
    "make pkgcleaniso EXTRA_PACKAGES=\"$CLEAN_PACKAGES\" && \
     make pkgaddiso EXTRA_PACKAGES=\"$INSTALLER_PACKAGES\" && \
     make customizeiso EXTRA_ROOTSKELS=\"$ROOTSKEL $EXTRA_ROOTSKELS\""
