#!/bin/sh -x

# $Id: make_installer_image.sh,v 1.6 2005/08/27 04:01:26 cpressey Exp $
# Trivial driver script for the other four scripts.
# Generally requires root privileges.
# Assumes a 'cd /usr/src/nrelease && make realquickrel'
# (or equivalent) has recently been done.
# Can be run multiple times thereafter.

SCRIPT=`realpath $0`
SCRIPTDIR=`dirname $SCRIPT`

[ -r $SCRIPTDIR/build.conf ] && . $SCRIPTDIR/build.conf
. $SCRIPTDIR/build.conf.defaults

# For the following to work, the directories in which the
# ports, distfiles, and packages are placed must be writeable by
# the user running the script.  This is generally not the case
# for /usr/ports.  There are two general options:
#  - run as root (NOT RECOMMENDED unless maybe you're in a jail)
#  - chown -R user /usr/ports
# The last step needs to be root anyway, but will "su root" for you.

$SCRIPTDIR/create_installer_tarballs.sh && \
$SCRIPTDIR/copy_ports_to_portsdir.sh && \
$SCRIPTDIR/build_installer_packages.sh && \
$SCRIPTDIR/install_installer_packages.sh
