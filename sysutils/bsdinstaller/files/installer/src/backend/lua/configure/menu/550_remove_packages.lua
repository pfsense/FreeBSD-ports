-- $Id: 550_remove_packages.lua,v 1.13 2005/06/14 20:49:27 cpressey Exp $

local remove_packages = function()
	local ok
	local installed_pkgs = Package.Set.new()
	installed_pkgs:enumerate_installed_on(App.state.target)
	local sel_pkgs = Package.Set.new()

	if installed_pkgs:size() == 0 then
		App.ui:inform(_(
		    "There are no packages installed on this system."
		))
		return
	end

	ok, sel_pkgs = PackageUI.select_packages{
	    name = _("Select Packages"),
	    short_desc = _("Select the packages you wish to remove from " ..
			    "this system"),
	    checkbox_name = _("Remove?"),
	    ok_name = _("Remove these Packages"),
	    cancel_name = _("Cancel"),

	    sel_pkgs = sel_pkgs,
	    all_pkgs = installed_pkgs
	}

	if ok then
		local pkg_graph = sel_pkgs:to_graph(function(pkg)
		    return pkg:get_dependents(App.state.target)
		end, true)

		--
		-- Notify about dependents that will also be removed.
		--
		local pkg_extra = pkg_graph:to_set()
		pkg_extra:take_difference(sel_pkgs)
		if pkg_extra:size() > 0 then
			local render = ""
			local pkg_list = pkg_extra:to_list()
			local pkg

			pkg_list:sort()
			for pkg in pkg_list:each_pkg() do
				render = render .. pkg:get_name() .. "\n"
			end
			
			if not App.ui:confirm(_(
				"The following installed packages require " ..
				"one or more of the packages you selected, " ..
				"and will also be removed:\n\n%s\n" ..
				"Is this acceptable?", render
			    )) then
				return
			end
		end

		local num_sel_pkgs = pkg_graph:size()
		local pkg_list = pkg_graph:topological_sort()
		local cmds = CmdChain.new()
		pkg_list:cmds_remove_all(cmds, App.state.target)

		if cmds:execute() then
			App.ui:inform(_(
			    "%d/%d packages were successfully removed!",
			    num_sel_pkgs - pkg_list:size(), num_sel_pkgs
			))
		else
			App.ui:inform(_(
			    "There were errors. " ..
			    "Some packages may not have been " ..
			    "successfully removed."
			))
		end
	end
end

return {
    id = "remove_packages",
    name = _("Remove Packages"),
    effect = function()
	remove_packages()
	return Menu.CONTINUE
    end
}
