-- $Id: 400_mount_target_system.lua,v 1.22 2005/08/04 22:00:40 cpressey Exp $

--
-- Mount the chosen system as the target system for configuration.
--

return {
    id = "mount_target_system",
    name = _("Mount Target System"),
    interactive = false,
    req_state = { "storage", "sel_disk", "sel_part" },
    effect = function(step)
	--
	-- If the user has already mounted a TargetSystem (e.g. they are
	-- coming here directly from the end of an install,) skip ahead.
	--
	if App.state.target ~= nil and App.state.target:is_mounted() then
		return step:next()
	end

	App.state.target = TargetSystem.new{
	    partition  = App.state.sel_part,
	    base       = "mnt"
	}
	local ok, errmsg = App.state.target:probe()
	if not ok then
		App.log(errmsg)
		App.ui:inform(_(
		    "The target system could not be successfully probed:\n\n%s",
		    errmsg
		))
		App.state.target = nil
		return step:prev()
	end
	local ok, errmsg = App.state.target:mount()
	if not ok then
		App.log(errmsg)
		App.ui:inform(_(
		    "The target system could not be successfully mounted:\n\n%s",
		    errmsg
		))
		App.state.target = nil
		return step:prev()
	end

	return step:next()
    end
}
