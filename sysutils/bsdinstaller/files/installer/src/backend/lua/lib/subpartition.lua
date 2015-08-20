-- $Id: Subpartition.lua,v 1.185.2.3 2006/08/29 01:20:41 sullrich Exp $
-- Storage Descriptors (a la libinstaller) in Lua.

--
-- Copyright (c)2006 Scott Ullrich.  All rights reserved.
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

-- BEGIN lib/storage.lua --

module "storage"

local App = require("app")
local Pty = require("pty")
local FileName = require("filename")
local Bitwise = require("bitwise")
local CmdChain = require("cmdchain")

Storage = {}

--
-- Note: these methods should try to use consistent terminology:
--
-- 'size' of an object refers to its size in sectors
--   (which are assumed to be 512 bytes each.)
-- 'capacity' of an object refers a Storage.Capacity object,
--   which may be dereferenced with the in_units() method to retrieve
--   a numeric value in any desired unit.
-- 'capstring' refers to a string which includes a string rendering
--   of a number (with an optional decimal point) and a unit suffix.
--
-- Units:
--   'B' for bytes
--   'S' for sectors
--   'K' for kilobytes
--   'M' for megabytes (= 2048 sectors)
--   'G' for gigabytes
--

--[[------------------]]--
--[[ Storage.Capacity ]]--
--[[------------------]]--

--
-- This is the class of objects which represent storage capacities.
--

Storage.Capacity = {}
Storage.Capacity.CONVERT = {
	["B"] = 1/512,
	["S"] = 1,
	["K"] = 2,
	["M"] = 2048,
	["G"] = 2048 * 1024
}
Storage.Capacity.new = function(amount, units)
	local method = {} -- instance
	local sectors = 0 -- actual state: how many sectors it is.

	--
	-- Retrieve the value of this Capacity as a numeric,
	-- in the given units of measurement.
	--
	method.in_units = function(self, units)
		local conv = assert(Storage.Capacity.CONVERT[units],
				    "Bad units '" .. units .. "'")
		return sectors / conv
	end

	--
	-- Comparators.
	-- (I'm marginally aware I could be using a metatable to
	--  overload the comparison operators, but that's certainly
	--  not a necessary sort of thing right now.)
	--
	method.exceeds = function(self, target_cap)
		return sectors > target_cap:in_units("S")
	end

	method.exceeds_or_equals = function(self, target_cap)
		return sectors >= target_cap:in_units("S")
	end

	--
	-- Format the value of this Capacity into a string
	-- which includes an appropriate units suffix.
	--
	method.format = function(self, units)
		if not units then
			--
			-- Devise a default format by looking at the
			-- largest units in which capacity is still >= 1.
			--
			local largest_unit = "B"
			local largest_conv = Storage.Capacity.CONVERT["B"]
			local unit, conv
			for unit, conv in pairs(Storage.Capacity.CONVERT) do
				if conv > largest_conv and sectors >= conv then
					largest_conv = conv
					largest_unit = unit
				end
			end
			units = largest_unit
		end

		--
		-- Truncate to 2 decimal places and format.
		--
		local num = math.floor(self:in_units(units) * 100) / 100
		return tostring(num) .. units
	end

	--
	-- Constructor - initialize # of sectors.
	--

	if type(amount) == "number" then
		units = units or "M"	-- default is megabytes.
	elseif type(amount) == "string"	then
		local found, len, body, ustr =
		    string.find(amount, "^%s*(%d+%.?%d*)%s*(%a+)%s*$")
		assert(found, "Bad capstring '".. amount .. "'")
		units = string.upper(ustr)
		amount = tonumber(body)
	else
		error("Amount must be string or number, not " .. type(amount))
	end

	local conv = assert(Storage.Capacity.CONVERT[units],
			    "Illegal units '" .. units .. "'")
	sectors = math.floor(amount * conv)

	return method
end
Storage.Capacity.is_valid_capstring = function(capstring)
	local result = pcall(function()
		return Storage.Capacity.new(capstring)
	end)
	return result
end


--[[----------------]]--
--[[ Storage.System ]]--
--[[----------------]]--

--
-- This is the class of objects which represent the storage capabilities
-- of an entire system.  Because programs typically run on only one system,
-- there is typically only one such object.  It contains collections of
-- subsidiary objects, each of which describes the storage capabilities of
-- an individual storage unit (such as a disk, etc.)
--

