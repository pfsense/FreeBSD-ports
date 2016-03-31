# Created by: Chris Pressey <cpressey@catseye.mine.nu>
# $FreeBSD$

PORTNAME=	bsdinstaller
PORTVERSION=	2.0.12
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	cpressey@catseye.mine.nu
COMMENT=	BSD Installer mega-package

WRKSRC=		${WRKDIR}/installer/src
MAKE_JOBS_UNSAFE=yes

OPTIONS_DEFINE=		NLS CGI CURSES C_BACKEND LUA_BACKEND
OPTIONS_DEFAULT=	NLS CURSES LUA_BACKEND

USE_LDCONFIG=	yes

# Used by copy script
INTERNAL=	YES

.include <bsd.port.options.mk>

.if ${PORT_OPTIONS:MNLS}
USES+=		gettext

# XXX Not sure if this should be LOCALBASE or PREFIX... probably LOCALBASE?
LOCALEDIR=	${LOCALBASE}/share/locale
LANGUAGES=	ru
ALL_TARGET=	nls
.else
MAKE_ARGS+=	LOCALEDIR="" NLS_CFLAGS="" NLS_LDFLAGS=""
.endif

DFUIBE_LUA_DIR=	${STAGEDIR}${PREFIX}/share/dfuibe_lua

# Make it so that we don't have to build the parts we're not installing...
MAKE_ARGS+=	BUILD_QT_FRONTEND=false

.if ${PORT_OPTIONS:MCGI}
MAKE_ARGS+=	BUILD_CGI_FRONTEND=true
.else
MAKE_ARGS+=	BUILD_CGI_FRONTEND=false
.endif

.if ${PORT_OPTIONS:MCURSES}
MAKE_ARGS+=	BUILD_NCURSES_FRONTEND=true
.else
MAKE_ARGS+=	BUILD_NCURSES_FRONTEND=false
.endif

.if ${PORT_OPTIONS:MC_BACKEND}
MAKE_ARGS+=	BUILD_C_BACKEND=true
.else
MAKE_ARGS+=	BUILD_C_BACKEND=false
.endif

.if ${PORT_OPTIONS:MLUA_BACKEND}
MAKE_ARGS+=	BUILD_LUA_BACKEND=true
.else
MAKE_ARGS+=	BUILD_LUA_BACKEND=false
.endif

# Don't strip executables if this is being built as a "debuggable" package.
.if defined(DEBUG) || defined(WITH_DEBUG) || defined(WITH_DEBUG_INFO) || defined(EXTRA_CFLAGS)
STRIP=
.endif

###### Packing list.
# pfSense
PLIST_FILES+=	/scripts/lua_installer \
		/scripts/pfi \
		bin/after_installation_routines.sh \
		share/dfuibe_lua/150_easy_install.lua \
		share/dfuibe_lua/300_rescueconfig.lua \
		share/dfuibe_lua/conf/build.conf \
		share/dfuibe_lua/conf/pfSense.lua \
		share/dfuibe_lua/conf/pfSense_rescue.lua \
		share/dfuibe_lua/easy_install/100_confirm_basic_install.lua \
		share/dfuibe_lua/easy_install/200_select_disk.lua \
		share/dfuibe_lua/easy_install/220_format_disk.lua \
		share/dfuibe_lua/easy_install/250_partition_disk.lua \
		share/dfuibe_lua/easy_install/300_select_part.lua \
		share/dfuibe_lua/easy_install/400_select_subparts.lua \
		share/dfuibe_lua/easy_install/407_create_subparts.lua \
		share/dfuibe_lua/easy_install/420_preselect_packages.lua \
		share/dfuibe_lua/easy_install/500_install_os.lua \
		share/dfuibe_lua/easy_install/597_install_kernel.lua \
		share/dfuibe_lua/easy_install/598_install_bootblocks.lua \
		share/dfuibe_lua/easy_install/599_after_installation_tasks.lua \
		share/dfuibe_lua/easy_install/900_reboot.lua \
		share/dfuibe_lua/easy_install/main.lua \
		share/dfuibe_lua/install/597_install_kernel.lua \
		share/dfuibe_lua/install/599_after_installation_tasks.lua

PLIST_DIRSTRY+=	/scripts

