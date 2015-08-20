-- $Id: NetworkUI.lua,v 1.11 2005/09/02 03:13:06 cpressey Exp $

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

module "network_ui"

local Network = require("network")

--[[-----------]]--
--[[ NetworkUI ]]--
--[[-----------]]--

local NetworkUI = {}

--
-- Private functions.
--

--
-- Execute the given commands, then wait for the given network
-- interface to become available (go 'up'.)  If successful,
-- call write_fun to write the selected settings to a configuration
-- file; if not successful, ask the user if they want to write the
-- settings to the file, even though they did not succeed.
--
local execute_and_wait_for = function(ni, cmds, write_fun)
	if cmds:execute() then
		if App.wait_for{
		    predicate = function()
			ni:probe()
			return ni:is_up()
		    end,
		    timeout = 30,
		    frequency = 2,
		    short_desc = _(
			"Waiting for interface %s to come up...",
			ni:get_name()
		    )
		} then
			App.ui:inform(_(
			   "Interface\n\n%s\n\nis now up, with IP address %s.",
			   ni:get_desc(), ni:get_inet_addr()
			))
			write_fun()
			return true
		else
			App.ui:inform(_(
			   "Interface\n\n%s\n\nfailed to come up.",
			   ni:get_desc()
			))
		end
	end
	if App.ui:confirm(_(
	    "Couldn't successfully configure interface\n\n%s\n\n" ..
	    "Remember these settings (for future recording in " ..
	    "configuration files) anyway?",
	    ni:get_desc()
	)) then
		write_fun()
	end
	return false
end

--
-- Static methods.
--

NetworkUI.dhcp_configure = function(ni)
	local cmds = CmdChain.new()

	ni:cmds_dhcp_configure(cmds)
	return execute_and_wait_for(ni, cmds, function()
	    App.state.rc_conf:set("ifconfig_" .. ni:get_name(), "DHCP")
	end)
end

NetworkUI.static_configure = function(ni)
	local response = App.ui:present{
	    id = "assign_ip",
	    name = _("Assign IP Address"),
	    short_desc = _("Configuring Interface:"),
	    fields = {
		{
		    id = "host",
		    name = _("Hostname"),
		    short_desc = _("Enter the name of this computer")
		},
		{
		    id = "domain",
		    name = _("Domain Name"),
		    short_desc = _("Enter the name of the (local) network this computer is on")
		},
		{
		    id = "ip",
		    name = _("IP Address"),
		    short_desc = _("Enter the IP Address you would like this computer to use")
		},
		{
		    id = "netmask",
		    name = _("Netmask"),
		    short_desc = _("Enter the netmask (defines the extent of the local network)")
		},
		{
		    id = "default_router",
		    name = _("Default Router"),
		    short_desc = _("Enter the IP address of the default router")
		},
		{
		    id = "primary_dns",
		    name = _("Primary DNS Server"),
		    short_desc = _("Enter the IP address of primary DNS Server")
		}
	    },
	    actions = {
		{
		    id = "ok",
		    name = _("Configure Interface")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to Utilities Menu")
		}
	    },
	    datasets = {
		{
		    ip = "",
		    netmask = "",
		    default_router = "",
		    primary_dns = "",
		    host = "",
		    domain = ""
		}
	    }
	}

	if response.action_id == "ok" then
		local ip = response.datasets[1].ip
		local netmask = response.datasets[1].netmask
		local default_router = response.datasets[1].default_router
		local primary_dns = response.datasets[1].primary_dns
		local host = response.datasets[1].host
		local domain = response.datasets[1].domain

		local cmds = CmdChain.new()
		-- XXX check ip for wellformedness first
		ni:cmds_assign_inet_addr(cmds, ip)
		ni:cmds_assign_netmask(cmds, netmask)

		cmds:add{
		    cmdline = "${root}${ROUTE} add default ${default_router}",
		    replacements = {
			default_router = default_router
		    }
		}

		return execute_and_wait_for(ni, cmds, function()
		    App.state.rc_conf:set(
			string.format("ifconfig_%s", ni:get_name()),
			string.format("inet %s netmask %s", ip, netmask)
		    )
		    App.state.rc_conf:set("defaultrouter", default_router)
		    App.state.rc_conf:set("hostname",
			string.format("%s.%s", host, domain))
		    App.state.resolv_conf:set("search", domain)
		    App.state.resolv_conf:set("nameserver", primary_dns)
		end)
	end
end

NetworkUI.rtadv_configure = function(ni)
	local cmds = CmdChain.new()

	cmds:add("${root}${SYSCTL} net.inet6.ip6.accept_rtadv=1")
	return execute_and_wait_for(ni, cmds, function()
	    -- XXX something like this...
	    -- App.state.sysctl_conf:set("net.inet6.ip6.accept_rtadv", "1")
	end)
end

--
-- Allow the user to configure a given interface, by first allowing them
-- to select the method by which they wish to configure it, then dispatching
-- to the selected method.
--
NetworkUI.configure_interface = function(ni)
	return App.ui:present{
	    id = "select_network_configuration_method",
	    name = _("Select Network Configuration Method"),
	    short_desc = _(
		"How would you like to try configuring this network interface?"
	    ),
	    long_desc = _(
		"Manual configuration requires that you enter IP address " ..
		"for this computer and the default router and DNS server " ..
		"of the network.\n\n"					   ..
		"DHCP requires that you have a DHCP server operating on "  ..
		"the network that this interface is attached to.\n\n"      ..
		"Router advertisements require IPv6."
	    ),
	    actions = {
		{
		    id = "static",
		    name = _("Manually"),
		    short_desc = _("Manually enter the network settings for this interface"),
		    effect = function()
			return NetworkUI.static_configure(ni)
		    end
		},
		{
		    id = "dhcp",
		    name = _("By DHCP"),
		    short_desc = _("Contact the DHCP server on this network to discover network settings"),
		    effect = function()
			return NetworkUI.dhcp_configure(ni)
		    end
		},
		{
		    id = "rtadv",
		    name = _("By Router Advertisements"),
		    short_desc = _("Accept IPv6 router advertisements to discover network settings"),
		    effect = function()
			return NetworkUI.rtadv_configure(ni)
		    end
		},
		{
		    id = "cancel",
		    name = _("Cancel"),
		    short_desc = _("Cancel"),
		    accelerator = "ESC",
		    effect = function()
			return nil
		    end
		}
	    }
	}
end

--
-- Display a dialog box which allows the user to select which
-- network interface they want to use.
--
NetworkUI.select_interface = function(nis, tab)
	local actions, ni, ifname
	if not tab then tab = {} end
	local ui = tab.ui or App.ui
	local id = tab.id or "select_interface"
	local name = tab.name or _("Select Network Interface")
	local short_desc = tab.short_desc or _(
	    "Please select the network interface you wish to configure."
	)
	local filter = tab.filter or function() return true end

	--
	-- Get interface list.
	--
	actions = {}
	for ni in nis:each() do
		if filter(ni) then
			table.insert(actions, {
			    id = ni:get_name(),
			    name = ni:get_desc()
			})
		end
	end
	table.insert(actions, {
	    id = "cancel",
	    name = _("Cancel"),
	    accelerator = "ESC"
	})

	ifname = App.ui:present({
	    id = id,
	    name =  name,
	    short_desc = short_desc,
	    role = "menu",
	    actions = actions
	}).action_id

	if ifname == "cancel" then
		return nil
	else
		return nis:get(ifname)
	end
end

return NetworkUI
