-- $Id: PackageUI.lua,v 1.3 2005/07/23 19:26:26 cpressey Exp $

--
-- Copyright (c)2005 Chris Pressey.  All rights reserved.
--
-- Redistribution and use in source and binary forms, with or without
-- modification, are permitted provided that the following conditions
-- are met:
--
-- 1. Redistributions of source code must retain the above copyright
--    notices, this list of conditions and the following disclaimer.
-- 2. Redistributions in binary form must reproduce the above copyright
--    notices, this list of conditions, and the following disclaimer in
--    the documentation and/or other materials provided with the
--    distribution.
-- 3. Neither the names of the copyright holders nor the names of their
--    contributors may be used to endorse or promote products derived
--    from this software without specific prior written permission. 
--
-- THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
-- ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES INCLUDING, BUT NOT
-- LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
-- FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE
-- COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
-- INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
-- BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
-- LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
-- CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
-- LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
-- ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
-- POSSIBILITY OF SUCH DAMAGE.
--

module "package_ui"

local Package = require("package")

--[[-----------]]--
--[[ PackageUI ]]--
--[[-----------]]--

PackageUI = {}

PackageUI.select_packages = function(tab)
	local datasets_list = {}
	local pkg, selected, i, dataset

	local all_pkgs = tab.all_pkgs or Package.Set.new()
	local sel_pkgs = tab.sel_pkgs or Package.Set.new()

	for pkg in all_pkgs:each_pkg() do
		table.insert(datasets_list, {
		    selected = (sel_pkgs:contains(pkg) and "Y") or "N",
		    pkg_name = pkg:get_name()
		})
	end
	table.sort(datasets_list, function(a, b)
		return a.pkg_name < b.pkg_name
	end)

	local fields_list = {
		{
		    id = "selected",
		    name = tab.checkbox_name or _("Install?"),
		    control = "checkbox"
		},
		{
		    id = "pkg_name",
		    name = _("Full Name of Package"),
		    editable = "false"
		}
	}

	local actions_list = {
		{
		    id = "all",
		    accelerator = "A",
		    name = tab.all_name or _("Select All")
	        },
		{
		    id = "none",
		    accelerator = "N",
		    name = tab.none_name or _("Select None")
	        },
		{
		    id = "ok",
		    name = tab.ok_name or _("Accept these Packages")
	        },
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = tab.cancel_name or _("Cancel")
	        }
	}

	while true do
		local response = App.ui:present({
		    id = tab.id or "select_packages",
		    name = tab.name or _("Select Packages"),
		    short_desc = tab.short_desc or
			_("Select the packages you wish to install."),
		    long_desc = tab.long_desc,
		    actions = actions_list,
		    fields = fields_list,
		    datasets = datasets_list,
	
		    multiple = "true"
		})

		datasets_list = response.datasets

		if response.action_id == "all" then
			for i, dataset in datasets_list do
				dataset.selected = "Y"
			end
		elseif response.action_id == "none" then
			for i, dataset in datasets_list do
				dataset.selected = "N"
			end
		else
			local pkgs = Package.Set.new()
			for i, dataset in datasets_list do
				local t_pkg = Package.new{ name = dataset.pkg_name }
				assert(all_pkgs:contains(t_pkg),
				    "Can't find package named '" .. t_pkg:get_name() .. "'!")
				if dataset.selected == "Y" then
					pkgs:add(t_pkg)
				end
			end
			return response.action_id == "ok", pkgs
		end
	end
end

return PackageUI
