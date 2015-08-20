--
-- conf/NetBSD.lua
-- $Id: NetBSD.lua,v 1.8.2.1 2006/07/28 16:19:53 sullrich Exp $
--
-- This file contains NetBSD-specific overrides to conf/BSDInstaller.lua.
--

os = {
	name = "NetBSD",
	version = "2.0.2"
}

cmd_names = cmd_names + {
	CPDUP = "usr/pkg/bin/cpdup -vvv -I",
	SYSCTL_DISKS = "hw.disknames"
}

install_items = {
	"altroot",
	"bin",
	"boot",
	"dev",
	"etc", -- XXX { src = "etc.hdd", dest = "etc" },
	"libexec",
	"lib",
	"netbsd",
	"rescue",
	"root",
	"sbin",
	"stand",
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
	"var"
}

mtrees_post_copy = {}	-- none

mount_info_regexp = "^([^%s]+)%s+on%s+([^%s]+)%s+type%s+([^%s]+)"

sysids = {
	{ "NetBSD",		169 },
	{ "DragonFly/FreeBSD",	165 },
	{ "OpenBSD",		166 },
	{ "MS-DOS",		 15 },
	{ "Linux",		131 },
	{ "Plan9",		 57 }
}

default_sysid = 169
num_subpartitions = 16
has_raw_devices = true
disklabel_on_disk = true
has_softupdates = false
window_subpartitions = { "c", "d" }
enable_crashdumps = false
use_cpdup = false

--
-- Offlimits mount points.  BSDInstaller will ignore these mount points
--
-- example: offlimits_mounts  = { "unionfs" }
offlimits_mounts = { }
