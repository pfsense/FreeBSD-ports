--
-- conf/OpenBSD.lua
-- $Id: OpenBSD.lua,v 1.5.2.1 2006/07/28 16:19:53 sullrich Exp $
--
-- This file contains OpenBSD-specific overrides to conf/BSDInstaller.lua.
--

os = {
	name = "OpenBSD",
	version = "3.7"
}

cmd_names = cmd_names + {
	TEST_DEV = "bin/test -b",
}

mount_info_regexp = "^([^%s]+)%s+on%s+([^%s]+)%s+type%s+([^%s]+)"

sysids = {
	{ "OpenBSD",		166 },
	{ "DragonFly/FreeBSD",	165 },
	{ "NetBSD",		169 },
	{ "MS-DOS",		 15 },
	{ "Linux",		131 },
	{ "Plan9",		 57 }
}

default_sysid = 166
num_subpartitions = 16
has_raw_devices = true
disklabel_on_disk = true
has_softupdates = false
window_subpartitions = { "c", "d" }
use_cpdup = false

--
-- Offlimits mount points.  BSDInstaller will ignore these mount points
--
-- example: offlimits_mounts  = { "unionfs" }
offlimits_mounts = { }

