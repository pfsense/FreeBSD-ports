-- $Id: 410_select_additional_filesystems.lua,v 1.4 2005/08/26 04:25:24 cpressey Exp $

--
-- Select any extra filesystems to put into the fstab of the target system.
--

return {
    id = "select_additional_filesystems",
    name = _("Select Additional Filesystems"),
    effect = function(step)
	local response = App.ui:present{
	    id = "select_additional_filesystems",
    	    name =  _("Select Additional Filesystems"),
	    short_desc = _(
		"Select any extra filesystems that you wish to be able to "	..
		"access on this computer when booted into %s.",
		App.conf.product.name),

	    fields = {
		{
		    id = "desc",
		    name = _("Description of Filesystem"),
		    short_desc = _("Description of this filesystem"),
		    editable = "false"
		},
		{
		    id = "dev",
		    name = _("Device"),
		    short_desc = _("Device node used for this filesystem"),
		},
		{
		    id = "mtpt",
		    name = _("Mountpoint"),
		    short_desc = _("Where this filesystem is mounted"),
		},
		{
		    id = "fstype",
		    name = _("FSType"),
		    short_desc = _("Type of this filesystem"),
		    editable = "false"
		},
		{
		    id = "access",
		    name = _("Access"),
		    short_desc = _("How this filesystem may be accessed"),
		    editable = "false"
		},
		{
		    id = "selected",
		    name = _("Include?"),
		    short_desc = _("Include this filesystem on this installation?"),
		    control = "checkbox"
		}
	    },

	    --
	    -- They should also be set as selected or not based on whether
	    -- they are already present in App.state.extra_fs.
	    --
	    datasets = App.conf.extra_filesystems,

	    actions = {
		{
		    id = "ok",
		    name = _("Proceed")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name())
		}
	    },

	    multiple = "true",
	    extensible = "false"
	}

	if response.action_id == "ok" then
		local i, dataset

		App.state.extra_fs = {}
		for i, dataset in ipairs(response.datasets) do
			if dataset.selected == "Y" then
				table.insert(App.state.extra_fs, dataset)
			end
		end

		return step:next()
	else
		return step:prev()
	end
    end
}