Storage.System = {}
Storage.System.new = function()
	local disk = {}		-- disks in this storage descriptor
	local ram = 0		-- in megabytes
	local method = {}	-- instance variable

	-- Internal function.
	local next_power_of_two = function(n)
		local i = 1
		n = math.ceil(n)

		while i < n and i >= 1 do
			i = i * 2
		end

		if i > n then
			return i
		else
			return n
		end
	end

	--
	-- Public methods.
	--

	--
	-- Determine identity of this object.
	--
	method.is_a = function(self, class)
		return class == Storage.System
	end

	--
	-- Attempt to ascertain the amount of physical memory on
	-- the running system.
	--
	method.measure_memory = function(self)
		local cmd = App.expand("${root}${SYSCTL} -n hw.physmem")
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			App.log_warn("Couldn't open pty to '%s'", cmd)
			return 0
		end
		local line = pty:readline()
		pty:close()
		App.log("`%s` returned: %s", cmd, line)
		return next_power_of_two((tonumber(line) or 0) / (1024 * 1024))
	end

	--
	-- Next, enumerate the disks attached to the system,
	-- once again using a sysctl.
	--
	method.probe_for_disks = function(self)
		local tab = {}
		local cmd

		cmd = App.expand("${root}${SYSCTL} -n ${SYSCTL_DISKS}")
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			App.log_warn("Couldn't open pty to '%s'", cmd)
			return tab
		end
		local probed_devices = pty:readline()
		pty:close()
		App.log("`" .. cmd .. "` returned: " .. probed_devices)

		--
		-- If the platform is FreeBSD and /dev/mirror/ exists
		-- then get a list of the GEOM Mirror volumes and add
		-- to the selection list.
		--
		if App.conf.os.name == "FreeBSD" then
			if FileName.is_dir(App.expand("${root}dev/mirror")) then
				App.log("/dev/mirror exists.  Surveying.");
				cmd = App.expand('${root}${FIND} /dev/mirror/* | ${root}${SED} "s/\\/dev\\/mirror/mirror/"')
				local pty = Pty.Logged.open(cmd, App.log_string)
				if not pty then
					App.log_warn("Couldn't open pty to '%s'", cmd)
					return tab
				end
				local probed_gmirror_devices = pty:readline()
				pty:close()
				App.log("`" .. cmd .. "` returned: " .. probed_gmirror_devices)
				probed_devices = probed_gmirror_devices .. " " .. probed_devices
			end
		end

		local disk_name
		for disk_name in string.gfind(probed_devices, "%s*([a-zA-Z/0-9]+)") do
			local ok = true
			local i, pattern
			App.log("Testing " .. disk_name)
			for i, pattern in ipairs(App.conf.offlimits_devices) do
				if string.find(disk_name, "^" .. pattern .. "$") then
					App.log("Device " .. disk_name .. " is listed as off limits");
					ok = false
				end
			end
			if ok then
				local disk = Storage.Disk.new(self, disk_name)
				App.log("Invoking survey for " .. disk_name)
				if disk:survey() then
					tab[disk_name] = disk
				else
					App.log_warn("Disk '%s' failed survey",
					    disk_name)
				end
			end
		end

		return tab
	end

	--
	-- Probe the storage capabilities of the system.  In the abstract,
	-- this involves determining how much physical memory is available
	-- and what storage devices are attached to the system.
	--
	method.survey = function(self)
		ram = self:measure_memory()
		disk = self:probe_for_disks()
	end

	--
	-- Refresh our view of the storage connected to the system.
	--
	-- Note that this may well create new objects to describe any
	-- new or existing Storage.Objects in the system.  Thus, this
	-- function accepts a list of current Storage objects which
	-- we desire to be retained, and returns a new list, with each
	-- item of the list being either an object or nil (indicating
	-- that no corresponding object could be found.)
	--
	-- Note that the list of objects should be in reverse dependency
	-- order.  For example, first list a disk, then list partitions
	-- that are on that disk.
	--
	method.resurvey = function(self, ...)
		local save = {}
		local i, obj, disk, part
		local ret = {}

		--
		-- Store identifiers for everything passed to use.
		--
		for i = 1, table.getn(arg) do
			obj = arg[i]
			if obj == nil then
				disk = nil
				part = nil
				table.insert(save, nil)
			elseif obj:is_a(Storage.Disk) then
				disk = obj
				table.insert(save, {
				    "disk",
				    disk:get_name()
				})
			elseif obj:is_a(Storage.Partition) then
				assert(disk ~= nil,
				  "Partition must be preceeded by a disk")
				part = obj
				table.insert(save, {
				    "part",
				    part:get_number()
				})
			else
				error("Arguments must be disks and partitions")
			end
		end

		--
		-- Re-survey the storage descriptor.
		--
		self:survey()

		--
		-- Restore pointers of everything passed to use.
		--
		for i = 1, table.getn(save) do
			obj = save[i]
			if obj == nil then
				table.insert(ret, nil)
			elseif obj[1] == "disk" then
				disk = self:get_disk_by_name(obj[2])
				table.insert(ret, disk or false)
			elseif obj[1] == "part" then
				if not disk then
					table.insert(ret, false)
				else
					part = disk:get_part_by_number(obj[2])
					table.insert(ret, part or false)
				end
			end
		end

		return unpack(ret)
	end

	--
	-- Return an iterator which yields the next Storage.Disk object
	-- in this Storage.System each time it is called (typically in a
	-- for loop.)
	--
	method.get_disks = function(self)
		local disk_name, dd
		local list = {}
		local i, n = 0, 0

		for disk_name, dd in pairs(disk) do
			table.insert(list, dd)
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
	-- Assemble a table of all mountpoints of the filesystem.
	--
	method.all_mountpoints = function(self)
		local tab = {}

		local cmd = App.expand("${root}${MOUNT}")
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, "could not open pty to mount"
		end

		local line = pty:readline()
		local found = true
		while line and found do
			local len, device, mtpt, fstype
			found, len, device, mtpt, fstype =
			    string.find(line, App.conf.mount_info_regexp)
			if found then
				table.insert(tab, {
				    device = device,
				    mountpoint = mtpt,
				    type = fstype
				})
			end
			line = pty:readline()
		end

		local retval = pty:close()
		if retval ~= 0 then
			return nil, "mount failed with return code " ..
			    tostring(retval)
		end

		return tab
	end

	--
	-- Return an iterator which yields the next mountpoint of the
	-- filesystem each time it is called (typically in a for loop.)
	-- Note that this is much different from the disk/partition/
	-- subpartition breakdown - although some of these mountpoints
	-- may be associated with subpartitions, others may be purely
	-- abstract beasts.
	--
	method.each_mountpoint = function(self)
		local tab, err = self:all_mountpoints()
		assert(tab, err)
		return ipairs(tab)
	end

	--
	-- Unmount all mountpoints under a given directory.  Recursively unmounts
	-- dependent mountpoints, so that unmount_all'ing /mnt will first unmount
	-- /mnt/usr/local, then /mnt/usr, then /mnt itself.
	--
	-- The third argument is generally not necessary when calling this function;
	-- it is used only when it recursively calls itself.
	--
	method.cmds_unmount_all_under = function(self, cmds, dirname, fs_descs)
		local unmount_me = false
		local i

		assert(dirname)

		if not fs_descs then
			fs_descs = self:all_mountpoints()
		end

		for i, fs_desc in ipairs(fs_descs) do
			if fs_desc.mountpoint == dirname then
				unmount_me = true
			end

			if string.sub(fs_desc.mountpoint, 1, string.len(dirname)) == dirname and
			   string.len(dirname) < string.len(fs_desc.mountpoint) then
				self:cmds_unmount_all_under(cmds, fs_desc.mountpoint, fs_descs)
			end
		end

		for i, pattern in ipairs(App.conf.offlimits_mounts) do
			if string.find(dirname, pattern) then
				App.log("Mount " .. dirname .. " is listed as off limits");
				unmount_me = false
			end
		end

		if unmount_me then
			cmds:add{
			    cmdline = "${root}${UMOUNT} ${dirname}",
			    replacements = { dirname = dirname }
			}
		end
	end

	--
	-- Given the name of a disk, return the Storage.Disk with that
	-- name, or nil if no disk by that name was found.
	--
	method.get_disk_by_name = function(self, name)
		local dd

		for dd in self:get_disks() do
			if dd:get_name() == name then
				return dd
			end
		end

		return nil
	end

	--
	-- Return the number of disks on this system.
	--
	method.get_disk_count = function(self)
		local dd
		local n = 0

		for dd in self:get_disks() do
			n = n + 1
		end

		return n
	end

	--
	-- Return the capacity of RAM (main memory, core, whatever)
	-- as a Storage.Capacity object.
	--
	method.get_ram_capacity = function(self)
		return Storage.Capacity.new(ram, "M")
	end

	--
	-- Return the total amount of swap (virtual) memory,
	-- as a Storage.Capacity object, activated on all disks
	-- on this system.
	--
	method.get_activated_swap = function(self)
		local pty, line
		local swap = 0
		local found, len, devname, amount

		local cmd = App.expand("${root}${SWAPINFO} -k")
		local pty = Pty.Logged.open(cmd, App.log_string)
		line = pty:readline()
		while line do
			if not string.find(line, "^Device") then
				found, len, devname, amount =
				    string.find(line, "^([^%s]+)%s+(%d+)")
				swap = swap + tonumber(amount)
			end
			line = pty:readline()
		end
		pty:close()

		return Storage.Capacity.new(swap, "K")
	end

	--
	-- Print the contents this Storage.System, for debugging purposes.
	--
	method.dump = function(self)
		local dd

		print("*** DUMP of Storage.System ***")
		for dd in self:get_disks() do
			dd:dump()
		end
	end

	--
	-- Return the name of the booted-from device, if detected.
	-- XXX This may not be the ideal place for this function.
	--
	method.get_bootdev = function(self)
		local line
		local bootmsgs_filename = App.expand("${root}${DMESG_BOOT}")
		if FileName.is_file(bootmsgs_filename) then
			for line in io.lines(bootmsgs_filename) do
				local found, len, fstype, device =
				    string.find(line, "^Mounting%s*root%s*from%s*(.+):(.+)%s*$")
				if found then
					return device
				end
			end
		else
			App.log_warn("couldn't open '%s'", bootmsgs_filename)
		end

		return nil
	end

	return method
end


--[[--------------]]--
--[[ Storage.Disk ]]--
--[[--------------]]--

