-- $Id: 200_select_disk.lua,v 1.7 2005/08/26 04:25:25 cpressey Exp $

return {
    id = "select_disk",
    name = _("Select Disk"),
    req_state = { "storage" },
    effect = function(step)
	App.state.sel_disk = nil
	App.state.sel_part = nil

	-- XXX there might be a better place to handle this.
	if App.state.storage:get_disk_count() == 0 then
		App.ui:inform(_(
		    "The installer could not find any suitable disks "	..
		    "attached to this computer.  If you wish to "	..
		    "upgrade an installation located "			..
		    "on an unorthodox storage device, you will have to " ..
		    "exit to a %s command prompt and configure it "	..
		    "manually, using the file /README as a guide.",
		    App.conf.product.name, App.conf.media_name)
		)
		return nil
	end

	local dd = StorageUI.select_disk({
	    sd = App.state.storage,
	    short_desc = _(
	        "Select the disk on which the installation " ..
		"of %s that you wish to upgrade resides.",
	        App.conf.product.name),
	    cancel_desc = _("Return to %s", step:get_prev_name())
	})

	if dd then
		App.state.sel_disk = dd
		return step:next()
	else
		return step:prev()
	end
    end
}
