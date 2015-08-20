-- $Id: 300_select_part.lua,v 1.37 2005/08/26 04:25:24 cpressey Exp $

--
-- Select partition onto which to install.
--

return {
    id = "select_part",
    name = _("Select Partition"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	App.state.sel_part = nil

	local pd = StorageUI.select_part{
	    dd = App.state.sel_disk,
	    short_desc = _(
		"Select the primary partition of %s (also "	..
		"known as a `slice' in the BSD tradition) on "	..
		"which to install %s.",
		App.state.sel_disk:get_name(),
		App.conf.product.name),
	    cancel_desc = _("Return to %s", step:get_prev_name()),
	}

	if pd then
		if pd:is_mounted() then
			App.ui:inform(_(
			    "One or more subpartitions on the selected "	..
			    "primary partition already in use (they are "	..
			    "currently mounted in the filesystem.) "		..
			    "You should either unmount them before "		..
			    "proceeding, or select a different partition "	..
			    "or disk on which to install %s.",
			    App.conf.product.name
			))
			return step
		end

		if pd:get_activated_swap():in_units("K") > 0 then
			local response = App.ui:present{
			    name = _("Cannot swapoff; reboot?"),
			    short_desc = _(
				"Some subpartitions on the selected primary "	..
				"partition are already activated as swap. "	..
				"Since there is no way to deactivate swap in "	..
				"%s once it is activated, in order "		..
				"to edit the subpartition layout of this "	..
				"primary partition, you must first reboot.",
				App.conf.product.name
			    ),
			    actions = {
			        {
				    id = "reboot",
				    name = _("Reboot"),
				    effect = function() return "reboot" end
				},
				{
				    id = "cancel",
				    name = _("Return to %s", step:get_prev_name()),
				    accelerator = "ESC",
				    effect = function() return step:prev() end
				}
			    }
			}
			return response.result
		end

		App.state.sel_part = pd

		local part_min_capacity = Storage.Capacity.new(
		    App.conf.limits.part_min
		)
		if part_min_capacity:exceeds(pd:get_capacity()) then
			App.ui:inform(_(
			    "WARNING: primary partition #%d appears to have " ..
			    "a capacity of %s, which is less than the minimum " ..
			    "recommended capacity, %s. You may encounter "   ..
			    "problems while trying to install %s onto it.",
			    pd:get_number(),
			    pd:get_capacity():format(),
			    part_min_capacity:format(),
			    App.conf.product.name)
			)
		end

		if App.state.sel_disk:has_been_touched() or
		   App.ui:confirm(_(
		    "WARNING!  ALL data in primary partition #%d,\n\n%s\n\non the "	..
		    "disk\n\n%s\n\n will be IRREVOCABLY ERASED!\n\nAre you "		..
		    "ABSOLUTELY SURE you wish to take this action?  This is "		..
		    "your LAST CHANCE to cancel!",
		    pd:get_number(), pd:get_desc(),
		    App.state.sel_disk:get_desc())) then
			local cmds = CmdChain.new()

			pd:cmds_set_sysid(cmds, App.conf.default_sysid)
			pd:cmds_initialize_disklabel(cmds)

			if cmds:execute() then
				App.ui:inform(_(
				    "Primary partition #%d was formatted.",
				    pd:get_number())
				)
				return step:next()
			else
				App.ui:inform(_(
				    "Primary partition #%d was "	..
				    "not correctly formatted, and may "	..
				    "now be in an inconsistent state. "	..
				    "We recommend re-formatting it "	..
				    "before proceeding.",
				    pd:get_number())
				)
				return step
			end
		else
			App.ui:inform(_(
			    "Action cancelled - " ..
			    "no primary partitions were formatted."))
			return step:prev()
		end
	else
		return step:prev()
	end
    end
}
