#!/bin/sh -x

# $Id: copy_ports_to_portsdir.sh,v 1.4 2005/08/25 23:51:40 cpressey Exp $
# Copy the ports in our CVS tree to the system-wide ports directory.
# This script generally requires root privileges.
# create_installer_tarballs.sh should generally be run first.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults
. $SCRIPTDIR/pver.conf

PVERSUFFIX=""
if [ "X$RELEASEBUILD" != "XYES" ]; then
	PVERSUFFIX=.`date "+%Y.%m%d"`
fi

cd $CVSDIR/$CVSMODULE/ports		&& \
rm -rf */*/work				&& \
for CATEGORY in *; do
	mkdir -p $PORTSDIR/$CATEGORY
	for PORT in $CATEGORY/*; do
		if [ "X$CATEGORY" != "XCVS" -a "X$PORT" != "X$CATEGORY/CVS" ]; then
			rm -rf $PORTSDIR/$PORT
			cp -Rp $PORT $PORTSDIR/$PORT
			INTERNAL=$(make -C ${PORT} -V INTERNAL)
			if [ "${INTERNAL}" = "YES" ]; then
				sed -i '' \
					"s/^PORTVERSION=.*$/PORTVERSION=	${INSTALLER_VER}${PVERSUFFIX}/" \
				    $PORTSDIR/$PORT/Makefile
			fi
		fi
	done
done
