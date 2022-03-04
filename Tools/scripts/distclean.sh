#!/bin/sh
#
# distclean
# Compare distfiles in /usr/ports/distfiles
# with currently instaled ports collection
# and removes outdated files
#
# ----------------------------------------------------------------------------
# "THE BEER-WARE LICENSE" (Revision 42, (c) Poul-Henning Kamp):
# Maxim Sobolev <sobomax@FreeBSD.org> wrote this file.  As long as you retain
# this notice you can do whatever you want with this stuff. If we meet some
# day, and you think this stuff is worth it, you can buy me a beer in return.
#
# Maxim Sobolev
# ----------------------------------------------------------------------------
#
# MAINTAINER= ports@FreeBSD.org

PATH=/sbin:/bin:/usr/bin

cleanup() {
	rm -f $FN_RESULTS_SCRIPT $FN_PORTS $FN_DISTFILES
	echo "Terminated."
	exit 1
}

PORTSDIR=${PORTSDIR:-/usr/ports}
if [ ! -d ${PORTSDIR} ]; then
	echo "Ports directory ${PORTSDIR} does not exist."
	exit 1
fi

DISTDIR=`(make -V DISTDIR -f ${PORTSDIR}/Mk/bsd.port.mk) 2>/dev/null`
DISTDIR=${DISTDIR:-/usr/ports/distfiles}

echo "Assumes that your ports are in ${PORTSDIR} and distfiles in ${DISTDIR}."
echo ""

if [ x"$1" = x"-f" ]; then
	RM_FLAG="-f"	
else
	RM_FLAG="-i"
	echo "Use \"`basename $0` -f\" to remove the files without prompting for confirmation."
	echo ""
fi

umask 077

FN_PORTS=`mktemp -t dclean` || exit 1
FN_DISTFILES=`mktemp -t dclean` || exit 1
FN_RESULTS_SCRIPT=`mktemp -t dclean` || exit 1

trap cleanup 1 2 3 4 5 6 7 8 10 11 12 13 14 15 16 21 22 23 24 25 26 27 28 \
	     30 31

echo -n "Building ports sha256 index..."
find ${PORTSDIR}/ \
    \( -name "distinfo" -or -name "distinfo.i386" -or -name "distinfo.alpha" \) \
    -type f -mindepth 3 -maxdepth 3 | \
    xargs cat | grep '^SHA256 ('| sort -u > $FN_PORTS
echo "Done."
P_SHA256_COUNT=`wc -l $FN_PORTS | sed "s| $FN_PORTS|| ; s| ||g"`
echo "Found $P_SHA256_COUNT sha256 entries in your ports directory."

echo -n "Building distfiles sha256 index..."
find ${DISTDIR}/ -type f | xargs sha256 | sed 's|'${DISTDIR}'/||' | sort > $FN_DISTFILES
echo "Done."
D_SHA256_COUNT=`wc -l $FN_DISTFILES | sed "s| $FN_DISTFILES|| ; s| ||g"`
echo "Found $D_SHA256_COUNT distfile(s) in your distfiles directory."

echo -n "Comparing results..."
diff -d $FN_DISTFILES $FN_PORTS | grep "^<" | sed 's|.*(|rm '$RM_FLAG' '${DISTDIR}'/| ; s|).*||' > $FN_RESULTS_SCRIPT
echo "Done."
R_SHA256_COUNT=`wc -l $FN_RESULTS_SCRIPT | sed "s| $FN_RESULTS_SCRIPT|| ; s| ||g"`
echo "$R_SHA256_COUNT distfile(s) doesn't have corresponding sha256 entries in ports directory."
/bin/sh $FN_RESULTS_SCRIPT
find ${DISTDIR}/ -type d -empty -delete

echo -n "Finishing..."
rm -f $FN_RESULTS_SCRIPT $FN_PORTS $FN_DISTFILES
echo "Done."
