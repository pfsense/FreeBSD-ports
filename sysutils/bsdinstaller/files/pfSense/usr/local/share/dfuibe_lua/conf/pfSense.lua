--
-- conf/pfSense.lua
-- $Id$
--
-- This file contains pfSense-specific overrides to BSDInstaller.lua.
--

product = {
	name = "%%PRODUCT_NAME%%",
	version = "%%PRODUCT_VERSION%%",
	arch = "%%ARCH%%"
}

mountpoints = function(part_cap, ram_cap)

        --
        -- Calculate suggested swap size depending of memory amount vs partition size
        --
        local swap = 0

        if ram_cap >= part_cap then
                swap = part_cap / 4
        elseif ram_cap >= (part_cap / 2) then
                swap = part_cap / 2
        elseif ram_cap >= (part_cap / 4) then
                swap = ram_cap
        else
                swap = ram_cap * 2
        end

        swap = tostring(swap) .. "M"

        --
        -- Now, based on the capacity of the partition,
        -- return an appropriate list of suggested mountpoints.
        --

        --
        -- pfSense: We want to only setup / and swap.
        --

        return {
                { mountpoint = "/",     capstring = "*" },
                { mountpoint = "swap",  capstring = swap },
        }

end

cmd_names = cmd_names + {
	DMESG_BOOT = "var/log/dmesg.boot"
}

mtrees_post_copy = {}

install_items = {
        ".cshrc",
        ".profile",
        "COPYRIGHT",
        "bin",
        "boot",
        "cf",
        "conf",
        "conf.default",
        "dev",
        "etc",
	"home",
        "lib",
        "libexec",
        "license.txt",
	"rescue",
        "root",
        "sbin",
        "usr",
        "var"
}

ui_nav_control = {
	["*/welcome"] = "ignore",           	     			-- do not show any "welcome" items
	["*/configure_installed_system"] = "ignore", 			-- don't put these on
	["pre_install_tasks/select_language"] = "ignore",               -- do not show language selection
	["pre_install_tasks/configure_network"] = "ignore", 		-- no need for configuring network
	["*/upgrade_installed_system"] = "ignore",   			-- the main menu...
	["*/load_kernel_modules"] = "ignore", 		 			-- do not ask about loading kernel modules
	["*/pit/configure_console"] = "ignore",   	 			-- do not ask about console
	["*/pit/configure_network"] = "ignore",   	 			-- do not ask about network
	["*/*netboot*"] = "ignore",						-- ignore netboot installation services
	["*/install/select_packages"] = "ignore", 	 			-- do not do the "Select Packages" step on install
	["*/install/confirm_install_os"] = "ignore",			-- no need to confirm os install
	["*/install/warn_omitted_subpartitions"] = "ignore",	-- warn that /tmp /var and friends are being ommited
	["*/install/finished"] = "ignore",						-- no need to extra spamming
	["*/install/select_additional_filesystems"] = "ignore", -- do not include additional filesystems prompts
	["*/install/270_install_bootblocks.lua"] = "ignore", 	-- ignore the old boot block installer program
	["*/configure/*"] = "ignore",             	 			-- do not configure, we've already did it.
}

booted_from_install_media=true
has_softupdates = true

dir = { root = "/", tmp = "/tmp/" }

limits.part_min = "100M"

offlimits_devices = { "fd%d+", "md%d+", "cd%d+" }

offlimits_mounts  = { "union" }

use_cpdup = true
