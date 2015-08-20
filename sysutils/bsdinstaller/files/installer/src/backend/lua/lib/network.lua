-- $Id: Network.lua,v 1.25 2006/07/25 21:36:57 sullrich Exp $
-- Lua abstraction for the Network Interfaces of a system.

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

-- BEGIN lib/network.lua --

module "network"

local App = require("app")
local CmdChain = require("cmdchain")

Network = {}

--[[-------------------]]--
--[[ Network.Interface ]]--
--[[-------------------]]--

--
-- This class returns an object which represents one of the system's
-- network interfaces.
--
-- This class is not typically instantiated directly by client code.
-- Instead, the user should call Network.Interface.all() to get a table
-- of all network interfaces present in the system, and choose one.
--
Network.Interface = {}
Network.Interface.new = function(name)
	local method = {}		-- instance/method variable
	local desc = name		-- description of device
	local up			-- internal state...
	local mtu
	local inet6, prefixlen, scopeid
	local inet, netmask, broadcast
	local ether

	local toboolean = function(x)
		if x then
			return true
		else
			return false
		end
	end

	local hex_to_dotted_decimal = function(str)
		local found, len, a, b, c, d =
		    string.find(str, "^(%x%x)(%x%x)(%x%x)(%x%x)$")
		if not found then
			return "0.0.0.0"
		else
			return	tostring(tonumber(a, 16)) .. "." ..
				tostring(tonumber(b, 16)) .. "." ..
				tostring(tonumber(c, 16)) .. "." ..
				tostring(tonumber(d, 16))
		end
	end

	--
	-- Probe this network interface for its current state.
	--
	-- dc0: flags=8843<UP,BROADCAST,RUNNING,SIMPLEX,MULTICAST> mtu 1500
	-- inet6 fe80::250:bfff:fe96:cf68%dc0 prefixlen 64 scopeid 0x1
	-- inet 10.0.0.19 netmask 0xffffff00 broadcast 10.0.0.255
	-- ether 00:50:bf:96:cf:68
	-- media: Ethernet autoselect (10baseT/UTP)
	--
	method.probe = function(self)
		local found, len, cap, flagstring

		up = nil
		mtu = nil
		inet6, prefixlen, scopeid = nil, nil, nil
		inet, netmask, broadcast = nil, nil, nil

		local cmd = App.expand("${root}${IFCONFIG} ${name}", { name = name })
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, "could not open pty"
		end
		line = pty:readline()
		while line do
			found, len, cap, flagstring =
			    string.find(line, "flags%s*=%s*(%d+)%s*%<([^%>]*)%>")
			if found then
				flagstring = "," .. flagstring .. ","
				up = toboolean(string.find(flagstring, ",UP,"))
			end

			found, len, cap = string.find(line, "mtu%s*(%d+)")
			if found then
				mtu = tonumber(cap)
			end
			found, len, cap = string.find(line, "inet6%s*([^%s]+)")
			if found then
				inet6 = cap
			end
			found, len, cap = string.find(line, "prefixlen%s*(%d+)")
			if found then
				prefixlen = tonumber(cap)
			end
			found, len, cap = string.find(line, "scopeid%s*0x(%x+)")
			if found then
				scopeid = cap
			end
			found, len, cap = string.find(line, "inet%s*(%d+%.%d+%.%d+%.%d+)")
			if found then
				inet = cap
			end
			found, len, cap = string.find(line, "netmask%s*0x(%x+)")
			if found then
				netmask = hex_to_dotted_decimal(cap)
			end
			found, len, cap = string.find(line, "broadcast%s*(%d+%.%d+%.%d+%.%d+)")
			if found then
				broadcast = cap
			end
			found, len, cap = string.find(line, "ether%s*(%x%x%:%x%x%:%x%x%:%x%x%:%x%x%:%x%x%)")
			if found then
				ether = cap
			end
			line = pty:readline()
		end
		pty:close()
	end

	--
	-- Accessor methods.
	--

	method.is_up = function(self)
		return up
	end

	method.get_name = function(self)
		return name
	end

	method.get_inet_addr = function(self)
		return inet
	end

	--
	-- Returns the netmask in dotted-decimal form.
	--
	method.get_netmask = function(self)
		return netmask
	end

	method.get_broadcast_addr = function(self)
		return broadcast
	end

	method.get_ether_addr = function(self)
		return ether
	end

	method.get_desc = function(self)
		return desc
	end

	method.set_desc = function(self, new_desc)
		--
		-- Calculate a score for how well this string describes
		-- a network interface.  Reject obviously bogus descriptions
		-- (usually harvested from error messages in dmesg.)
		--
		local calculate_score = function(s)
			local score = 0

			-- In the absence of any good discriminator,
			-- the longest description wins.
			score = string.len(s)

			-- Look for clues
			if string.find(s, "%<.*%>") then
				score = score + 10
			end

			-- Look for irrelevancies
			if string.find(s, "MII bus") then
				score = 0
			end

			return score
		end

		if calculate_score(new_desc) > calculate_score(desc) then
			desc = new_desc
		end
	end

	--
	-- Set the description of a device, as best we can, based on
	-- the available system information.
	--
	method.auto_describe = function(self)
		--
		-- First give some common pseudo-devices some
		-- reasonable 'canned' descriptions.
		--
		local descs = {
		    ["ppp%d+"] = "Point-to-Point Protocol device",
		    ["sl%d+"] = "Serial Line IP device",
		    ["faith%d+"] = "IPv6-to-IPv4 Tunnel device",
		    ["lp%d+"] = "Network Line Printer device",
		    ["lo%d+"] = "Loopback device"
		}
		for pat, desc in descs do
			if string.find(name, "^" .. pat .. "$") then
				self:set_desc(name .. ": " .. desc)
			end
		end

		--
		-- Now look through dmesg.boot for the names of
		-- physical network interface hardware.
		--
		local bootmsgs_filename = App.expand("${root}${DMESG_BOOT}")
		local line
		if FileName.is_file(bootmsgs_filename) then
			for line in io.lines(bootmsgs_filename) do
				local found, len, cap =
				    string.find(line, "^" .. name .. ":.*(%<.*%>).*$")
				if found then
					self:set_desc(name .. ": " .. cap)
				end
			end
		else
			App.log_warn("couldn't open '%s'", bootmsgs_filename)
		end
	end

	--
	-- CmdChain-creating methods.
	--

	method.cmds_assign_inet_addr = function(self, cmds, addr)
		cmds:add({
		    cmdline = "${root}${IFCONFIG} ${name} ${addr}",
		    replacements = {
			name = name,
			addr = addr
		    }
		})
	end

	method.cmds_assign_netmask = function(self, cmds, netmask)
		cmds:add({
		    cmdline = "${root}${IFCONFIG} ${name} netmask ${netmask}",
		    replacements = {
			name = name,
			netmask = netmask
		    }
		})
	end

	method.cmds_dhcp_configure = function(self, cmds)
		cmds:add(
		    {
			cmdline = "${root}${KILLALL} dhclient",
			failure_mode = CmdChain.FAILURE_IGNORE
		    },
		    {
			cmdline = "${root}${DHCLIENT} ${name}",
			replacements = {
			    name = name
			}
		    }
		)
	end

	--
	-- Create commands necessary for setting up a remote boot server.
	--
	method.cmds_start_netboot_server = function(self, cmds)
		local my_ip = self:get_inet_addr()
		netroot = string.gsub(my_ip, "%.%d+$", "")

		cmds:set_replacements{
		    my_ip = my_ip,
		    netroot = netroot,
		    mask = self:get_netmask() -- XXX probably must be 255.255.255.0
		}
		-- If dhcpd is already running, we can run
		-- into an error.  Simply try to kill it 
		-- first to avoid this error.
		cmds:add(
			{
			cmdline = "${root}${KILLALL} dhcpd",
			failure_mode = CmdChain.FAILURE_IGNORE
		    }
		)
		cmds:add(
		    "${root}${MKDIR} -p ${tmp}tftpdroot",
		    "${root}${CP} ${root}boot/pxeboot ${tmp}tftpdroot",
		    "${root}${ECHO} ${root} -ro -alldirs -maproot=root: " ..
		        "-network ${netroot}.0 -mask ${mask} " ..
			">>${root}etc/exports",
		    "${root}${ECHO} tftp dgram udp wait root " ..
			"${root}${TFTPD} tftpd -l -s ${tmp}tftpdroot " ..
			">>${root}etc/inetd.conf",
		    "${root}${INETD}",
		    "${root}${TOUCH} ${root}var/db/dhcpd.leases",
		    "${root}${ECHO} 'ddns-update-style none;' >${root}etc/dhcpd.conf",
		    "${root}${ECHO} 'class \"pxeboot-class\" { match if substring (option vendor-class-identifier, 0, 9) = \"PXEClient\"; }' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} 'class \"etherboot-class\" { match if substring (option vendor-class-identifier, 0, 9) = \"Etherboot\"; }' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} 'class \"dragonfly-class\" { match if substring (option vendor-class-identifier, 0, 9) = \"DragonFly\"; }' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} 'subnet ${netroot}.0 netmask ${mask} { pool {' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    allow members of \"pxeboot-class\";' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    allow members of \"etherboot-class\";' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    allow members of \"dragonfly-class\";' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    range ${netroot}.128 ${netroot}.254;' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    option subnet-mask ${mask};' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    option broadcast-address ${netroot}.255;' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    filename \"pxeboot\";' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    option root-path \"${my_ip}:/\";' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '    next-server ${my_ip};' >>${root}etc/dhcpd.conf",
		    "${root}${ECHO} '} }' >>${root}etc/dhcpd.conf",
		    "${root}${DHCPD} -cf ${root}etc/dhcpd.conf -lf ${root}var/db/dhcpd.leases",
		    "${root}${RPCBIND}",
		    "${root}${MOUNTD} -ln",
		    "${root}${NFSD} -u -t -n 6"
		)
	end

	return method