# libaura
PLIST_FILES+=	lib/libaura.a			\
		lib/libaura.so			\
		lib/libaura.so.3		\
		include/aura/buffer.h		\
		include/aura/dict.h		\
		include/aura/fspred.h		\
		include/aura/popen.h		\
		include/aura/mem.h

# libdfui
PLIST_FILES+=	lib/libdfui.a			\
		lib/libdfui.so			\
		lib/libdfui.so.4		\
		include/dfui/dfui.h		\
		include/dfui/dump.h		\
		include/dfui/lang.h		\
		include/dfui/system.h

# libinstaller
.if ${PORT_OPTIONS:MC_BACKEND}
PLIST_FILES+=	lib/libinstaller.a		\
		lib/libinstaller.so		\
		lib/libinstaller.so.5		\
		include/installer/commands.h	\
		include/installer/confed.h	\
		include/installer/diskutil.h	\
		include/installer/functions.h	\
		include/installer/package.h	\
		include/installer/uiutil.h
.endif

# dfuife_curses
.if ${PORT_OPTIONS:MCURSES}
PLIST_FILES+=	sbin/dfuife_curses	\
		share/dfuife_curses/fred.txt
.if ${PORT_OPTIONS:MNLS}
#PLIST_FILES+=	share/locale/ru/LC_MESSAGES/dfuife_curses.mo
.endif
.endif

# dfuife_cgi
.if ${PORT_OPTIONS:MCGI}
PLIST_FILES+=	www/dfuife.cgi		\
		www/dfly-pg.gif		\
		www/fred-bg.gif		\
		www/fred.png		\
		www/dfuife.css		\
		www/dfuife.js		\
		www/index.html
.endif

# dfuibe_installer
.if ${PORT_OPTIONS:MC_BACKEND}
PLIST_FILES+=	sbin/dfuibe_installer \
		share/dfuibe_installer/cmdnames.conf
.if ${PORT_OPTIONS:MNLS}
PLIST_FILES+=	share/locale/ru/LC_MESSAGES/dfuibe_installer.mo
.endif
.endif

# lua50 - maybe should go into libexec.
.if ${PORT_OPTIONS:MLUA_BACKEND}
PLIST_FILES+=	lib/liblua50.so		\
		lib/liblualib50.so	\
		bin/lua50		\
		bin/lua50c

# lua50-compat5.1
PLIST_FILES+=	share/lua/5.0/compat-5.1.lua		\
		lib/lua/${DISTNAME}/compat-5.1.c	\
		lib/lua/${DISTNAME}/compat-5.1.h	\
		bin/lua50c51

# lua50-pty
PLIST_FILES+=	lib/lua/5.0/lpty.so \
		share/lua/5.0/pty.lua

# lua50-app
PLIST_FILES+=	share/lua/5.0/app.lua

# lua50-gettext
.if ${PORT_OPTIONS:MNLS}
PLIST_FILES+=	lib/lua/5.0/lgettext.so \
		share/lua/5.0/gettext.lua
.endif

# lua50-filename
PLIST_FILES+=	share/lua/5.0/filename.lua

# lua50-posix
PLIST_FILES+=	lib/lua/5.0/lposix.so	\
		share/lua/5.0/posix.lua

# lua50-dfui
PLIST_FILES+=	lib/lua/5.0/ldfui.so \
		share/lua/5.0/dfui.lua

# lua50-luasocket
PLIST_FILES+=	lib/lua/5.0/lsocket.so		\
		lib/lua/5.0/lmime.so		\
		share/lua/5.0/ftp.lua		\
		share/lua/5.0/http.lua		\
		share/lua/5.0/ltn12.lua		\
		share/lua/5.0/mime.lua		\
		share/lua/5.0/smtp.lua		\
		share/lua/5.0/socket.lua	\
		share/lua/5.0/tp.lua		\
		share/lua/5.0/url.lua

# lua50-transceiver
PLIST_FILES+=	share/lua/5.0/transceiver.lua

# lua50-curses
.if ${PORT_OPTIONS:MCURSES}
PLIST_FILES+=	lib/lua/5.0/lcurses.so \
		share/lua/5.0/curses.lua
