-- $Id: 220_format_disk.lua,v 1.15 2006/02/03 22:54:13 sullrich Exp $

--
-- Allow the user to format the selected disk, if they so desire.
--

--
-- Utility function which asks the user what geometry they'd like to use.
--
local select_geometry = function(step, dd)
	if dd:is_geometry_bios_friendly() then
		local c_cyl, c_head, c_sec = dd:get_geometry()
	else
		local c_cyl, c_head, c_sec = dd:get_normalized_geometry()
	end

	dd:set_geometry(c_cyl, c_head, c_sec)

	return true
end

--
-- Utility function which confirms that the user would like to proceed,
-- and actually executes the formatting commands.
--
local format_disk = function(step, dd)
	local cmds = CmdChain.new()

	if not select_geometry(step, dd) then
		return false
	end

	local cmdsGPT = CmdChain.new()
	local disk = dd:get_name()
	cmdsGPT:set_replacements{
	    disk = disk
	}
	cmdsGPT:add("/usr/local/sbin/cleargpt.sh ${disk}");
	cmdsGPT:execute()

	dd:cmds_format(cmds)

		if not cmds:execute() then
			App.ui:inform(_(
			    "The disk\n\n%s\n\nwas "		..
			    "not correctly formatted, and may "	..
			    "now be in an inconsistent state. "	..
			    "We recommend trying to format it again " ..
			    "before attempting to install "	..
			    "%s on it.",
			    dd:get_desc(), App.conf.product.name
			))
			return false
		end

		--
		-- The extents of the Storage.System have probably
		-- changed, so refresh our knowledge of it.
		--
		local result
		result, App.state.sel_disk, App.state.sel_part, dd =
		    StorageUI.refresh_storage(
			App.state.sel_disk, App.state.sel_part, dd
		    )
		if not result then
			return false
		end

		--
		-- Mark the disk as having been 'touched'
		-- (modified destructively, i.e. partitioned) by us.
		-- This should prevent us from asking for further
		-- confirmation for changes we might do to it in
		-- the future.
		--
		dd:touch()

		return true
end

return {
    id = "format_disk",
    name = _("Format Disk"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	print("\nFormatting disk...")

	if format_disk(step, App.state.sel_disk) then
		App.state.sel_part =
		    App.state.sel_disk:get_part_by_number(1)
		return step:next()
	else
	--[[
		-- weird hack.
		os.execute("/usr/bin/touch /tmp/install_runagain")
		os.exit()
	--]]
	end
    end
}