end

--[[--------------------]]--
--[[ Network.Interfaces ]]--
--[[--------------------]]--

--
-- A container/aggregate class.  Contains a bunch of Network.Interface
-- objects, typically the set of those available on a given system.
--

Network.Interfaces = {}

Network.Interfaces.new = function()
	local ni_tab = {}
	local method = {}

	method.add = function(self, ni)
		ni_tab[ni.name] = ni
	end

	method.get = function(self, name)
		return ni_tab[name]
	end

	-- Iterator, typically used in for loops.
	method.each = function(self)
		local name, ni
		local list = {}
		local i, n = 0, 0

		for name, ni in ni_tab do
			table.insert(list, ni)
			n = n + 1
		end

		table.sort(list, function(a, b)
			return a:get_name() < b:get_name()
		end)

		return function()
			if i <= n then
				i = i + 1
				return list[i]
			end
		end
	end

	--
	-- Populate the Network.Interface collection with all the
	-- network interfaces detected as present in the system.
	--
	method.probe = function(self)
		local name, ni, line, pat, desc

		local cmd = App.expand("${root}${IFCONFIG} -l")
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, "could not open pty"
		end
		local line = pty:readline()
		pty:close()

		ni_tab = {}

		for name in string.gfind(line, "%s*([^%s]+)%s*") do
			ni = Network.Interface.new(name)
			ni:probe()
			ni:auto_describe()
			ni_tab[name] = ni
		end

		return self
	end

	--
	-- Returns the number of configured IP addresses of all the
	-- Network.Interface objects in this Network.Interfaces object.
	--
	-- Practically, used for asking "Uh, is the network on?"  :)
	--
	method.ip_addr_count = function(self)
		local name, ni
		local num = 0

		for name, ni in ni_tab do
			ip_addr = ni:get_inet_addr()
			if ip_addr and
			    not string.find(ip_addr, "^127%..*$") and
			    not string.find(name, "^faith%d+") then
				num = num + 1
			end
		end

		return num
	end

	return method
end

return Network

-- END of lib/network.lua --