.endif
# dfuibe_lua
PLIST_FILES+=	share/dfuibe_lua/main.lua \
		share/dfuibe_lua/200_install.lua \
		share/dfuibe_lua/400_configure.lua \
		share/dfuibe_lua/500_create_geom_mirror.lua \
		share/dfuibe_lua/600_upgrade.lua \
		share/dfuibe_lua/800_netboot.lua \
		share/dfuibe_lua/950_reboot.lua \
		share/dfuibe_lua/lib/bitwise.lua \
		share/dfuibe_lua/lib/capacity.lua \
		share/dfuibe_lua/lib/cmdchain.lua \
		share/dfuibe_lua/lib/configfile.lua \
		share/dfuibe_lua/lib/configvars.lua \
		share/dfuibe_lua/lib/disk.lua \
		share/dfuibe_lua/lib/flow.lua \
		share/dfuibe_lua/lib/flowstep.lua \
		share/dfuibe_lua/lib/installation.lua \
		share/dfuibe_lua/lib/installationui.lua \
		share/dfuibe_lua/lib/menu.lua \
		share/dfuibe_lua/lib/menuitem.lua \
		share/dfuibe_lua/lib/navcontainer.lua \
		share/dfuibe_lua/lib/navleaf.lua \
		share/dfuibe_lua/lib/network.lua \
		share/dfuibe_lua/lib/network_ui.lua \
		share/dfuibe_lua/lib/networkui.lua \
		share/dfuibe_lua/lib/package.lua \
		share/dfuibe_lua/lib/packagegraph.lua \
		share/dfuibe_lua/lib/packagelist.lua \
		share/dfuibe_lua/lib/packageset.lua \
		share/dfuibe_lua/lib/packageui.lua \
		share/dfuibe_lua/lib/package_ui.lua \
		share/dfuibe_lua/lib/partition.lua \
		share/dfuibe_lua/lib/storage.lua \
		share/dfuibe_lua/lib/storage_ui.lua \
		share/dfuibe_lua/lib/storagesystem.lua \
		share/dfuibe_lua/lib/storagesystemui.lua \
		share/dfuibe_lua/lib/storageui.lua \
		share/dfuibe_lua/lib/subpartition.lua \
		share/dfuibe_lua/lib/target_system.lua \
		share/dfuibe_lua/lib/target_system_ui.lua \
		share/dfuibe_lua/lib/uinav.lua \
		share/dfuibe_lua/conf/BSDInstaller.lua \
		share/dfuibe_lua/conf/DragonFly.lua \
		share/dfuibe_lua/conf/FreeBSD.lua \
		share/dfuibe_lua/conf/NetBSD.lua \
		share/dfuibe_lua/conf/OpenBSD.lua \
		share/dfuibe_lua/pit/main.lua \
		share/dfuibe_lua/pit/050_welcome.lua \
		share/dfuibe_lua/pit/100_select_language.lua \
		share/dfuibe_lua/pit/150_configure_console.lua \
		share/dfuibe_lua/pit/400_load_kernel_modules.lua \
		share/dfuibe_lua/pit/800_configure_network.lua \
		share/dfuibe_lua/install/main.lua \
		share/dfuibe_lua/install/100_welcome.lua \
		share/dfuibe_lua/install/200_select_disk.lua \
		share/dfuibe_lua/install/220_format_disk.lua \
		share/dfuibe_lua/install/250_partition_disk.lua \
		share/dfuibe_lua/install/270_install_bootblocks.lua \
		share/dfuibe_lua/install/300_select_part.lua \
		share/dfuibe_lua/install/400_select_subparts.lua \
		share/dfuibe_lua/install/405_warn_omitted_subparts.lua \
		share/dfuibe_lua/install/407_create_subparts.lua \
		share/dfuibe_lua/install/410_select_additional_filesystems.lua \
		share/dfuibe_lua/install/420_preselect_packages.lua \
		share/dfuibe_lua/install/450_select_packages.lua \
		share/dfuibe_lua/install/490_confirm_install_os.lua \
		share/dfuibe_lua/install/500_install_os.lua \
		share/dfuibe_lua/install/800_finished.lua \
		share/dfuibe_lua/install/900_reboot.lua \
		share/dfuibe_lua/configure/main.lua \
		share/dfuibe_lua/configure/100_choose_where_from.lua \
		share/dfuibe_lua/configure/200_select_disk.lua \
		share/dfuibe_lua/configure/300_select_part.lua \
		share/dfuibe_lua/configure/400_mount_target_system.lua \
		share/dfuibe_lua/configure/450_init_configuration.lua \
		share/dfuibe_lua/configure/500_menu.lua \
		share/dfuibe_lua/configure/550_write_configuration.lua \
		share/dfuibe_lua/configure/600_unmount_target_system.lua \
		share/dfuibe_lua/configure/menu/main.lua \
		share/dfuibe_lua/configure/menu/100_set_root_password.lua \
		share/dfuibe_lua/configure/menu/200_add_user.lua \
		share/dfuibe_lua/configure/menu/300_set_timezone.lua \
		share/dfuibe_lua/configure/menu/350_set_datetime.lua \
		share/dfuibe_lua/configure/menu/400_change_console_settings.lua \
		share/dfuibe_lua/configure/menu/500_install_packages.lua \
		share/dfuibe_lua/configure/menu/550_remove_packages.lua \
		share/dfuibe_lua/upgrade/main.lua \
		share/dfuibe_lua/upgrade/100_begin_upgrade.lua \
		share/dfuibe_lua/upgrade/200_select_disk.lua \
		share/dfuibe_lua/upgrade/300_select_part.lua \
		share/dfuibe_lua/upgrade/400_mount_target_system.lua \
		share/dfuibe_lua/upgrade/500_perform_upgrade.lua \
		share/dfuibe_lua/upgrade/550_upgrade_configuration.lua \
		share/dfuibe_lua/upgrade/600_unmount_target_system.lua \
		share/dfuibe_lua/netboot/main.lua \
		share/dfuibe_lua/netboot/500_setup_server.lua
