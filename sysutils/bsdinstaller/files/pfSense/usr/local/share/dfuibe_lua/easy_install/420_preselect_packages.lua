-- $Id: 420_preselect_packages.lua,v 1.8 2005/08/26 04:25:24 cpressey Exp $

--
-- Select the initial or required packages to install.
--

return {
    id = "preselect_packages",
    name = _("Pre-select Packages"),
    interactive = false,
    req_state = { "source" },
    effect = function(step)
	--
	-- If the user hasn't selected any packages yet, set them up with
	-- the default packages.
	--
	if not App.state.sel_pkgs then
		local def_pkgs = App.conf.default_packages or {}
		local pkg, i, regexp

		App.state.sel_pkgs = Package.Set.new()
		for pkg in App.state.all_pkgs:each_pkg() do
			for i, regexp in def_pkgs do
				if string.find(pkg:get_name(), regexp) then
					App.state.sel_pkgs:add(pkg)
				end
			end
		end
	end

	return step:next()
    end
}
