-- $Id: 500_install_packages.lua,v 1.18 2005/08/26 04:25:24 cpressey Exp $

local install_packages = function()
	local ok

	--
	-- Discover the packages installed on the installation medium
	-- (available_pkgs) and those merely included in the directory
	-- /usr/ports/packages/All on the installation medium
	-- (included_pkgs).  Take their union into available_pkgs.
	--
	local available_pkgs = Package.Set.new()
	available_pkgs:enumerate_present_on(App.state.source)

	--
	-- Figure out which packages are already installed, so that
	-- the user is not asked if they want to install them again.
	--
	local installed_pkgs = Package.Set.new()
	installed_pkgs:enumerate_installed_on(App.state.target)
	local pkgs_not_yet_installed = available_pkgs:copy()
	pkgs_not_yet_installed:take_difference(installed_pkgs)
	local sel_pkgs = Package.Set.new()

	if pkgs_not_yet_installed:size() == 0 then
		App.ui:inform(_(
		    "All packages present on the %s " ..
		    "are already installed on this system.",
		    App.conf.media_name
		))
		return
	end

	ok, sel_pkgs = PackageUI.select_packages{
	    name = _("Select Packages"),
	    short_desc = _("Select the packages you wish to install from " ..
			    "the %s onto the HDD.", App.conf.media_name),
	    checkbox_name = _("Install?"),
	    ok_name = _("Install these Packages"),
	    cancel_name = _("Cancel"),

	    all_pkgs = pkgs_not_yet_installed,
	    sel_pkgs = sel_pkgs
	}

	if ok then
		local pkg_graph = sel_pkgs:to_graph(function(pkg)
		    return pkg:get_prerequisites(App.state.source)
		end, true)

		--
		-- Notify about dependencies that will be brought in.
		--
		local pkg_extra = pkg_graph:to_set()
		pkg_extra:take_difference(sel_pkgs)
		pkg_extra:take_difference(installed_pkgs)
		if pkg_extra:size() > 0 then
			local render = ""
			local pkg_list = pkg_extra:to_list()
			local pkg

			pkg_list:sort()
			for pkg in pkg_list:each_pkg() do
				render = render .. pkg:get_name() .. "\n"
			end
			
			if not App.ui:confirm(_(
				"The following packages are required to " ..
				"support the packages you selected, and " ..
				"will also be installed:\n\n%s\n" ..
				"Is this acceptable?", render
			    )) then
				return
			end
		end

		local num_sel_pkgs = pkg_graph:size()
		local pkg_list = pkg_graph:topological_sort()
		pkg_list:take_difference(installed_pkgs)
		local cmds = CmdChain.new()
		pkg_list:cmds_install_all(cmds, App.state.target)

		if cmds:execute() then
			App.ui:inform(_(
			    "%d/%d packages were successfully installed!",
			    num_sel_pkgs - pkg_list:size(), num_sel_pkgs
			))
		else
			App.ui:inform(_(
			    "Errors occurred while installing packages. " ..
			    "Some packages may not have been " ..
			    "successfully installed."
			))
		end
	end
end

return {
    id = "install_packages",
    name = _("Install Packages"),
    effect = function()
	install_packages()
	return Menu.CONTINUE
    end
}
