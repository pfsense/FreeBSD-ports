-- $Id: 300_set_timezone.lua,v 1.7 2005/06/16 01:07:30 cpressey Exp $

local set_timezone = function()
	local cmds, files, dir, filename, full_filename, found_file

	if App.ui:present({
	    id = "internal_clock_type",
	    name = _("Local or UTC (Greenwich Mean Time) clock"),
	    short_desc = _(
		"Is this machine's internal clock set to local time " ..
		"or UTC (Universal Coordinated Time, roughly the same " ..
		"as Greenwich Mean Time)?\n\n" ..
		"If you don't know, assume local time for now."
	    ),

	    actions = {
		{
		    id = "local",
		    name = _("Local Time")
		},
		{
		    id = "utc",
		    name = _("UTC Time")
		}
	    }
	}).action_id == "utc" then
		cmds = CmdChain.new()

		cmds:add({
		    cmdline = "${root}${TOUCH} ${root}${base}etc/wall_cmos_clock",
		    replacements = {
			base = App.state.target:get_base()
		    }
		})
		cmds:execute()
	end

	--
	-- Select a file.
	--
	dir = App.expand("${root}${base}usr/share/zoneinfo",
	    {
		base = App.state.target:get_base()
	    }
	)
	local orig_dir = dir

	found_file = false
	while not found_file do
		filename = App.ui:select_file{
		    title = _("Select Time Zone"),
		    short_desc = _("Select a Time Zone appropriate to your physical location."),
		    cancel_desc = _("Return to Utilities Menu"),
		    dir = dir,
		    predicate = function(filename)
			if filename == "." then return false end
			if dir == orig_dir and filename == ".." then return false end
			return true
		    end
		}
		if filename == "cancel" then
			return false
		end
		if filename == ".." then
			local found, len
			found, len, full_filename = string.find(dir, "^(.*)/.-$")
		else
			full_filename = dir .. "/" .. filename
		end
		if FileName.is_dir(full_filename) then
			dir = full_filename
		else
			filename = full_filename
			found_file = true
		end
	end

	cmds = CmdChain.new()
	cmds:add({
	    cmdline = "${root}${CP} ${filename} ${root}${base}etc/localtime",
	    replacements = {
	        filename = filename,
		base = App.state.target:get_base()
	    }
	})
	if cmds:execute() then
		App.ui:inform(_(
		    "The Time Zone has been successfully set to %s.",
		    filename
		))
	end
end

return {
    id = "set timezone",
    name = _("Set Timezone"),
    effect = function()
	set_timezone()
	return Menu.CONTINUE
    end
}
