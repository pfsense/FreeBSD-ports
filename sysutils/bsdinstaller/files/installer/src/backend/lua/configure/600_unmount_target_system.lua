-- $Id: 600_unmount_target_system.lua,v 1.9 2005/07/13 04:32:22 cpressey Exp $

--
-- Unmount the target system once we are done with it.
--

return {
    id = "unmount_target_system",
    name = _("Unmount Target System"),
    interactive = false,
    req_state = { "target" },
    effect = function(step)
	if App.state.target:unmount() then
		App.state.target = nil
		return step:next()
	else
		App.ui:inform(
		    _("Target system could not be unmounted!")
		)
		return nil
	end
    end
}
