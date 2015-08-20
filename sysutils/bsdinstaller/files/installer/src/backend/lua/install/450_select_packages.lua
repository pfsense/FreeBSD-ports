-- $Id: 450_select_packages.lua,v 1.17 2005/08/26 04:25:24 cpressey Exp $

--
-- Select packages to initially be installed.
--
-- This Step is skipped by default, on the assumption that the operating
-- system requires some standard packages that the user should not be
-- able to avoid installing, and that customizing the system by
-- installing optional packages can be done during the Configure Flow.
--

return {
    id = "select_packages",
    name = _("Select Packages"),
    req_state = { "source", "sel_pkgs" },
    effect = function(step)
	--
	-- Ask the user what packages they want.
	--
	local ok, sel_pkgs = PackageUI.select_packages{
	    name = _("Select Packages"),
	    short_desc = _("Select the packages you wish to install from " ..
			    "the %s onto the HDD.", App.conf.media_name),
	    checkbox_name = _("Install?"),
	    ok_name = _("Accept these Packages"),
	    cancel_name = _("Return to %s", step:get_prev_name()),

	    all_pkgs = App.state.all_pkgs,
	    sel_pkgs = App.state.sel_pkgs
	}

	if ok then
		-- Change the selected packages to the user's selection.
		App.state.sel_pkgs = sel_pkgs
		return step:next()
	else
		-- Don't change the selected packages.
		return step:prev()
	end
    end
}
