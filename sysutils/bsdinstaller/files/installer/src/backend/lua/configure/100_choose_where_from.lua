-- $Id: 100_choose_where_from.lua,v 1.16 2005/08/30 00:39:05 cpressey Exp $

--
-- Allow the user to select which system to configure.
--
-- If this detects that it was started from the install media, this
-- step does not apply; that is, we're not allowed to configure the
-- running system in this case (it is assumed to be immutable) and
-- we skip straight to selecting which disk/partition to configure.
--

if App.conf.booted_from_install_media then
	return nil, "was booted from install media"
end

return {
    id = "choose_target_system",
    name = _("Choose Target System"),
    effect = function(step)
	--
	-- If the user has already selected a TargetSystem (e.g. they are
	-- coming here directly from the end of an install,) skip ahead.
	--
	if App.state.target ~= nil then
		return step:next()
	end

	--
	-- Ask the user where to configure.
	--
	local action_id = App.ui:present({
	    id = "choose_target_system",
	    name = _("Choose Target System"),
	    short_desc = _(
	        "Please choose which installed system you want to configure."
	    ),
	    actions = {
		{
		    id = "this",
		    name = _("Configure the Running System")
		},
		{
		    id = "disk",
		    name = _("Configure a System on Disk")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name()),
		}
	    },
	    role = "menu"
	}).action_id

	if action_id == "cancel" then
		return step:prev()
	elseif action_id == "disk" then
		return step:next()
	else -- "this"
		App.state.target = App.state.source
		-- Jump straight to the menu.
		return "configuration_menu"
	end
    end
}
