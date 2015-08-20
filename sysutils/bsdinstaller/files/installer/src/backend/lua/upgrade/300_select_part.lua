-- $Id: 300_select_part.lua,v 1.5 2005/08/07 02:26:25 cpressey Exp $

--
-- Allow the user to select the BIOS partition where the OS
-- they want to upgrade resides.
--

return {
    id = "select_part",
    name = _("Select Partition"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	App.state.sel_part = nil

	local pd = StorageUI.select_part({
	    dd = App.state.sel_disk,
	    short_desc = _(
		"Select the primary partition of %s " ..
		"on which the installation that " ..
		"you wish to upgrade resides.",
		App.state.sel_disk:get_name()),
	   cancel_desc = _("Return to %s", step:get_prev_name())
	})

	if pd then
		if pd:is_mounted() then
			App.ui:inform(_(
			    "One or more subpartitions on the selected "	..
			    "primary partition already in use (they are "	..
			    "currently mounted in the filesystem.) "		..
			    "You should unmount them before proceeding."
			))
			return step
		end
		App.state.sel_part = pd
		return step:next()
	else
		return step:prev()
	end
    end
}
