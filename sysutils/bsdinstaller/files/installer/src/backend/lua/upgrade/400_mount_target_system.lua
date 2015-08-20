-- $Id: 400_mount_target_system.lua,v 1.6 2005/08/04 22:23:06 cpressey Exp $

return {
    id = "mount_target_system",
    name = _("Mount Target System"),
    interactive = false,
    req_state = { "storage", "sel_disk", "sel_part" },
    effect = function(step)
	--
	-- If there is a target system mounted, unmount it before starting.
	--
	if App.state.target ~= nil and App.state.target:is_mounted() then
		if not App.state.target:unmount() then
			App.ui:inform(
			    _("Warning: already-mounted target system could " ..
			      "not be correctly unmounted first.")
			)
			return step:prev()
		end
	end

	App.state.target = TargetSystem.new{
	    partition = App.state.sel_part,
	    base      = "mnt"
	}
	if not App.state.target:probe() then
		App.ui:inform(_(
		    "The target system could not be successfully probed."
		))
		return step:prev()
	end
	if not App.state.target:mount() then
		App.ui:inform(_(
		    "The target system could not be successfully mounted."
		))
		return step:prev()
	end

	return step:next()
    end
}