.if ${PORT_OPTIONS:MNLS}
#PLIST_FILES+=	share/locale/ru/LC_MESSAGES/dfuibe_lua.mo
.endif
.endif # WITH_LUA

PLIST_DIRSTRY+=	include/aura \
		include/dfui \
		lib/lua/5.0 \
		lib/lua/bsdinstaller-${PORTVERSION} \
		lib/lua \
		share/dfuibe_lua/conf \
		share/dfuibe_lua/configure/menu \
		share/dfuibe_lua/configure \
		share/dfuibe_lua/install \
		share/dfuibe_lua/lib \
		share/dfuibe_lua/netboot \
		share/dfuibe_lua/pit \
		share/dfuibe_lua/upgrade \
		share/dfuibe_lua \
		share/dfuife_curses \
		share/lua/5.0 \
		share/lua

.include <bsd.port.pre.mk>

.if ${ARCH} == "amd64"
CFLAGS+=	-fPIC
.endif

##### Extract

do-extract:
	${MKDIR} ${WRKDIR}
	${CP} -r ${FILESDIR}/installer ${WRKDIR}

##### Install

do-install:
	echo "Installing libaura..."
	${MKDIR} ${STAGEDIR}${PREFIX}/include/aura
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/libaura.a ${STAGEDIR}${PREFIX}/lib/
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/libaura.so.3 ${STAGEDIR}${PREFIX}/lib/
	${LN} -sf ${PREFIX}/lib/libaura.so.3 ${STAGEDIR}${PREFIX}/lib/libaura.so
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/buffer.h ${STAGEDIR}${PREFIX}/include/aura/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/dict.h ${STAGEDIR}${PREFIX}/include/aura/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/popen.h ${STAGEDIR}${PREFIX}/include/aura/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/fspred.h ${STAGEDIR}${PREFIX}/include/aura/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libaura/mem.h ${STAGEDIR}${PREFIX}/include/aura/
	echo "Installing libdfui..."
	${MKDIR} ${STAGEDIR}${PREFIX}/include/dfui
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/libdfui.a ${STAGEDIR}${PREFIX}/lib/
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/libdfui.so.4 ${STAGEDIR}${PREFIX}/lib/
	${LN} -sf ${PREFIX}/lib/libdfui.so.4 ${STAGEDIR}${PREFIX}/lib/libdfui.so
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/dfui.h ${STAGEDIR}${PREFIX}/include/dfui/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/dump.h ${STAGEDIR}${PREFIX}/include/dfui/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/lang.h ${STAGEDIR}${PREFIX}/include/dfui/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libdfui/system.h ${STAGEDIR}${PREFIX}/include/dfui/
.if ${PORT_OPTIONS:MC_BACKEND}
	echo "Installing libinstaller..."
	${MKDIR} ${STAGEDIR}${PREFIX}/include/installer
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/libinstaller.a ${STAGEDIR}${PREFIX}/lib/
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/libinstaller.so.5 ${STAGEDIR}${PREFIX}/lib/
	${LN} -sf ${PREFIX}/lib/libinstaller.so.5 ${STAGEDIR}${PREFIX}/lib/libinstaller.so
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/commands.h ${STAGEDIR}${PREFIX}/include/installer/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/confed.h ${STAGEDIR}${PREFIX}/include/installer/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/diskutil.h ${STAGEDIR}${PREFIX}/include/installer/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/functions.h ${STAGEDIR}${PREFIX}/include/installer/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/package.h ${STAGEDIR}${PREFIX}/include/installer/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/libinstaller/uiutil.h ${STAGEDIR}${PREFIX}/include/installer/
.endif
.if ${PORT_OPTIONS:MCURSES}
	echo "Installing dfuife_curses..."
	${MKDIR} ${STAGEDIR}${PREFIX}/share/dfuife_curses
	${INSTALL_PROGRAM} -o root -g wheel ${WRKSRC}/frontends/ncurses/dfuife_curses ${STAGEDIR}${PREFIX}/sbin/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/frontends/ncurses/fred.txt ${STAGEDIR}${PREFIX}/share/dfuife_curses/
