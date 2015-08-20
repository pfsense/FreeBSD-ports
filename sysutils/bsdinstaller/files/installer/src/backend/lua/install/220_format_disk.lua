-- $Id: 220_format_disk.lua,v 1.15 2006/02/03 22:54:13 sullrich Exp $

--
-- Allow the user to format the selected disk, if they so desire.
--

--
-- Utility function which asks the user what geometry they'd like to use.
--
local select_geometry = function(step, dd)
	local c_cyl, c_head, c_sec = dd:get_geometry()
	local geom_msg = _(
	    "The system reports that the geometry of %s is\n\n"	..
	    "%d cylinders, %d heads, %d sectors\n\n",
	    dd:get_name(),
	    dd:get_geometry_cyl(),
	    dd:get_geometry_head(),
	    dd:get_geometry_sec()
	)
	local valid_msg
	if dd:is_geometry_bios_friendly() then
		valid_msg = _(
		    "This geometry should enable you to boot from "	..
		    "this disk.  Unless you have a pressing reason "	..
		    "to do otherwise, it is recommended that you use "	..
		    "it.\n\n"
		)
	else
		c_cyl, c_head, c_sec = dd:get_normalized_geometry()
		valid_msg = _(
		    "This geometry will NOT enable you to boot from "	..
		    "this disk!  Unless you have a pressing reason "	..
		    "to do otherwise, it is recommended that you use "	..
		    "the following modified geometry:\n\n"		..
		    "%d cylinders, %d heads, %d sectors\n\n",
		    c_cyl, c_head, c_sec
		)
	end
	local end_msg = _(
	    "If you don't understand what any of this means, just "	..
	    "select 'Use this Geometry' to continue."
	)

	local response = App.ui:present{
	    id = "select_geometry",
	    name = _("Select Geometry"),
	    short_desc = geom_msg .. valid_msg .. end_msg,

	    fields = {
		{
		    id = "cyl",
		    name = _("Cylinders"),
		    short_desc = _("Enter the number of cylinders in this disk's geometry")
		},
		{
		    id = "head",
		    name = _("Heads"),
		    short_desc = _("Enter the number of heads in this disk's geometry")
		},
		{
		    id = "sec",
		    name = _("Sectors"),
		    short_desc = _("Enter the number of sectors in this disk's geometry")
		},
	    },

	    datasets = {
		{
		    cyl  = tostring(c_cyl),
		    head = tostring(c_head),
		    sec  = tonumber(c_sec)
		}
	    },

	    actions = {
		{
		    id = "ok",
		    name = _("Use this Geometry")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name())
		}
	    }
	}

	if response.action_id == "ok" then
		dd:set_geometry(
		    tonumber(response.datasets[1].cyl),
		    tonumber(response.datasets[1].head),
		    tonumber(response.datasets[1].sec)
		)
		return true
	else
		return false
	end
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
	cmdsGPT:add("/usr/sbin/cleargpt.sh ${disk}");
	cmdsGPT:execute()
	dd:cmds_format(cmds)

	local confirm = function()
		local response = App.ui:present{
		    id = "confirm_alter_disk",
		    name = _("ABOUT TO FORMAT! Proceed?"),
		    short_desc = _(
			"WARNING!  ALL data in ALL partitions "	..
			"on the disk\n\n"			..
			"%s\n\nwill be IRREVOCABLY ERASED!\n\n"	..
			"Are you ABSOLUTELY SURE you wish to "	..
			"take this action?  This is your "	..
			"LAST CHANCE to cancel!",
			dd:get_desc()
		    ),

		    actions = {
			{
			    id = "ok",
			    name = _("Format %s", dd:get_name())
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("Return to %s", step:get_prev_name())
			}
		    }
		}
		return response.action_id == "ok"
	end

	if dd:has_been_touched() or confirm() then
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
	else
		App.ui:inform(_(
		    "Action cancelled.  No disks were formatted."
		))
		return false
	end
end

return {
    id = "format_disk",
    name = _("Format Disk"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	local response = App.ui:present{
	    id = "format_disk",
	    name = _("Format this Disk?"),
	    short_desc = _(
		"Would you like to format this disk?\n\n"	..
		"You should format the disk if it is new, "	..
		"or if you wish to start from a clean "		..
		"slate.  You should NOT format the disk "	..
		"if it contains information that you "		..
		"want to keep."
	    ),

	    actions = {
		{
		    id = "ok",
		    name = _("Format this Disk")
		},
		{
		    id = "skip",
		    name = _("Skip this step")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name())
		}
	    }
	}
	if response.action_id == "cancel" then
		return step:prev()
	elseif response.action_id == "skip" then
		return step:next()
	elseif response.action_id == "ok" then
		if format_disk(step, App.state.sel_disk) then
			--
			-- Success.  Select the (only!) partition on the disk.
			--
			App.state.sel_part =
			    App.state.sel_disk:get_part_by_number(1)
			return step:next()
		else
			return step:prev()
		end
	end
    end
}
