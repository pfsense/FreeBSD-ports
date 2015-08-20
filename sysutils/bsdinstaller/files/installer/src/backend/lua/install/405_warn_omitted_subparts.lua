-- $Id: 405_warn_omitted_subparts.lua,v 1.1 2005/07/23 21:35:46 cpressey Exp $

return {
    id = "warn_omitted_subpartitions",
    name = _("Warn Omitted Subpartitions"),
    req_state = { "sel_disk", "sel_part" },
    interactive = false,
    effect = function(step)
	local number = 0
	local omit = ""
	local consequences = ""

	local pd = App.state.sel_part

	if not pd:get_subpart_by_mountpoint("/var") then
		number = number + 1
		omit = omit .. "/var "
		consequences = consequences ..
		    _("%s will be a plain dir in %s\n", "/var", "/")
	end

	if not pd:get_subpart_by_mountpoint("/usr") then
		number = number + 1
		omit = omit .. "/usr "
		consequences = consequences ..
		    _("%s will be a plain dir in %s\n", "/usr", "/")
	end

	if not pd:get_subpart_by_mountpoint("/tmp") then
		if pd:get_subpart_by_mountpoint("/var") then
			number = number + 1
			omit = omit .. "/tmp "
			consequences = consequences ..
			    _("%s will be symlinked to %s\n", "/tmp", "/var/tmp")
		elseif pd:get_subpart_by_mountpoint("/usr") then
			number = number + 1
			omit = omit .. "/tmp "
			consequences = consequences ..
			    _("%s will be symlinked to %s\n", "/tmp", "/usr/tmp")
		end
	end

	if not pd:get_subpart_by_mountpoint("/home") then
		if pd:get_subpart_by_mountpoint("/usr") then
			number = number + 1
			omit = omit .. "/home "
			consequences = consequences ..
			    _("%s will be symlinked to %s\n", "/home", "/usr/home")
		elseif pd:get_subpart_by_mountpoint("/var") then
			number = number + 1
			omit = omit .. "/home "
			consequences = consequences ..
			    _("%s will be symlinked to %s\n", "/home", "/var/home")
		end
	end

	if number > 0 then
		local omit_button_text, bare_plural, det_plural

		if number > 1 then
			omit_button_text = _("Omit Subpartitions")
			bare_plural = _("subpartitions")
			det_plural = _("these subpartitions")
		else
			omit_button_text = _("Omit Subpartition")
			bare_plural = _("subpartition")
			det_plural = _("this subpartition")
		end

		local response = App.ui:present{
		    name = _("Omit subpartitions?"),
		    short_desc = _(
			"You have elected to not have the following "	..
			"%s:\n\n%s\n\n"					..
			"The ramifications of %s being "		..
			"missing will be:\n\n%s\n"			..
			"Is this really what you want to do?",
			bare_plural, omit, det_plural, consequences
		    ),
		    actions = {
			{
			    id = "ok",
			    name = omit_button_text
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("Return to %s", step:get_prev_name())
			}
		    }
		}
		if response.action_id ~= "ok" then
			return step:prev()
		end
	end

	return step:next()
    end
}
