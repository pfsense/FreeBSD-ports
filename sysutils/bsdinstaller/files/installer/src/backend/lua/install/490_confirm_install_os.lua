-- $Id: 490_confirm_install_os.lua,v 1.2 2005/08/26 04:25:24 cpressey Exp $

return {
    id = "confirm_install_os",
    name = _("Confirm Install OS"),
    req_state = { "storage", "sel_disk", "sel_part", "sel_pkgs", "extra_fs" },
    effect = function(step)
	--
	-- Final confirmation.
	--
	local response = App.ui:present({
	    id = "ready_to_install",
	    name = _("Ready to Install"),
	    short_desc = _(
		"Everything is now ready to install the actual files which "	..
		"constitute %s on partition #%d of the disk %s.\n\n"		..
		"Note that this process can take quite a while to finish. "	..
		"You may wish to take a break now and come back to the "	..
		"computer in a short while.",
		App.conf.product.name,
		App.state.sel_part:get_number(),
		App.state.sel_disk:get_name()),
	    actions = {
		{
		    id = "ok",
		    name = _("Begin Installing Files")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name())
		},
		{
		    id = "abort",
		    name = _("Abort and Return to %s", step:get_upper_name())
		}
	    }
	})

	if response.action_id == "cancel" then
		return step:prev()
	end
	if response.action_id == "abort" then
		return nil
	end

	return step:next()
    end
}
