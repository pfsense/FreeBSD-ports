--
-- conf/BSDInstaller.lua
-- $Id: BSDInstaller.lua,v 1.14.2.2 2006/08/29 01:20:41 sullrich Exp $
--
-- The monolithic default configuration file for the BSD Installer.
--
-- Many of the defaults in here are appropriate to DragonFly BSD,
-- but only because that platform is where the installer is
-- developed, and there is no other "default" for many of these
-- things (except perhaps 4.4BSD-LITE.)
--
-- This file can (and should) be partially overridden by further,
-- operating system- and product-specific configuration files.
--
-- Settings can also be set or overridden from the command line like so:
--    fake_execution=true dir.root=/usr/release/root
--

-------------------------------------------------------------------
-- Application Settings
-------------------------------------------------------------------

--
-- app_name: name of the application.
--

app_name = "BSD Installer"

--
-- log_filename: the file to which logs will be recorded.
-- This exists under dir.tmp.
--

log_filename = "installer.log"

--
-- dir: a table of important directories.
--
-- dir.root is where all commands are assumed to be located, and where
-- all system files are copied from.
--
-- dir.tmp is the temporary directory.
--

dir = {
	root	= "/",
	tmp	= "/tmp/"
}


-------------------------------------------------------------------
-- Installation Parameters
-------------------------------------------------------------------

--
-- os: table which describes the operating system.
-- Required, but no default value is given, so it is necessary
-- that some other configuration file override these entries.

os = {
	name = nil,
	version = nil
}


--
-- product: table which describes the product which is being installed;
-- if not given, the product is assumed to be the operating system.
--

product = {
	name = nil,
	version = nil
}


--
-- Name of the install media in use.  Usually "LiveCD", but could be
-- "CompactFlash card", "install disk", etc.
--
media_name = "LiveCD"


--
-- install_items: description of the set of items that are to be
-- installed.   Each item represents a file or directory to copy,
-- and can be specified with either:
--   o  a string, in which case the source has the same name as the dest, or
--   o  a table, with "src" and "dest" keys, so that the names may differ.
-- (A table is particularly useful with /etc, which may have configuration
-- files which produce significantly different behaviour on the install
-- medium, compared to a standard HDD boot.)
--
-- Either way, no leading root directory is specified in names of files
-- and directories.
--
-- Note that specifying (for example) "usr/local" will only copy all of
-- /usr/local *if* nothing below /usr/local is specified.  For instance,
-- if you want copy all of /usr/local/ *except* for /usr/local/share,
-- you need to specify all subdirs of /usr/local except for /usr/local/share
-- in the table.
--

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


--
-- cleanup_items: list of files to remove from the HDD immediately following
-- an installation.  These may be files that are simply unwanted, or may
-- impede the functioning of the system (because they came from the
-- installation system, which may have a different configuration in place.)
--
-- On the DragonFlyBSD LiveCD, for example, /boot/loader.conf contains
--   kernel_options="-C"
-- i.e., boot from CD-ROM.  This is clearly inapplicable to a HDD boot.
--

cleanup_items = {
    "/boot/loader.conf"
}


--
-- mtrees_post_copy: a table of directory trees to create, using 'mtree',
-- after everything has been copied.
--

mtrees_post_copy = {
    ["usr/local"] = "etc/mtree/BSD.local.dist",
    ["usr/X11R6"] = "etc/mtree/BSD.x11-4.dist"
}

--
-- upgrade_items: similar to "install_items", except for upgrading purposes
-- instead of initial installation.
--

upgrade_items = {
	"COPYRIGHT",
	"bin",
	"boot/beastie.4th",		-- unfortunately, we need to list
	"boot/boot",			-- everything in boot except for
	"boot/boot0",			-- the .conf files, so that we
	"boot/boot1",			-- don't end up overwriting them
	"boot/boot2",
	"boot/cdboot",
	"boot/defaults",
	"boot/frames.4th",
	"boot/loader",
	"boot/loader.4th",
	"boot/loader.help",
	"boot/loader.old",
	"boot/loader.rc",
	"boot/mbr",
	"boot/pxeboot",
	"boot/screen.4th",
	"boot/support.4th",
	"dev",
	"libexec",
	"lib",
	"kernel",
	"modules",
	"sbin",
	"sys",
	"usr/bin",
	"usr/games",
	"usr/include",
	"usr/lib",
	"usr/libdata",
	"usr/libexec",
	"usr/sbin",
	"usr/share"
}


