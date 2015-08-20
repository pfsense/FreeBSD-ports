#!/bin/sh -x

# $Id: build_installer_packages.sh,v 1.40.2.1 2010/09/14 15:59:35 sullrich Exp $
# Build packages for BSD Installer components.
# This script will "su root" to remove old packages from the system.
# copy_ports_to_portsdir.sh should generally be run first.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults
. $SCRIPTDIR/pver.conf

PVERSUFFIX=""
if [ "X$RELEASEBUILD" != "XYES" ]; then
	PVERSUFFIX=.`date "+%Y.%m%d"`
fi

INSTALL_DFUIFE_QT=${INSTALL_DFUIFE_QT:-NO}	# build & install Qt frontend
INSTALL_DFUIBE_LUA=${INSTALL_DFUIBE_LUA:-NO}	# build & install Lua backend
INSTALL_DFUIBE_INSTALLER=${INSTALL_DFUIBE_INSTALLER:-YES} # ditto C backend

export options_UNSET="${options_UNSET} NLS X11 "
WITH_NLS=${WITH_NLS:-NO}			# build pkgs with i18n
WITH_X11=${WITH_X11:-NO}			# build X11 support pkgs


export options_SET="${options_SET} CURSES CGI "
WITH_CURSES_DEF="WITH_CURSES=YES"
WITH_CGI_DEF="WITH_CGI=YES"

WITH_LUA_BACKEND_DEF=""
if [ "X$INSTALL_DFUIBE_LUA" = "XYES" ]; then
	export options_SET="${options_SET} LUA_BACKEND "
	WITH_LUA_BACKEND_DEF="WITH_LUA_BACKEND=YES"
fi

WITH_C_BACKEND_DEF=""
if [ "X$INSTALL_DFUIBE_INSTALLER" = "XYES" ]; then
	export options_SET="${options_SET} C_BACKEND "
	WITH_C_BACKEND_DEF="WITH_C_BACKEND=YES"
fi

WITH_NLS_DEF=""
if [ "X$WITH_NLS" = "XYES" ]; then
	export options_SET="${options_SET} NLS "
	WITH_NLS_DEF="WITH_NLS=YES"
fi

WITH_DEBUG_DEF=""
if [ "X$WITH_DEBUG" = "XYES" ]; then
	WITH_DEBUG_DEF="EXTRA_CFLAGS='-g -DDEBUG'"
fi

WITH_DEBUG_INFO_DEF=""
if [ "X$WITH_DEBUG_INFO" = "XYES" ]; then
	WITH_DEBUG_DEF="EXTRA_CFLAGS='-g'"
fi

rebuild_port()
{
	cd $PORTSDIR/$1/$2/			&& \
	rm -rf work distinfo			&& \
	make makesum				&& \
	make -DBATCH patch			&& \
	chmod -R 777 work			&& \
	make -DBATCH WITHOUT="${options_UNSET}" WITH="${options_SET}" $WITH_NLS_DEF $WITH_CURSES_DEF $WITH_CGI_DEF $WITH_QT_DEF \
	     $WITH_LUA_BACKEND_DEF $WITH_C_BACKEND_DEF $WITH_DEBUG_DEF \
	     $PORTS_FLAGS build package deinstall install FORCE_PKG_REGISTER=yes && \
	make clean && rm -rf work
}

if [ -n "${FREEBSD_VERSION}" -a "${FREEBSD_VERSION}" -lt 9 ]; then
	su root -c \
	"pkg_delete -f 'libaura-*'
	pkg_delete -f 'libinstaller-*'
	pkg_delete -f '*dfui*'
	pkg_delete -f 'thttpd-notimeout-*'
	pkg_delete -f 'lua50-*'
	pkg_delete -f 'bsdinstaller-*'"
else
	su root -c \
	"pkg delete -yf 'libaura-*'
	pkg delete -yf 'libinstaller-*'
	pkg delete -yf '*dfui*'
	pkg delete -yf 'thttpd-notimeout-*'
	pkg delete -yf 'lua50-*'
	pkg delete -yf 'bsdinstaller-*'"
fi

if [ "X$REMOVEOLDPKGS" = "XYES" ]; then
	rm -rf $PACKAGESDIR/libaura-*.????.????.t?z
	rm -rf $PACKAGESDIR/libinstaller-*.????.????.t?z
	rm -rf $PACKAGESDIR/*dfui*.????.????.t?z
	rm -rf $PACKAGESDIR/lua50-*.????.????.t?z
	rm -rf $PACKAGESDIR/bsdinstaller-*.????.????.t?z
fi

# Now, rebuild all the ports, making packages in the process.

if [ "X$ONE_BIG_PKG" = "XYES" ]; then
	rebuild_port sysutils bsdinstaller
else
	rebuild_port devel libaura			&& \
	rebuild_port sysutils libdfui			&& \
	if [ "X$INSTALL_DFUIBE_INSTALLER" = "XYES" ]; then
		rebuild_port sysutils libinstaller	&& \
		rebuild_port sysutils dfuibe_installer
	fi						&& \
	rebuild_port sysutils dfuife_curses		&& \
	rebuild_port sysutils dfuife_cgi		&& \
	rebuild_port www thttpd-notimeout		&& \
	if [ "X$INSTALL_DFUIFE_QT" = "XYES" ]; then
		rebuild_port sysutils dfuife_qt
	fi						&& \
	if [ "X$INSTALL_DFUIBE_LUA" = "XYES" ]; then
		rebuild_port lang lua50			&& \
		rebuild_port devel lua50-compat51	&& \
		rebuild_port devel lua50-posix		&& \
		rebuild_port devel lua50-pty		&& \
		rebuild_port devel lua50-gettext	&& \
		rebuild_port devel lua50-dfui		&& \
		rebuild_port devel lua50-filename	&& \
		rebuild_port devel lua50-app		&& \
		rebuild_port net lua50-socket		&& \
		rebuild_port sysutils dfuibe_lua
	fi
fi
