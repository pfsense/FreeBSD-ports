-- $Id: 300_select_part.lua,v 1.13 2005/08/26 04:25:24 cpressey Exp $

--
-- Allow the user to select the BIOS partition where the OS
-- they want to configure resides.
--

return {
    id = "select_part",
    name = _("Select Partition"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	--
	-- If the user has already selected a TargetSystem (e.g. they are
	-- coming here directly from the end of an install,) skip ahead.
	--
	if App.state.target ~= nil then
		return step:next()
	end

	--
	-- Allow the user to select a partition.
	--
	App.state.sel_part = nil
	local pd = StorageUI.select_part({
	    dd = App.state.sel_disk,
	    short_desc = _(
		"Select the primary partition of %s " ..
		"on which the installation of %s resides.",
		App.state.sel_disk:get_name(),
		App.conf.product.name),
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