--
-- mountpoints: a function which takes two numbers (the capacity
-- of the partition and the capacity of RAM, both in megabytes)
-- and which returns a list of tables, each of which like:
--
-- {
--   mountpoint = "/foo",    -- name of mountpoint
--   capstring  = "123M"     -- suggested capacity
-- }
--
-- Note that the capstring can be "*" to indicate 'use the
-- rest of the partition.')
--
-- Typically this function returns a different list of mountpoint
-- descriptions based on the supported capacity of the device.
--
-- As a somewhat special case, this function may return {}
-- (an empty list) to indicate that there simply is not enough
-- space on the device to install anything at all.
--

mountpoints = function(part_megs, ram_megs)

	--
	-- NOTE: Minidumps require at least 64MB
	--

        --
        -- The megabytes available on disk for non-swap use.
        --
        local avail_megs = part_megs

	--
	-- Now, based on the capacity of the partition,
	-- return an appropriate list of suggested mountpoints.
	--
	if avail_megs < 300 then
		return {}
	elseif avail_megs < 523 then
		return {
			{ mountpoint = "/",	capstring = "70M" },
			{ mountpoint = "swap",	capstring = "64M" },
			{ mountpoint = "/var",	capstring = "32M" },
			{ mountpoint = "/tmp",	capstring = "32M" },
			{ mountpoint = "/usr",	capstring = "174M" },
			{ mountpoint = "/home",	capstring = "*" }
		}
	elseif avail_megs < 1024 then
		return {
			{ mountpoint = "/",	capstring = "96M" },
			{ mountpoint = "swap",	capstring = "64M" },
			{ mountpoint = "/var",	capstring = "64M" },
			{ mountpoint = "/tmp",	capstring = "64M" },
			{ mountpoint = "/usr",	capstring = "256M" },
			{ mountpoint = "/home",	capstring = "*" }
		}
	elseif avail_megs < 4096 then
		return {
			{ mountpoint = "/",	capstring = "128M" },
			{ mountpoint = "swap",	capstring = "128M" },
			{ mountpoint = "/var",	capstring = "128M" },
			{ mountpoint = "/tmp",	capstring = "128M" },
			{ mountpoint = "/usr",	capstring = "512M" },
			{ mountpoint = "/home",	capstring = "*" }
		}
	elseif avail_megs < 10240 then
		return {
			{ mountpoint = "/",	capstring = "256M" },
			{ mountpoint = "swap",	capstring = "256M" },
			{ mountpoint = "/var",	capstring = "256M" },
			{ mountpoint = "/tmp",	capstring = "256M" },
			{ mountpoint = "/usr",	capstring = "3G" },
			{ mountpoint = "/home",	capstring = "*" }
		}
	else
		return {
			{ mountpoint = "/",	capstring = "256M" },
			{ mountpoint = "swap",	capstring = "256M" },
			{ mountpoint = "/var",	capstring = "256M" },
			{ mountpoint = "/tmp",	capstring = "256M" },
			{ mountpoint = "/usr",	capstring = "8G" },
			{ mountpoint = "/home",	capstring = "*" }
		}
	end
end


--
-- extra_filesystems:
--

extra_filesystems = {
	{
	    desc     = "Process filesystem",
	    dev      = "proc",
	    mtpt     = "/proc",
	    fstype   = "procfs",
	    access   = "rw",
	    selected = "N"
	},
	{
	    desc     = "CD-ROM drive",
	    dev      = "/dev/acd0c",
	    mtpt     = "/cdrom",
	    fstype   = "cd9660",
	    access   = "ro,noauto",
	    selected = "N"
	}
}

--
-- limits: Limiting values specified by the installation; the most
-- significant of these is the minimum disk space required to
-- install the software.
--

limits = {
	part_min =	  "300M",	-- Minimum size of partition or disk.
	subpart_min = {
	    ["/"]	=  "70M",	-- Minimum size of each subpartition.
	    ["/var"]	=   "8M",	-- If a subpartition has no particular
	    ["/usr"]	= "174M"	-- minimum, it can be omitted here.
	},
	waste_max	=   8192	-- Maximum number of sectors to allow
					-- to go to waste when carving out
					-- partitions and subpartitions.
}


--
-- deafult_packages: a list of packages to install without asking
-- during the install phase.
--
-- Note that these packages are specified by Lua regular expressions
-- that will be passed to string.find().  This allows us to specify
-- packages regardless of their version number, etc.
--

default_packages = {
	-- empty by default
}


--
-- use_cpdup: a boolean which indicates whether the 'cpdup' utility
-- will be used to copy files and directories to the target system.
-- If false, 'tar' and 'cp' will be used instead.
--

use_cpdup = true


-------------------------------------------------------------------
-- User Interface
-------------------------------------------------------------------