.if ${PORT_OPTIONS:MNLS}
	for _language in ${LANGUAGES}; do \
		${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/frontends/ncurses/po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/dfuife_curses.mo; \
	done
.endif
.endif
.if ${PORT_OPTIONS:MCGI}
	echo "Installing dfuife_cgi..."
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${INSTALL_PROGRAM} ${WRKSRC}/frontends/cgi/dfuife.cgi ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/dfly-pg.gif ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/fred-bg.gif ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/fred.png ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/dfuife.css ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/dfuife.js ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}/frontends/cgi/index.html ${STAGEDIR}${PREFIX}/www
.endif
.if ${PORT_OPTIONS:MC_BACKEND}
	echo "Installing dfuibe_installer..."
	${INSTALL_PROGRAM} -s -o root -g wheel -m 555 ${WRKSRC}/backend/installer/dfuibe_installer ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/share/dfuibe_installer
	${INSTALL_DATA} -o root -g wheel -m 555 ${WRKSRC}/backend/installer/conf/cmdnames.conf ${STAGEDIR}${PREFIX}/share/dfuibe_installer
.if ${PORT_OPTIONS:MNLS}
	for _language in ${LANGUAGES}; do \
		${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/installer/po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/dfuibe_installer.mo; \
	done
.endif
.endif
.if ${PORT_OPTIONS:MLUA_BACKEND}
	echo "Installing lua50..."
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/platform/lua/lib/liblua50.so ${STAGEDIR}${PREFIX}/lib/
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/platform/lua/lib/liblualib50.so ${STAGEDIR}${PREFIX}/lib/
	${INSTALL_PROGRAM} -o root -g wheel -m 555 ${WRKSRC}/platform/lua/bin/lua ${STAGEDIR}${PREFIX}/bin/lua50
	${INSTALL_PROGRAM} -o root -g wheel -m 555 ${WRKSRC}/platform/lua/bin/luac ${STAGEDIR}${PREFIX}/bin/lua50c
	echo "Installing lua50-compat5.1..."
	${MKDIR} ${STAGEDIR}${PREFIX}/lib/lua/5.0
	${MKDIR} ${STAGEDIR}${PREFIX}/lib/lua/${DISTNAME}
	${MKDIR} ${STAGEDIR}${PREFIX}/share/lua/5.0
	${INSTALL_DATA} ${WRKSRC}/lib/lua/compat-5.1/compat-5.1.lua ${STAGEDIR}${PREFIX}/share/lua/5.0
	${INSTALL_DATA} ${WRKSRC}/lib/lua/compat-5.1/compat-5.1.c ${STAGEDIR}${PREFIX}/lib/lua/${DISTNAME}
	${INSTALL_DATA} ${WRKSRC}/lib/lua/compat-5.1/compat-5.1.h ${STAGEDIR}${PREFIX}/lib/lua/${DISTNAME}
	${INSTALL_SCRIPT} ${WRKSRC}/lib/lua/compat-5.1/lua50c51.sh ${STAGEDIR}${PREFIX}/bin/lua50c51
	echo "Installing lua50-POSIX..."
	${INSTALL_DATA} ${WRKSRC}/lib/lua/posix/lposix.so ${STAGEDIR}${PREFIX}/lib/lua/5.0/
	${INSTALL_DATA} ${WRKSRC}/lib/lua/posix/posix.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
	echo "Installing lua50-pty..."
	${INSTALL_DATA} ${WRKSRC}/lib/lua/pty/lpty.so ${STAGEDIR}${PREFIX}/lib/lua/5.0
	${INSTALL_DATA} ${WRKSRC}/lib/lua/pty/pty.lua ${STAGEDIR}${PREFIX}/share/lua/5.0
	echo "Installing lua50-filename..."
	${INSTALL_DATA} ${WRKSRC}/lib/lua/filename/filename.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/filename.lua
	echo "Installing lua50-app..."
	${INSTALL_DATA} ${WRKSRC}/lib/lua/app/app.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/app.lua
	echo "Installing lua50-dfui..."
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/lib/lua/dfui/ldfui.so ${STAGEDIR}${PREFIX}/lib/lua/5.0/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/lua/dfui/dfui.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
	echo "Installing lua50-luasocket..."
	${INSTALL_PROGRAM} ${WRKSRC}/lib/lua/luasocket/lsocket.so ${STAGEDIR}${PREFIX}/lib/lua/5.0/
	${INSTALL_PROGRAM} ${WRKSRC}/lib/lua/luasocket/lmime.so ${STAGEDIR}${PREFIX}/lib/lua/5.0/
	${INSTALL_DATA} ${WRKSRC}/lib/lua/luasocket/lua/*.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
	echo "Installing lua50-transceiver..."
	${INSTALL_DATA} ${WRKSRC}/lib/lua/transceiver/transceiver.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
.if ${PORT_OPTIONS:MCURSES}
	echo "Installing lua50-lcurses..."
	${INSTALL_PROGRAM} ${WRKSRC}/lib/lua/lcurses/lcurses.so ${STAGEDIR}${PREFIX}/lib/lua/5.0
	${INSTALL_DATA} ${WRKSRC}/lib/lua/lcurses/curses.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
.endif
.if ${PORT_OPTIONS:MNLS}
	echo "Installing lua50-gettext..."
	${INSTALL_PROGRAM} -o root -g wheel -m 444 ${WRKSRC}/lib/lua/gettext/lgettext.so ${STAGEDIR}${PREFIX}/lib/lua/5.0/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/lib/lua/gettext/gettext.lua ${STAGEDIR}${PREFIX}/share/lua/5.0/
.endif
	echo "Installing dfuibe_lua..."
	${MKDIR} ${DFUIBE_LUA_DIR}
	${MKDIR} ${DFUIBE_LUA_DIR}/lib
	${MKDIR} ${DFUIBE_LUA_DIR}/conf
	${MKDIR} ${DFUIBE_LUA_DIR}/pit
	${MKDIR} ${DFUIBE_LUA_DIR}/install
	${MKDIR} ${DFUIBE_LUA_DIR}/configure
	${MKDIR} ${DFUIBE_LUA_DIR}/configure/menu
	${MKDIR} ${DFUIBE_LUA_DIR}/upgrade
	${MKDIR} ${DFUIBE_LUA_DIR}/netboot
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/*.lua ${DFUIBE_LUA_DIR}/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/lib/*.lua ${DFUIBE_LUA_DIR}/lib/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/conf/*.lua ${DFUIBE_LUA_DIR}/conf/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/pit/*.lua ${DFUIBE_LUA_DIR}/pit/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/install/*.lua ${DFUIBE_LUA_DIR}/install/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/configure/*.lua ${DFUIBE_LUA_DIR}/configure/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/configure/menu/*.lua ${DFUIBE_LUA_DIR}/configure/menu/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/upgrade/*.lua ${DFUIBE_LUA_DIR}/upgrade/
	${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/netboot/*.lua ${DFUIBE_LUA_DIR}/netboot/
.if ${PORT_OPTIONS:MNLS}
	for _language in ${LANGUAGES}; do \
		${INSTALL_DATA} -o root -g wheel -m 444 ${WRKSRC}/backend/lua/po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/dfuibe_lua.mo; \
	done
.endif
.endif # WITH_LUA_BACKEND
	# Install pfSense files
	${TAR} -cf - -C ${FILESDIR}/pfSense/ . | ${TAR} -xf - -C ${STAGEDIR}

.include <bsd.port.post.mk>
