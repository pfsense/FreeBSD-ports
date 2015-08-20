#!/bin/sh -x

# $Id: install_x11_packages.sh,v 1.6 2005/08/29 18:21:24 cpressey Exp $
# Install X11 (X.org) packages onto the ISO-to-be using DragonFly's
# src/nrelease/Makefile.  This assumes a release (or quickrel etc)
# has already been built; it simply (re)installs packages.
# This is separate from the install_installer_packages script since
# it takes a long time and only needs to be done once (per rel.)
# Note that this generally requires root privileges.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults
. $SCRIPTDIR/pver.conf

cd $NRELEASEDIR && \
make pkgaddiso EXTRA_PACKAGES="expat-${EXPAT_VER}
			pkgconfig-${PKGCONFIG_VER}
			freetype2-${FREETYPE2_VER}
			fontconfig-${FONTCONFIG_VER}
			bitstream-vera-${BITSTREAM_VERA_VER}
			png-${PNG_VER}
	
			xorg-libraries-${XORG_LIBRARIES_VER}
			libXft-${LIBXFT_VER}
			xorg-vfbserver-${XORG_VFBSERVER_VER}
			xorg-server-${XORG_SERVER_VER}
			xorg-printserver-${XORG_PRINTSERVER_VER}
			xorg-fonts-encodings-${XORG_FONTS_ENCODINGS_VER}
			xorg-fonts-miscbitmaps-${XORG_FONTS_MISCBITMAPS_VER}
			xorg-fonts-cyrillic-${XORG_FONTS_CYRILLIC_VER}
			xorg-fonts-75dpi-${XORG_FONTS_75DPI_VER}
			xorg-fonts-100dpi-${XORG_FONTS_100DPI_VER}
			xorg-fonts-type1-${XORG_FONTS_TYPE1_VER}
			xorg-fonts-truetype-${XORG_FONTS_TRUETYPE_VER}
			xorg-documents-${XORG_DOCUMENTS_VER}
			xorg-nestserver-${XORG_NESTSERVER_VER}
			xorg-fontserver-${XORG_FONTSERVER_VER}
			xorg-clients-${XORG_CLIENTS_VER}
			rxvt-${RXVT_VER}
			dri-${DRI_VER}
			xorg-${XORG_VER}"