--
-- This is the class of objects which represent the storage capability
-- of an single disk (or other mass-storage device which looks sufficiently
-- like a disk from the operating system's perspective.)
--

Storage.Disk = {}
Storage.Disk.new = function(parent, name)
	local method = {}	-- instance variable
	local capacity		-- private: basic capacity of this disk
	local part = {}		-- private: partitions on this disk
	local desc = name	-- private: description of disk
	local cyl, head, sec	-- private: geometry of disk
	local touched = false	-- private: whether we formatted it

	--
	-- Public methods: accessor methods.
	--

	--
	-- Determine the identity of this object.
	--
	method.is_a = function(self, class)
		return class == Storage.Disk
	end

	--
	-- Return the the Storage.System object that this
	-- Storage.Disk is contained in.
	--
	method.get_parent = function(self)
		return parent
	end

	--
	-- Return the (short, machine) name of this disk (e.g. 'ad0').
	--
	method.get_name = function(self)
		return name
	end

	--
	-- Change the description of this disk, but only if it is
	-- "better" than the current description.  Quality of the
	-- description is "human-readableness", as determined by a
	-- heuristic.
	--
	method.set_desc = function(self, new_desc)
		--
		-- Calculate a score for how well this string describes
		-- a disk.  Reject obviously bogus descriptions (usually
		-- erroneously harvested from error messages in dmesg.)
		--
		local calculate_score = function(s)
			local score = 0

			-- In the absence of any good discriminator,
			-- the longest disk description wins.
			score = string.len(s)

			-- Look for clues
			if string.find(s, "%d+MB") then
				score = score + 10
			end
			if string.find(s, "%<.*%>") then
				score = score + 10
			end
			if string.find(s, "%[%d+%/%d+%/%d+%]") then
				score = score + 10
			end

			-- Look for error messages
			if string.find(s, "resetting") then
				score = 0
			end

			return score
		end

		if calculate_score(new_desc) > calculate_score(desc) then
			desc = new_desc
		end
	end

	--
	-- Return the description of this disk.
	--
	method.get_desc = function(self)
		return desc
	end

	--
	-- Return the geometry of this disk.
	--
	method.get_geometry = function(self)
		return cyl, head, sec
	end

	method.get_geometry_cyl = function(self)
		return cyl
	end

	method.get_geometry_head = function(self)
		return head
	end

	method.get_geometry_sec = function(self)
		return sec
	end

	method.set_geometry = function(self, c, h, s)
		cyl = c or cyl
		head = h or head
		sec = s or sec
	end

	--
	-- Determine whether the geometry of the disk is "BIOS friendly"
	-- or not, i.e., whether the C/H/S parameters are within the
	-- limits of what is supported by legacy means of reading the
	-- disk (int 13h), which are required during e.g. booting.
	--
	-- This is needed because sometimes the system reports a
	-- geometry for an unformatted disk as having some number of
	-- sectors per track, such as 255, that will not permit booting
	-- from the BIOS.
	--
	-- XXX It is also possible that some systems require the number
	-- of heads to be 16 or less, especially if they are set to
	-- use "LBA" geometry translation.
	--
	method.is_geometry_bios_friendly = function(self)
		return sec >= 1 and sec <= 63
	end

	--
	-- Find a "BIOS-friendly" geometry that corresponds to the
	-- capacity of this disk, so we can at least boot from it.
	--
	method.get_normalized_geometry = function(self)
		--
		-- Convert to something that the BIOS can boot from.
		--
		local sec = 63
		local head = 255
		local cyl = math.floor(capacity:in_units("S") / (sec * head))

		return cyl, head, sec
	end

	--
	-- Return the name of the device which handles this disk.
	-- Note that, on all currently supported operating systems,
	-- this is the same as the disk's name, but that this should
	-- not be relied upon in general.
	--
	method.get_device_name = function(self)
		return name
	end

	--
	-- Return the name of the device which handles raw operations
	-- on this disk.
	--
	method.get_raw_device_name = function(self)
		if App.conf.has_raw_devices then
			return "r" .. name
		else
			return name
		end
	end

	--
	-- Return an iterator which yields the next Storage.Partition
	-- object in this Storage.Disk each time it is called (typically
	-- in a for loop.)
	--
	method.get_parts = function(self)
		local i, n = 0, table.getn(part)

		return function()
			if i <= n then
				i = i + 1
				return part[i]
			end
		end
	end

	--
	-- Given the number of a partition, return that
	-- partition descriptor, or nil if not found.
	--
	method.get_part_by_number = function(self, number)
		local pd

		for pd in self:get_parts() do
			if pd:get_number() == number then
				return pd
			end
		end

		return nil
	end

	--
	-- Return the number of partitions on this disk.
	--
	method.get_part_count = function(self)
		return table.getn(part)
	end

	--
	-- Return the sum of the capacities of the
	-- partitions on this disk, as a Storage.Capacity object.
	--
	method.get_parts_total_capacity = function(self)
		local pd
		local cap = 0

		for pd in self:get_parts() do
			cap = cap + pd:get_capacity():in_units("S")
		end

		return Storage.Capacity.new(cap, "S")
	end

	--
	-- Return the active partition, if found.
	--
	method.get_active_part = function(self)
		local pd

		for pd in self:get_parts() do
			if pd:is_active() then
				return pd
			end
		end

		return nil
	end

	--
	-- Return the disk's basic, unformatted capacity,
	-- as a Storage.Capacity.
	--
	method.get_capacity = function(self)
		return capacity
	end

	--
	-- Mark this disk as having been "touched", i.e. modified
	-- by the user.
	--
	method.touch = function(self)
		touched = true
	end

	--
	-- Detect if the disk has been touched.
	--
	method.has_been_touched = function(self)
		return touched
	end

	--
	-- Determine whether any subpartition from any partition of this
	-- disk is mounted somewhere in the filesystem.
	--
	method.is_mounted = function(self)
		local i, fs_desc

		local dev_name = self:get_device_name()
		for i, fs_desc in self:get_parent():each_mountpoint() do
			if string.find(fs_desc.device, dev_name, 1, true) then
				return true
			end
		end

		return false
	end

	--
	-- Public methods: manipulation methods.
	--

	--
	-- Remove all partitions from this disk.
	--
	method.clear_parts = function(self)
		part = {}
	end

	--
	-- Add a given partition to this disk.
	--
	method.add_part = function(self, pd)
		part[pd:get_number()] = pd
		-- pd:set_parent(self)
	end

	--
	-- Get (possibly non-useful) descriptions of disks from
	-- the system messages that were logged at boot-time.
	--
	method.describe_from_boot_messages = function(self)
		local bootmsgs_filename = App.expand("${root}${DMESG_BOOT}")
		local line

		if FileName.is_file(bootmsgs_filename) then
			for line in io.lines(bootmsgs_filename) do
				local found, len, cap =
				    string.find(line, "^" .. self:get_name() .. ":%s*(.*)$")
				if found then
					self:set_desc(self:get_name() .. ": " .. cap)
				end
			end
		else
			App.log_warn("couldn't open '%s'", bootmsgs_filename)
		end
	end

	--
	-- Get (possibly better) descriptions of disks from the output
	-- command(s) which probe the appropriate buses (ATA / SCSI).
	-- (Currently only ATA is supported.)
	--
	method.describe_from_hardware_probe = function(self)
		local cmd = App.expand("${root}${ATACONTROL} list")
		local pty = Pty.Logged.open(cmd, App.log_string)
		local disk_name = self:get_name()
		if pty then
			local line = pty:readline()
			while line do
				local found, len, cap =
				    string.find(line, "^%s*Master:%s*" ..
				      disk_name .. "%s*(%<.*%>)$")
				if not found then
					found, len, cap =
					    string.find(line, "^%s*Slave:%s*" ..
					      disk_name .. "%s*(%<.*%>)$")
				end
				if found then
					self:set_desc(disk_name .. ": " .. cap)
					break
				end
				line = pty:readline()
			end
			pty:close()
		else
			App.log_warn("couldn't open pty to '%s'", cmd)
		end
	end

	--
	-- Public methods: Methods to add appropriate commands to CmdChains.
	--

	--
	-- Create commands that ensure that the device node for
	-- this disk exists.
	--
	method.cmds_ensure_dev = function(self, cmds)
		if App.conf.os.name ~= "FreeBSD" then
			cmds:add{
			    cmdline = "cd ${root}dev && ${root}${TEST_DEV} ${dev} || " ..
				      "${root}${SH} MAKEDEV ${dev}",
			    replacements = {
				dev = FileName.basename(self:get_device_name())
			    }
			}
		end
	end

	--
	-- Create commands to format this disk.  'Format' in this sense
	-- means to create one big partition, and to install a partition
	-- table with the appropriate information in it.
	--
	method.cmds_format = function(self, cmds)

		self:cmds_ensure_dev(cmds)

		--
		-- Initialize the disk.
		--
		cmds:add("${root}${FDISK} -I " ..
		    self:get_raw_device_name())

		--
		-- Under more pleasant conditions, we could just
		-- shell 'fdisk -BI' here and be done with it.
		-- However, life is not that simple.  In order to
		-- get fdisk to honour the geometry we have
		-- selected, we need to create a fdisk script which
		-- tells fdisk exactly the geometry of the disk and
		-- the size of the partition we'd like to make.
		--
		cmds:add{
		    cmdline = "${root}${ECHO} 'g c${cyl} h${head} s${sec}' >${tmp}format.fdisk",
		    replacements = {
			cyl = cyl,
			head = head,
			sec = sec
		    }
		}

		--
		-- Allot the first partition as taking up the entire disk,
		-- assuming the given geometry.  This means that the part:
		-- * has the default sysid (depends on the operating system);
		-- * starts at the first track (after the zero'th track,)
		-- * extends to the end of the disk.
		--
		cmds:add{
		    cmdline = "${root}${ECHO} 'p 1 ${sysid} ${start} ${size}' >>${tmp}format.fdisk",
		    replacements = {
			sysid = App.conf.default_sysid,
			start = sec,
			size = (cyl * head * sec) - sec
		    }
		}

		--
		-- Mark the other partitions as unused.
		-- Mark the first partition as the active one.
		-- Send a copy of this script to the log, and make sure
		-- the system knows that it should delete it when done.
		--
		cmds:add(
		    "${root}${ECHO} 'p 2 0 0 0' >>${tmp}format.fdisk",
		    "${root}${ECHO} 'p 3 0 0 0' >>${tmp}format.fdisk",
		    "${root}${ECHO} 'p 4 0 0 0' >>${tmp}format.fdisk",
		    "${root}${ECHO} 'a 1' >>${tmp}format.fdisk",
		    "${root}${CAT} ${tmp}format.fdisk"
		)
		App.register_tmpfile("format.fdisk")

		--
		-- Execute the fdisk script.
		--
		if App.conf.os.name == "NetBSD" then -- XXXXXX
			-- XXX Going to need to do this differently
		else
			cmds:add("${root}${FDISK} -v -f ${tmp}format.fdisk " ..
			    self:get_device_name())
		end

		--
		-- Show the new state of the disk in the log.
		--
		cmds:add("${root}${FDISK} " ..
		    self:get_device_name())

		--
		-- Make the disk bootable.
		--
		cmds:add("${root}${YES} | ${root}${FDISK} -B " ..
		    self:get_raw_device_name())
	end

	--
	-- Create commands to partition this disk.
	--
	method.cmds_partition = function(self, cmds)
		local i, pd
		local active_part
		local cyl, head, sec = self:get_geometry()

		self:cmds_ensure_dev(cmds)

		cmds:add({
		    cmdline = "${root}${ECHO} 'g c${cyl} h${head} s${sec}' >${tmp}new.fdisk",
		    replacements = {
			cyl = cyl,
			head = head,
			sec = sec
		    }
		})

		i = 1
		while i <= 4 do
			local sysid, start, size = 0, 0, 0

			pd = self:get_part_by_number(i)
			if pd then
				sysid = pd:get_sysid()
				start = pd:get_start()
				size  = pd:get_capacity():in_units("S")
				if pd:is_active() then
					active_part = pd
				end
			end

			cmds:add({
			    cmdline = "${root}${ECHO} 'p ${number} ${sysid} ${start} ${size}' >>${tmp}new.fdisk",
			    replacements = {
				number = i,
				sysid = sysid,
				start = start,
				size = size
			    }
			})

			i = i + 1
		end

		if active_part then
			cmds:add({
			    cmdline = "${root}${ECHO} 'a ${number}' >>${tmp}new.fdisk",
			    replacements = {
				number = active_part:get_number()
			    }
			})
		end

		cmds:add("${root}${CAT} ${tmp}new.fdisk")

		App.register_tmpfile("new.fdisk")

		--
		-- Execute the fdisk script.
		--
		if App.conf.os.name == "NetBSD" then -- XXXXXX
			-- XXX Going to need to do this differently
		else
			cmds:add("${root}${FDISK} -v -f ${tmp}new.fdisk " ..
			    self:get_device_name())
		end

		--
		-- Show the new state of the disk in the log.
		--
		cmds:add("${root}${FDISK} " ..
		    self:get_device_name())
	end

	--
	-- Create commands to install a bootblock on this disk.
	--
	method.cmds_install_bootblock = function(self, cmds, packet_mode)
		local o = " "
		local s = " "

		if packet_mode then
			o = "-o packet "
		end
		local active_pd = self:get_active_part()
		if active_pd then
			s = "-s " .. tostring(active_pd:get_number()) .. " "
		else
			s = "-s 1 "
		end

		cmds:add(
		    {
			cmdline = "${root}${BOOT0CFG} -B " ..
			    o .. s .. "/dev/" .. self:get_raw_device_name(),
			failure = CmdChain.FAILURE_WARN,
			tag = self
		    },
		    {
			cmdline = "${root}${BOOT0CFG} -v /dev/" ..
			    self:get_raw_device_name(),
			failure = CmdChain.FAILURE_WARN,
			tag = self
		    }
		)
	end

	--
	-- Create commands to wipe the start of this disk.
	--
	method.cmds_wipe_start = function(self, cmds)
		self:cmds_ensure_dev(cmds)
		cmds:add("${root}${DD} if=${root}dev/zero of=${root}dev/" ..
		    self:get_raw_device_name() .. " bs=32k count=16")
	end

	--
	-- Create commands to initialize the disklabel for this disk
	-- and to write out this initial disklabel to a temp file.
	-- This only applies to operating systems which have only one
	-- disklabel for the entire disk (NetBSD and OpenBSD.)
	--
	method.cmds_initialize_disklabel = function(self, cmds)
		assert(App.conf.disklabel_on_disk)

		self:cmds_ensure_dev(cmds)

		cmds:set_replacements{
		    dev = self:get_device_name()
		}

		--
		-- Auto-disklabel the slice and make a record of the
		-- fresh new disklabel we just applied.
                -- XXX we may need to wipe this, if there is an
                -- old NetBSD or OpenBSD disklabel hanging around.
		--
		cmds:add("${root}${MBRLABEL} -r -w ${dev}")
                cmds:add{
                    cmdline = "${root}${DISKLABEL} -r ${dev} " ..
                              ">${tmp}install.disklabel.${devicename}",
		    failure_mode = CmdChain.FAILURE_IGNORE

		}
		cmds:set_replacements{
			devicename = self:get_escaped_device_name()
		}
	end

	--
	-- Create commands to unmount all the mountpoints
	-- which reside on this disk.
	--
	method.cmds_unmount_all_under = function(self, cmds)
		local i, fs
		local pattern = "%/" .. self:get_device_name()
		for i, fs in self:get_parent():each_mountpoint() do
			if string.find(fs.device, pattern) then
				self:get_parent():cmds_unmount_all_under(
				    cmds, fs.mountpoint
				)
			end
		end
	end

	--
	-- Print contents of disk descriptor, for debugging.
	--
	method.dump = function(self)
		local pd

		print("\t" .. name .. ": " .. cyl .. "/" .. head .. "/" .. sec .. ": " .. desc)
		for pd in self:get_parts() do
			pd:dump()
		end
	end

	--
	-- Probe the (BIOS) geometry of the disk.
	-- Returns three values: cylinder, head, and sec/trk, if all went well.
	-- Returns nil values if all did not go well.
	-- Capacity in sectors can then be calculated by C * H * S, or an error
	-- can be flagged, by the caller of this function.
	--
	-- This is the FreeBSD/DragonFly version of this function.
	--
	local probe_geometry_freebsd = function(self)
		local cyl, head, sec

		--
		-- Tell 'fdisk' to give us the rundown of the disk.
		-- Note that this does not use the 'summary' (-s)
		-- feature of fdisk, because that feature has markedly
		-- different behaviour when the disk is blank or
		-- otherwise has an invalid boot sector: it fails
		-- immediately.  Whereas we need it to provide at least
		-- the geometry (even if the part table is ficticious.)
		--
		local cmd = App.expand(
		    "${root}${FDISK} " .. self:get_raw_device_name()
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, string.format("could not open pty to '%s'", cmd)
		end
		local line = pty:readline()
		while line do
			if string.find(line, "^%s*parameters to be used for BIOS") then
				--
				-- Parse the line following.
				--
				line = pty:readline()
				found, len, cyl, head, sec =
				    string.find(line, "^%s*cylinders=(%d+)%s*heads=(%d+)%s*" ..
						      "sectors/track=(%d+)")
				if found then
					cyl = tonumber(cyl)
					head = tonumber(head)
					sec = tonumber(sec)
				end
				line = pty:readline()
			else
				--
				-- Keep looking...
				--
				line = pty:readline()
			end
		end
		pty:close()

		return cyl, head, sec
	end

	--
	-- Probe the (BIOS) geometry of the disk.
	-- Returns three values: cylinder, head, and sec/trk, if all went well.
	-- Returns nil values if all did not go well.
	-- Capacity in sectors can then be calculated by C * H * S, or an error
	-- can be flagged, by the caller of this function.
	--
	-- This is the NetBSD version of this function.
	--
	local probe_geometry_netbsd = function(self)
		local cyl, head, sec

		--
		-- Call 'fdisk' and parse its output.
		--
		local cmd = App.expand(
		    "${root}${FDISK} " .. self:get_device_name()
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, string.format("could not open pty to '%s'", cmd)
		end
		local line = pty:readline()
		while line do
			local found_bios_geom =
			    string.find(line, "^%s*NetBSD%s*disklabel%s*disk%s*geometry")
			line = pty:readline()
			if found_bios_geom then
				--
				-- Parse the line following.
				--
				found, len, cyl, head, sec =
				    string.find(line, "^%s*cylinders:%s*(%d+),%s*heads:%s*(%d+),%s*" ..
						      "sectors/track:%s*(%d+)")
				cyl = tonumber(cyl)
				head = tonumber(head)
				sec = tonumber(sec)
				found_bios_geom = false
				line = pty:readline()
			end
		end
		pty:close()

		return cyl, head, sec
	end

	local probe_partitions_freebsd = function(self)
		--
		-- Get the partitions from 'fdisk -s'.
		--
		local tab = {}
		local cmd = App.expand(
		    "${root}${FDISK} -s " .. self:get_device_name()
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, string.format("could not open pty to '%s'", cmd)
		end
		local line = pty:readline()  -- geometry - we already have it
		line = pty:readline()	-- headings, just ignore
		line = pty:readline()
		while line do
			local found, len, part_no, start, size, sysid, flags =
			    string.find(line, "^%s*(%d+):%s*(%d+)%s*(%d+)" ..
					      "%s*0x(%x+)%s*0x(%x+)%s*$")
			if found then
				part_no = tonumber(part_no)
				flags = tonumber(flags, 16)

				tab[part_no] = Storage.Partition.new{
				    parent = self,
				    number = part_no,
				    start  = tonumber(start),
				    size   = tonumber(size),
				    sysid  = tonumber(sysid, 16),
				    active = (Bitwise.bw_and(flags, 128) == 128)
				}
			end
			line = pty:readline()
		end
		pty:close()

		return tab
	end

	local probe_partitions_netbsd = function(self)
		--
		-- Get the partitions from 'fdisk'.
		--
		local tab = {}
		local cmd = App.expand(
		    "${root}${FDISK} " .. self:get_device_name()
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil, string.format("could not open pty to '%s'", cmd)
		end

		--
		-- Read lines from fdisk's output until we find
		-- the 'Partition table:' line.
		--
		local line = pty:readline()
		local found_part_table = false
		while line and not found_part_table do
			found_part_table = string.find(line, "^Partition table:")
			line = pty:readline()
		end
		if not found_part_table then
			return nil, string.format("couldn't find partition table in fdisk output")
		end

		local part_no, start, size, sysid, active
		local flush_part = function()
			--
			-- If we have accumulated information about a partition,
			-- write it to our table of partitions before continuing
			-- to the next partition and/or before leaving function.
			--
			if part_no then
				if start and size and sysid and type(active) == "boolean" then
					tab[part_no] = Storage.Partition.new{
					    parent = self,
					    number = part_no,
					    start  = tonumber(start),
					    size   = tonumber(size),
					    sysid  = tonumber(sysid),
					    active = active
					}
					start = nil
					size = nil
					sysid = nil
					active = nil
				else
					App.log_warn("Couldn't find all information for " ..
						"partition #%d", part_no)
				end
			end
		end

		--
		-- Read through the rest of the lines, accumulating information
		-- about each partition.
		--
		while line do
			--
			-- Check to see if this line is a partition line.
			-- If so, flush any previous partition information
			-- to the table, and start on this partition by
			-- extracting the partition # and sysid.
			--
			local found, len, num, new_sysid =
			    string.find(line, "^(%d+):.*%(sysid%s*(%d+)%)")
			if found then
				flush_part()
				part_no = num + 1
				sysid = new_sysid
			end

			--
			-- Check to see if this line is a start/size desc.
			--
			local found, len, new_start, new_size =
			    string.find(line, "^%s*start%s*(%d+),%s*size%s*(%d+)")
			if found then
				start = new_start
				size = new_size
				active = (string.find(line, "Active") and true) or false
			end

			--
			-- Otherwise, line could be a bootmenu line, or
			-- whatever.  Ignore it, keep going.
			--
			line = pty:readline()
		end
		pty:close()

		--
		-- Add any last partition we might have seen to our
		-- partition table.
		--
		flush_part()

		return tab
	end

	--
	-- Try to find out what we can about ourselves from the system
	-- utilities (such as fdisk.)
	-- Returns true if successful, false if not.
	--
	method.survey = function(self)
		App.log("Surveying Disk: " .. name .. " ...")

		self:describe_from_boot_messages()
		self:describe_from_hardware_probe()

		local success
		if App.conf.os.name == "NetBSD" then -- XXXXXX netbsd needs its own subclass here
			cyl, head, sec =
			    probe_geometry_netbsd(self)
		else
			cyl, head, sec =
			    probe_geometry_freebsd(self)
		end

		if not cyl or not head or not sec then
			App.log_warn(
			    "could not determine geometry of disk '%s'", name
			)
			return false
		end

		--
		-- Calculate disk's capacity from its geometry.
		--
		capacity = Storage.Capacity.new(cyl * head * sec, "S")

		App.log("Disk " .. name .. " (" .. desc .. "): " ..
			capacity:format() .. ": " ..
			cyl .. "/" .. head .. "/" .. sec)

		if App.conf.os.name == "NetBSD" then -- XXX netbsd needs its own subclass here
			part = probe_partitions_netbsd(self)
		else
			part = probe_partitions_freebsd(self)
		end

		local i, p
		for i, p in ipairs(part) do
			p:survey()
		end

		return true
	end

	--
	-- 'Constructor' - just return the instance object.
	-- Note that this does not automatically call self:survey().
	--
	return method
end

--[[-------------------]]--
--[[ Storage.Partition ]]--
--[[-------------------]]--

--
-- This is the class of objects which represent the storage capabilities
-- of individual (BIOS) partitions on disks.
--

Storage.Partition = {}
Storage.Partition.new = function(params)
	local method = {}	-- instance variable
	local subpart = {}	-- subpartitions on this partition

	local parent = assert(params.parent)
	local number = assert(params.number)
	local start  = assert(params.start)
	local size   = assert(params.size)
	local sysid  = assert(params.sysid)
	local active = params.active or false
	assert(params.flags == nil, "The 'flags' initializer is deprecated")

	--
	-- Public methods: accessor methods.
	--

	--
	-- Determine the identity of this object.
	--
	method.is_a = function(self, class)
		return class == Storage.Partition
	end

	--
	-- Return the Storage.Disk object that contains this.
	--
	method.get_parent = function(self)
		return parent
	end

	--
	-- Return the partition number; which of the (four) BIOS
	-- partitions of the disk, that this partition is.
	--
	method.get_number = function(self)
		return number
	end

	--
	-- Return the partition's start, as an offset in sectors from
	-- the start of the disk.
	--
	method.get_start = function(self)
		return start
	end

	--
	-- Return the partition's type ("sysid").
	--
	method.get_sysid = function(self)
		return sysid
	end

	--
	-- Return the partition's capacity as a Storage.Capacity object.
	--
	method.get_capacity = function(self)
		return Storage.Capacity.new(size, "S")
	end

	--
	-- Return whether the partition is active or not.
	--
	method.is_active = function(self)
		return active
	end

	--
	-- Return a reasonably human-readable description of
	-- the partition.
	--
	method.get_desc = function(self)
		return tostring(number) .. ": " ..
		    Storage.Capacity.new(size, "S"):format() .. " (" ..
		    tostring(start) .. "-" .. tostring(start + size) ..
		    ") id=" .. sysid
	end

	--
	-- Return the name of the device node for this partition.
	--
	method.get_device_name = function(self)
		if App.conf.disklabel_on_disk then
			return parent:get_device_name()
		end
		return parent.get_name() .. "s" .. number
	end

	--
	-- Return the name of the device node used for raw operations
	-- on this partition.
	--
	method.get_raw_device_name = function(self)
		if App.conf.disklabel_on_disk then
			return parent:get_raw_device_name()
		end
		-- XXX depends on operating system
		return parent.get_name() .. "s" .. number -- .. "c"
	end

	--
	-- Return the total amount of swap (virtual) memory,
	-- as a Storage.Capacity object, activated on this partition.
	--
	method.get_activated_swap = function(self)
		local swap = 0
		local found, len, devname, amount

		local cmd = App.expand("${root}${SWAPINFO} -k")
		local pty = Pty.Logged.open(cmd, App.log_string)
		local line = pty:readline()
		while line do
			if not string.find(line, "^Device") then
				found, len, devname, amount =
				    string.find(line, "^([^%s]+)%s+(%d+)")
				if string.find(devname, self:get_device_name()) then
					swap = swap + tonumber(amount)
				end
			end
			line = pty:readline()
		end
		pty:close()

		return Storage.Capacity.new(swap, "K")
	end

	--
	-- Return an iterator which yields the next Storage.Partition
	-- object in this Storage.Disk each time it is called (typically
	-- in a for loop.)
	--
	method.get_subparts = function(self)
		local letter, spd
		local list = {}
		local i, n = 0, 0

		for letter, spd in pairs(subpart) do
			table.insert(list, spd)
		end

		table.sort(list, function(a, b)
			-- not sure why we ever get a nil here, but we do:
			if not a and not b then return false end

			return a:get_letter() < b:get_letter()
		end)

		n = table.getn(list)

		return function()
			if i <= n then
				i = i + 1
				return list[i]
			end
		end
	end

	--
	-- Return a particular subpartition, given its letter.
	-- Return nil if none could be found.
	--
	method.get_subpart_by_letter = function(self, letter)
		return subpart[letter]
	end

	--
	-- Return a particular subpartition, given its mountpoint.
	-- Return nil if none could be found.
	--
	method.get_subpart_by_mountpoint = function(self, mountpoint)
		local spd

		for spd in self:get_subparts() do
			if spd:get_mountpoint() == mountpoint then
				return spd
			end
		end

		return nil
	end

	--
	-- Return a particular subpartition, given its device name.
	-- Return nil if none could be found.
	--
	method.get_subpart_by_device_name = function(self, device_name)
		local spd

		-- Strip any leading /dev/ or whatever.
		device_name = FileName.basename(device_name)

		for spd in self:get_subparts() do
			if spd:get_device_name() == device_name then
				return spd
			end
		end

		return nil
	end

	--
	-- Get friendly device name which has / replaced
	-- with a _.  This allows for a friendly name that
	-- is compatible with fstab and other items such as
	-- filenames on the system
	--
	method.get_escaped_device_name = function(self)
		local dev_name = self:get_device_name()
		return string.gsub(dev_name, "/", "_")
	end

	--
	-- Determine whether any subpartition of this
	-- partition is mounted somewhere in the filesystem.
	--
	method.is_mounted = function(self)
		local i, fs_desc

		local dev_name = self:get_device_name()
		for i, fs_desc in self:get_parent():get_parent():each_mountpoint() do
			if string.find(fs_desc.device, dev_name, 1, true) then
				return true
			end
		end

		return false
	end

	--
	-- Public methods: manipulator methods.
	--

	--
	-- Remove all subpartition descriptors from this partition.
	--
	method.clear_subparts = function(self)
		subpart = {}
	end

	--
	-- Add a subpartition to this partition.
	--
	method.add_subpart = function(self, spd)
		subpart[spd:get_letter()] = spd
		-- spd:set_parent(self)
	end

	--
	-- Public methods: Methods to add appropriate commands to CmdChains.
	--

	--
	-- NOTE: NetBSD and OpenBSD don't have device nodes for partitions,
	-- so many of these methods are just null stubs for those platforms.
	--

	--
	-- Create commands that ensure that the device node for this
	-- partition exists.
	--
	method.cmds_ensure_dev = function(self, cmds)
		if App.conf.disklabel_on_disk then
			return
		end
		if App.conf.os.name ~= "FreeBSD" then
			cmds:add{
			    cmdline = "cd ${root}dev && ${root}${TEST_DEV} ${dev} || " ..
				      "${root}${SH} MAKEDEV ${dev}",
			    replacements = {
				dev = FileName.basename(self:get_device_name())
			    }
			}
		end
	end


	--
	-- Create commands to set the sysid (type) of this partition.
	--
	method.cmds_set_sysid = function(self, cmds, sysid)
		if App.conf.os.name == "NetBSD" or App.conf.os.name == "OpenBSD" then -- XXXXXX
			-- XXX we shouldn't really here, but for now we do
			-- XXX going to need to subclass this I suspect
			return
		end

		self:cmds_ensure_dev(cmds)

		--
		-- The information in parent NEEDS to be accurate here!
		-- Presumably we just did a survey_storage() recently.
		--

		local cyl, head, sec = parent:get_geometry()

		cmds:set_replacements{
		    cyl = cyl,
		    head = head,
		    sec = sec,
		    number = self:get_number(),
		    sysid = sysid,
		    start = start,
		    size = size,
		    dev = self:get_raw_device_name(),
		    parent_dev = parent:get_device_name()
		}

		cmds:add(
		    "${root}${FDISK} ${parent_dev}",
		    "${root}${ECHO} 'g c${cyl} h${head} s${sec}' >${tmp}new.fdisk",
		    "${root}${ECHO} 'p ${number} ${sysid} ${start} ${size}' >>${tmp}new.fdisk"
		)

		--
		-- Work around an apparent fdisk silliness: if no 'a #'
		-- line is given in the fdisk script, all partitions are
		-- set to be inactive, instead of leaving the current
		-- situation unchanged (like you might reasonably expect...)
		--
		local active_part = parent:get_active_part()
		if active_part then
			cmds:add{
			    cmdline = "${root}${ECHO} 'a ${number}' >>${tmp}new.fdisk",
			    replacements = {
			        number = active_part:get_number()
			    }
			}
		end

		App.register_tmpfile("new.fdisk")

		--
		-- Dump the fdisk script to the log for debugging,
		-- execute it, and record the results.
		--
		cmds:add(
		    "${root}${CAT} ${tmp}new.fdisk",
		    "${root}${FDISK} -v -f ${tmp}new.fdisk ${parent_dev}",
		    "${root}${FDISK} ${parent_dev}"
		)
	end

	--
	-- Create commands to initialize the disklabel for this partition
	-- and to write out this initial disklabel to a temp file.
	--
	method.cmds_initialize_disklabel = function(self, cmds)
		if App.conf.disklabel_on_disk then
			--
			-- For these OSes, the disklabel is for the
			-- entire disk, not just a BIOS partition, so
			-- we detour to the cmds_initialize_disklabel
			-- method of the parent Storage.Disk object.
			--
			return parent:cmds_initialize_disklabel(cmds)
		end

		self:cmds_ensure_dev(cmds)

		--
		-- Auto-disklabel the slice and make a record of the
		-- fresh new disklabel we just applied.
		--
		cmds:set_replacements{
		    dev = self:get_raw_device_name(),
                    escaped_dev = self:get_escaped_device_name()
		}
		cmds:add(
		    "${root}${DISKLABEL} -B -r -w ${dev} auto",
		    "${root}${DISKLABEL} -r ${dev} >${tmp}install.disklabel.${escaped_dev}"
		)
	end

	--
	-- Create commands to write a disklabel to this partition.
	--
	-- Note that for NetBSD and OpenBSD, this actually writes
	-- the disklabel to the overlying disk.
	--
	method.cmds_disklabel = function(self, cmds)

		if App.conf.disklabel_on_disk then
			-- ${dev} refers to disk
			cmds:set_replacements{
			    dev = self:get_parent():get_device_name(),
			    num_subparts = tostring(App.conf.num_subpartitions),
			    devicename = self:get_escaped_device_name()
			}
		else
			-- ${dev} refers to partition
			cmds:set_replacements{
			    dev = self:get_device_name(),
			    num_subparts = tostring(App.conf.num_subpartitions),
			    devicename = self:get_escaped_device_name()
			}
		end

		--
		-- Weave together a new disklabel out the of the initial
		-- disklabel, and the user's subpartition choices.
		--

		--
		-- Take everything from the initial disklabel up until the
		-- '8 or 16 partitions' line, which looks like:
		--
		-- 8 or 16 partitions:
		-- #        size   offset    fstype   [fsize bsize bps/cpg]
		-- c:  2128833        0    unused        0     0       	# (Cyl.    0 - 2111*)
		--
		cmds:add(
		    "${root}${AWK} '$2==\"partitions:\" || cut { cut = 1 } !cut { print $0 }' " ..
		      "<${tmp}install.disklabel.${devicename} >${tmp}install.disklabel",
		    "${root}${ECHO} '${num_subparts} partitions:' >>${tmp}install.disklabel",
		    "${root}${ECHO} '#        size   offset    fstype   [fsize bsize bps/cpg]' " ..
		      ">>${tmp}install.disklabel"
		)

		--
		-- Write a line for each subpartition the user wants.
		--

		--
		-- Local function to output the "unused" subpartitions
		-- which act as magic "windows" to the slice (and the
		-- disk, on NetBSD.)
		--
		local already_copied_window_subparts = false
		local flush_window_subparts = function()
			if already_copied_window_subparts then
				return
			end
			--
			-- Copy the lines which represent 'window'
			-- subpartitions - that is, subpartitions which
			-- expose the entire slice ('c:') and/or disk
			-- ('d:' on Net/OpenBSD) from the initial disklabel
			-- into the new disklabel we are making.
			--
			local pattern =
			    "^[[:space:]]*["				..
			    table.concat(App.conf.window_subpartitions)	..
			    "][[:space:]]*:"
			cmds:add{
			    cmdline =
				"${root}${GREP} -E '${pattern}' "	..
				"${tmp}install.disklabel.${devicename} "	..
				">>${tmp}install.disklabel",
					replacements = {
						pattern = pattern
					}
			}
			cmds:set_replacements{
				devicename = self:get_escaped_device_name()
			}
			already_copied_window_subparts = true
		end

		--
		-- On FreeBSD/DragonFly, the start of the label is relative
		-- to the slice, so it starts at 0; on NetBSD/OpenBSD, it is
		-- relative to the disk, so it starts where the slice starts.
		--
		-- DragonFlyBSD starts at an offset of 0, whereas bsdlabel on
		-- FreeBSD suggests a starting point of 16.   Net/Open starts
		-- at an offset of 32.
		--
		local offset = 0
		local starting_offset = 0
		if App.conf.os.name == "FreeBSD" then
			starting_offset = 16
			offset = 16
		end
		if App.conf.os.name == "OpenBSD" then
                        starting_offset = 32
                        offset = 32
		end
		if App.conf.os.name == "NetBSD" then
                        starting_offset = 32
                        offset = 32
		end
		if App.conf.disklabel_on_disk then
			offset = self:get_start()
		end

		--
		-- On NetBSD, subpartition "d" is the last "window" subpart.
		--
		local last_window_subpart =
		    App.conf.window_subpartitions[
		      table.getn(App.conf.window_subpartitions)
		    ]

		local spd
		for spd in self:get_subparts() do
			if spd:get_letter() > last_window_subpart then
				flush_window_subparts()
			end

			local spd_size = spd:get_capacity():in_units("S")
			if starting_offset > 0 then
				-- adjust the starting offset
				spd_size = spd_size - starting_offset
				-- only change a:
				starting_offset = 0
			end
			cmds:set_replacements{
			    letter = spd:get_letter(),
			    fsize = spd:get_fsize(),
			    bsize = spd:get_bsize(),
			    size = tostring(spd_size)
			}

			cmds:set_replacements{
			    offset = tostring(offset)
			}

			if spd:is_swap() then
				cmds:add("${root}${ECHO} '  ${letter}:\t${size}\t${offset}\tswap' >>${tmp}install.disklabel")
			else
				cmds:add("${root}${ECHO} '  ${letter}:\t${size}\t${offset}\t4.2BSD\t${fsize}\t${bsize}\t99' >>${tmp}install.disklabel")
			end

			--
			-- Move offset to the start of the next subpartition.
			--
			offset = offset + spd_size
		end

		flush_window_subparts()

		--
		-- Dump disklabel to log, for debugging.
		--
		cmds:add("${root}${CAT} ${tmp}install.disklabel")

		App.register_tmpfile("install.disklabel")

		--
		-- Label the partition (or disk, as appropriate) from the
		-- disklabel that we just wove together.
		--
		-- Then create a snapshot of the disklabel we just created
		-- for debugging inspection in the log.
		--
		cmds:add(
		    "${root}${DISKLABEL} -R -r ${dev} ${tmp}install.disklabel",
		    "${root}${DISKLABEL} ${dev}"
		)
	end

	--
	-- Create commands to newfs (initialize) the filesystems on
	-- the subpartitions that are described by the disklabel.
	--
	method.cmds_initialize_filesystems = function(self, cmds)

		self:get_parent():cmds_ensure_dev(cmds)
		self:cmds_ensure_dev(cmds)

		for spd in self:get_subparts() do
			if not spd:is_swap() then
				spd:cmds_ensure_dev(cmds)
				if spd:is_softupdated() and App.conf.has_softupdates then
					cmds:add("${root}${NEWFS} -U ${root}dev/" ..
					    spd:get_device_name(),
                                                 "${root}${TUNEFS} -j enable ${root}dev/" ..
                                            spd:get_device_name())
				else
					cmds:add("${root}${NEWFS} -U -j ${root}dev/" ..
					    spd:get_device_name())
				end
			end
		end
	end

	--
	-- Create commands to wipe the start of this partition.
	--
	method.cmds_wipe_start = function(self, cmds)
		self:cmds_ensure_dev(cmds)
		cmds:add(
		    "${root}${DD} if=${root}dev/zero of=${root}dev/" ..
		    self:get_raw_device_name() .. " bs=32k count=16"
		)
	end

	--
	-- Create commands to make this partition bootable.
	--
	method.cmds_install_bootstrap = function(self, cmds)
		if App.conf.os.name == "NetBSD" then -- XXXXXX
			return
		end

		self:cmds_ensure_dev(cmds)

		--
		-- NB: one cannot use "/dev/adXsY" here -
		-- it must be in the form "adXsY".
		--
		cmds:add(
		    "${root}${DISKLABEL} -B " ..
		    self:get_device_name()
		)
		return cmds
	end

	method.cmds_unmount_all_under = function(self, cmds)
		local i, fs
		local pattern = "%/" .. self:get_device_name()
		local sys = self:get_parent():get_parent()
		for i, fs in sys:each_mountpoint() do
			if string.find(fs.device, pattern) then
				sys:cmds_unmount_all_under(
				    cmds, fs.mountpoint
				)
			end
		end
	end

	--
	-- Print contents of partition descriptor, for debugging.
	--
	method.dump = function(self)
		local letter, spd

		local active_str = (active and "Active") or "Inactive"
		print("\t\tPartition " .. number .. ": " ..
		    start .. "," .. size .. ":" .. sysid .. "/" ..
		    active_str)
		for spd in self:get_subparts() do
			spd:dump()
		end
	end

	--
	-- Fill out this partition descriptor with real info.
	-- If it looks like a BSD partition, try to probe it with
	-- disklabel to get an idea of the subpartitions on it.
	--
	method.survey = function(self)

		App.log("Surveying Partition: " .. number .. ": " ..
		    start .. "," .. size .. ":" .. sysid .. "/" .. tostring(active))

		if sysid == 165 then
			local len
			local devicename = self:get_escaped_device_name()
			local cmd = App.expand("${root}${DISKLABEL} " .. devicename ..
			    "s" .. number)
			local pty = Pty.Logged.open(cmd, App.log_string)
			local line = pty:readline()
			local found = false
			while line and not found do
				found = string.find(line, "^%d+%s+partitions:")
				line = pty:readline()
			end
			if found then
				local letter, size, offset, fstype, fsize, bsize
				while line do
					found, len, letter, size, offset, fstype,
					    fsize, bsize = string.find(line,
					    "^%s*(%a):%s*(%d+)%s*(%d+)%s*([^%s]+)")
					if found then
						fsize, bsize = 0, 0
						if fstype == "4.2BSD" then
							found, len, letter, size,
							    offset, fstype, fsize,
							    bsize = string.find(line,
							    "^%s*(%a):%s*(%d+)%s*" ..
							    "(%d+)%s*([^%s]+)%s*" ..
							    "(%d+)%s*(%d+)")
						end
						subpart[letter] =
						    Storage.Subpartition.new{
							parent = self,
							letter = letter,
							size = size,
							offset = offset,
							fstype = fstype,
							fsize = fsize,
							bsize = bsize
						    }
						subpart[letter]:survey()
					end
					line = pty:readline()
				end
			end

			pty:close()
		end
	end

	--
	-- 'Constructor' - just return the instance object.
	-- Note that this does not automatically call self:survey().
	--
	return method
end

--[[----------------------]]--
--[[ Storage.Subpartition ]]--
--[[----------------------]]--

--
-- This is the class of objects which represent the storage capabilities
-- of individual (BSD) subpartitions of disk (BIOS) partitions.  This
-- includes filesystem-level knowledge such as mountpoints.
--

Storage.Subpartition = {}
Storage.Subpartition.new = function(params)
	local method = {}	-- instance variable

	local parent = assert(params.parent)
	local letter = assert(params.letter)
	local size   = assert(params.size)
	local offset = assert(params.offset)
	local fstype = assert(params.fstype)
	local fsize  = assert(params.fsize)
	local bsize  = assert(params.bsize)
	local mountpoint = params.mountpoint

	--
	-- Public methods: accessor functions
	--

	--
	-- Determine this object's identity.
	--
	method.is_a = function(self, class)
		return class == Storage.Subpartition
	end

	--
	-- Return the Storage.Partition object which contains this
	-- subpartition.
	--
	method.get_parent = function(self)
		return parent
	end

	--
	-- Return the designating letter of this subpartition.
	--
	method.get_letter = function(self)
		return letter
	end

	--
	-- Set the mountpoint of this subpartition.
	--
	method.set_mountpoint = function(self, new_mountpoint)
		mountpoint = new_mountpoint
	end

	--
	-- Return the mountpoint of this subpartition.
	--
	method.get_mountpoint = function(self)
		return mountpoint
	end

	--
	-- Set the filesystem type identifier of this subpartition.
	--
	method.get_fstype = function(self)
		return fstype
	end

	--
	-- Return the name of the device node for this subpartition.
	--
	method.get_device_name = function(self)
		if App.conf.disklabel_on_disk then
			-- XXX need to work out what a 'parent' is here
			-- since there are no partition nodes, only disks
			return parent.get_parent().get_name() .. letter
		else
			return parent.get_parent().get_name() ..
			    "s" .. parent.get_number() .. letter
		end
	end

	--
	-- Return the name of the device node used for raw operations
	-- on this subpartition.
	--
	method.get_raw_device_name = function(self)
		if App.conf.has_raw_devices then
			return "r" .. parent.get_parent().get_name() .. letter
		else
			return parent.get_parent().get_name() ..
			    "s" .. parent.get_number() .. letter
		end
	end

	--
	-- Return the subpartition's capacity, as a Storage.Capacity object.
	--
	method.get_capacity = function(self)
		return Storage.Capacity.new(size, "S")
	end

	--
	-- Return the fragment size of this subpartition, in sectors.
	--
	method.get_fsize = function(self)
		return fsize
	end

	--
	-- Return the block size of this subpartition, in sectors.
	--
	method.get_bsize = function(self)
		return bsize
	end

	--
	-- Return whether this subpartition is a swap subpartition or not.
	--
	method.is_swap = function(self)
		return fstype == "swap"
	end

	--
	-- Return whether this subpartition has softupdates enabled or not.
	--
	method.is_softupdated = function(self)
		-- XXX this should be a property
		return true
	end

	--
	-- Print this subpartition descriptor, for debugging.
	--
	method.dump = function(self)
		print("\t\t\t" .. letter .. ": " .. offset .. "," .. size ..
			": " .. fstype .. " -> " .. mountpoint)
	end

	--
	-- Create commands that ensure that the device node for this
	-- subpartition exists.
	--
	method.cmds_ensure_dev = function(self, cmds)
		if App.conf.disklabel_on_disk then -- XXX not quite right
			return
		end
		if App.conf.os.name ~= "FreeBSD" then
			cmds:add{
			    cmdline = "cd ${root}dev && ${root}${TEST_DEV} ${dev} || " ..
				      "${root}${SH} MAKEDEV ${dev}",
			    replacements = {
				dev = FileName.basename(self:get_device_name())
			    }
			}
		end
	end


	method.survey = function(self)
		App.log("Surveying Subpartition on " .. parent:get_device_name() .. ": " ..
		    letter .. ": " .. offset .. "," .. size .. ": " .. fstype ..
		    "  F=" .. fsize .. ", B=" .. bsize)
	end

	--
	-- Constructor.  Just return the instance datum.
	--
	return method
end

return Storage

-- END of lib/storage.lua --
