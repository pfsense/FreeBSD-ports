-- $Id: 600_unmount_target_system.lua,v 1.3 2005/06/15 00:23:38 cpressey Exp $

return {
    id = "unmount_target_system",
    name = _("Unmount Target System"),
    interactive = false,
    req_state = { "target" },
    effect = function(step)
	if App.state.target:unmount() then
		return step:next()
	else
		App.ui:inform("Target system could not be unmounted!")
		return nil
	end
    end
}
