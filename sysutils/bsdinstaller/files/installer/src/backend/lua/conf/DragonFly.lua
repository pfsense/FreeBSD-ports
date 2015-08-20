--
-- conf/DragonFly.lua
-- $Id: DragonFly.lua,v 1.8.2.1 2006/07/28 16:19:53 sullrich Exp $
--
-- This file contains DragonFly-specific overrides to BSDInstaller.lua.
--

os = {
	name = "DragonFly BSD",
	version = "2.0-RELEASE"
}

install_items = {
	"COPYRIGHT",
	"bin",
	"boot",
	"dev",
	{ src = "etc.hdd", dest = "etc" },  -- install media config differs
	"lib",
	"libexec",
	"kernel",
	"modules",
	"root",
	"sbin",
	"sys",		-- XXX What's the deal with this anyway?
	"usr/bin",
	"usr/freebsd_pkg",
	"usr/pkg",
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

default_packages = default_packages + {
	"^cdrtools-",
	"^cvsup-"
}

sysids = {
	{ "DragonFly/FreeBSD",	165 },
	{ "OpenBSD",		166 },
	{ "NetBSD",		169 },
	{ "MS-DOS",		 15 },
	{ "Linux",		131 },
	{ "Plan9",		 57 }
}

default_sysid = 165
package_suffix = "tgz"
num_subpartitions = 16
has_raw_devices = false
disklabel_on_disk = false
has_softupdates = true
window_subpartitions = { "c" }
use_cpdup = true

--
-- Offlimits mount points.  BSDInstaller will ignore these mount points
--
-- example: offlimits_mounts  = { "unionfs" }
offlimits_mounts = { }

