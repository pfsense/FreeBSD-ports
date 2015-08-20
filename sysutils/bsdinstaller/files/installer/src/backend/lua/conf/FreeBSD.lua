--
-- conf/FreeBSD.lua
-- $Id: FreeBSD.lua,v 1.8.2.1 2006/07/28 16:19:53 sullrich Exp $
--
-- This file contains FreeBSD-specific overrides to BSDInstaller.lua.
--

os = {
	name = "FreeBSD",
	version = "6.0"
}

install_items = {
	"COPYRIGHT",
	"bin",
	"boot",
	"compat",	-- XXX not sure about this.
	"dev",
	"dist",
	"entropy",
	"etc",		-- XXX { src = "etc.hdd", dest = "etc" },
	"lib",
	"libexec",
	"rescue",
	"root",
	"sbin",
	"sys",		-- XXX What's the deal with this anyway?
	"usr/bin",
	"usr/games",
	"usr/include",
	"usr/lib",
--	"usr/local",	-- No need to copy these two, since we use mtree to
--	"usr/X11R6",	-- make them and pkg_add to populate them with files.
	"usr/libdata",
	"usr/libexec",
	"usr/sbin",
	"usr/share",
	"usr/src",
	"var"
}

cmd_names = cmd_names + {
	DISKLABEL = "sbin/bsdlabel",
	CPDUP = "usr/local/bin/cpdup -vvv -I",
	DHCPD = "usr/local/sbin/dhcpd",
	RPCBIND = "usr/sbin/rpcbind",
	MOUNTD = "usr/sbin/mountd",
	NFSD = "usr/sbin/nfsd",
	MODULES_DIR = "boot/kernel"
}

sysids = {
	{ "FreeBSD",		165 },
	{ "OpenBSD",		166 },
	{ "NetBSD",		169 },
	{ "MS-DOS",		 15 },
	{ "Linux",		131 },
	{ "Plan9",		 57 }
}

default_sysid = 165
package_suffix = "tbz"
num_subpartitions = 8
has_raw_devices = false
disklabel_on_disk = false
has_softupdates = true
window_subpartitions = { "c" }
use_cpdup = false

--
-- Offlimits mount points.  BSDInstaller will ignore these mount points
--
-- example: offlimits_mounts  = { "unionfs" }
offlimits_mounts = { }