--
-- ui_nav_control: a configuration table which allows individual
-- user-interface navigation elements to be configured in broad
-- fashion, globally.
--
-- Extra Flow.Steps and Menu.Items can always be added by adding Lua
-- scriptlets to their container directories; however, it is more awkward to
-- delete existing Steps and Items which may be inapplicable in a particular
-- distribution.  So, this file can be used to globally ignore (or otherwise
-- alter the meaning of) individual Steps and Items.
--
-- This configuration file should return a table.  Each key in this table
-- should be a regular expression which will match the id of the Step or
-- Item; the associated value is a control code which indicates what do
-- with all Steps and Items so matched.
--
-- The only supported control code, at present, is "ignore", indicating
-- that the Step or Item should be skipped; this is, not be executed as
-- part of the Flow, or not be displayed as part of the menu.
--
-- NOTE!  Ignoring Flow.Steps properly is more problematic than ignoring
-- Menu.Items, because Steps often rely on a change of state caused by a
-- previous Step.  Configure this table (and write your own Steps) with
-- that fact in mind.
--

ui_nav_control = {
	["*/install/select_packages"] = "ignore", -- do not do the "Select
						  -- Packages" step on install

--						  -- examples follow:
--	["*/install/format_disk"] = "ignore",	  -- do not do the "Format
--						  -- Disk" step on install
--	["*/welcome"] = "ignore",		  -- no "welcome" items at all

--	["*/install/partition_disk"] = "ignore",  -- Don't show the Partition
--      ["*/install/select_part"] = "ignore",     -- Editor or selection.
--                                                -- Used in combination with
--                                                -- "Format Disk" step in
--                                                -- embedded apps, etc.
}


-------------------------------------------------------------------
-- System Settings
-------------------------------------------------------------------

--
-- cmd_names: names and locations of system commands used by the installer.
--
-- Note that some non-command files and directories are configurable
-- here too.
--
-- The main table lists commands apropos for for DragonFly BSD.
-- Conditional overrides for other BSD's are listed below it.
--

cmd_names = {
	SH		= "bin/sh",
	MKDIR		= "bin/mkdir",
	CHMOD		= "bin/chmod",
	LN		= "bin/ln",
	RM		= "bin/rm",
	CP		= "bin/cp",
	DATE		= "bin/date",
	ECHO		= "bin/echo",
	DD		= "bin/dd",
	MV		= "bin/mv",
	CAT		= "bin/cat",
	TEST		= "bin/test",
	TEST_DEV	= "bin/test -c",
	CPDUP		= "bin/cpdup -vvv -I",

	ATACONTROL	= "sbin/atacontrol",
	MOUNT		= "sbin/mount",
	MOUNT_MFS	= "sbin/mount_mfs",
	UMOUNT		= "sbin/umount",
	SWAPON		= "sbin/swapon",
	DISKLABEL	= "sbin/disklabel",
	MBRLABEL	= "sbin/mbrlabel",
	NEWFS		= "sbin/newfs",
	NEWFS_MSDOS	= "sbin/newfs_msdos",
	TUNEFS		= "sbin/tunefs",
	FDISK		= "sbin/fdisk",
	DUMPON		= "sbin/dumpon",
	IFCONFIG	= "sbin/ifconfig",
	ROUTE		= "sbin/route",
	DHCLIENT	= "sbin/dhclient",
	SYSCTL		= "sbin/sysctl",
	MOUNTD		= "sbin/mountd",
	NFSD		= "sbin/nfsd",
	KLDLOAD		= "sbin/kldload",
	KLDUNLOAD	= "sbin/kldunload",
	KLDSTAT		= "sbin/kldstat",

	TOUCH		= "usr/bin/touch",
	YES		= "usr/bin/yes",
	BUNZIP2		= "usr/bin/bunzip2",
	GREP		= "usr/bin/grep",
	KILLALL		= "usr/bin/killall",
	BASENAME	= "usr/bin/basename",
	SORT		= "usr/bin/sort",
	COMM		= "usr/bin/comm",
	AWK		= "usr/bin/awk",
	SED		= "usr/bin/sed",
	BC		= "usr/bin/bc",
	TR		= "usr/bin/tr",
	FIND		= "usr/bin/find",
	CHFLAGS		= "usr/bin/chflags",
	XARGS		= "usr/bin/xargs",
	MAKE		= "usr/bin/make",
	TAR		= "usr/bin/tar",

	PWD_MKDB	= "usr/sbin/pwd_mkdb",
	CHROOT		= "usr/sbin/chroot",
	VIDCONTROL	= "usr/sbin/vidcontrol",
	KBDCONTROL	= "usr/sbin/kbdcontrol",
	PW		= "usr/sbin/pw",
	SWAPINFO	= "usr/sbin/pstat -s",
	BOOT0CFG	= "usr/sbin/boot0cfg",
	FDFORMAT	= "usr/sbin/fdformat",
	MTREE		= "usr/sbin/mtree",
	INETD		= "usr/sbin/inetd",
	DHCPD		= "usr/sbin/dhcpd",
	RPCBIND		= "usr/sbin/portmap",

	PKG_ADD		= "usr/sbin/pkg_add",
	PKG_DELETE	= "usr/sbin/pkg_delete",
	PKG_CREATE	= "usr/sbin/pkg_create",
	PKG_INFO	= "usr/sbin/pkg_info",

	TFTPD		= "usr/libexec/tftpd",

	CVSUP		= "usr/local/bin/cvsup",

	-- These aren't commands, but they're configurable here nonetheless.

	DMESG_BOOT	= "var/run/dmesg.boot",
	MODULES_DIR	= "modules",
	SYSCTL_DISKS	= "kern.disks"
}

--
-- package_suffix: The filename suffix for package files,
-- apropos to the current operating system and/or package
-- system in use.
-- XXX This should be organized better in the future.
--

package_suffix = "tgz"

--
-- enable_crashdumps: Whether crashdumps (to a suitable swap partition)
-- will be enabled upon installation, or not.
--

enable_crashdumps = true

--
-- mount_info_regexp: A Lua regular expression which describes
-- what the output of the 'mount' command looks like, so that
-- it can be parsed to extract mountpoint and filesystem info.
--

mount_info_regexp = "^([^%s]+)%s+on%s+([^%s]+)%s+%(([^%s]+)"


-------------------------------------------------------------------
-- Static Storage Parameters
-------------------------------------------------------------------

--
-- sysids: Partition identifiers that can be used in the partition
-- editor, and their names.  The order they are listed here are the
-- order they will appear in the partition editor.
--
sysids = {
	{ "DragonFly/FreeBSD",	165 },
	{ "OpenBSD",		166 },
	{ "NetBSD",		169 },
	{ "MS-DOS",		 15 },
	{ "Linux",		131 },
	{ "Plan9",		 57 }
}

--
-- default_sysid: the partition identifier to use by default.
--

default_sysid = 165

--
-- has_raw_devices: true if the platform has "raw" devices whose
-- names begin with "r".
--

has_raw_devices = false

--
-- disklabel_on_disk: true if there is only one disklabel per
-- disk (OpenBSD and NetBSD), false if there is more than one, i.e.
-- one disklabel per BIOS partition (FreeBSD and DragonFly BSD.)
--
-- disklabel_on_disk also implies there are no device nodes for
-- BIOS partitions.
--

disklabel_on_disk = false

--
-- num_subpartitions: number of subpartitions supported per disklabel.
--

num_subpartitions = 16

--
-- offlimits_devices: devices which the installer should not
-- consider installing onto.
-- These are actually Lua regexps.
--

offlimits_devices = { "fd%d+", "md%d+", "cd%d+" }

--
-- has_softupdates: whether the operating system supports creating
-- a filesystem with softupdates, i.e. the -U flag to newfs.
--

has_softupdates = true

--
-- window_subpartitions: a list of which subpartitions (BSD partitions)
-- are typically not used for housing filesystems, but rather for
-- representing an entire disk or (BIOS) partition - acting, as it were,
-- as a "window" onto a larger overlapping region of storage.
--

window_subpartitions = { "c" }

-------------------------------------------------------------------
-- Natural Language Services (NLS)
-------------------------------------------------------------------

--
-- languages: table of language descriptions.  The order that they are
-- listed here is the order they will be presented in the language-
-- selection menu.
--
-- Note that 'name' and 'short_desc' will be filtered through gettext
-- automatically later on, and don't need to be given with _() here.
--

languages = {
    {
	id = "ru",
	name = _("Russian"),
	short_desc = _("Russian KOI8-R"),
	vidfont = "cp866",
	font8x8 = "cp866-8x8",
	font8x14 = "cp866-8x14",
	font8x16 = "cp866-8x16",
	keymap = "ru.koi8-r",
	scrnmap = "koi8-r2cp866",
	language = "ru_RU.KOI8-R",
	charset = "KOI8-R",
	term = "cons25r"
    }
}


-------------------------------------------------------------------
-- Debugging
-------------------------------------------------------------------

--
-- fake_execution: if true, don't actually execute anything.
--

fake_execution = false

--
-- confirm_execution: if true, ask before executing every little thing.
--

confirm_execution = false

--
-- booted_from_install_media: if true, force the "install" style menus
-- to come up; if false, force the "configure this system" style menus.
-- If not set (or set to nil,) try to auto-detect (might not work.)
--

booted_from_install_media = nil

--
-- fatal_errors: if true, errors always cause the application to abort.
--

fatal_errors = false

--
-- Offlimits mount points.  BSDInstaller will ignore these mount points
--
-- example: offlimits_mounts  = { "unionfs" }
offlimits_mounts = { }

