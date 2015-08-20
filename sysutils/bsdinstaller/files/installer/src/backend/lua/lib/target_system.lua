-- $Id: target_system.lua,v 1.60 2006/07/22 14:40:52 cpressey Exp $

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

module "target_system"

local App = require("app")
local POSIX = require("posix")
local Pty = require("pty")
local FileName = require("filename")
local CmdChain = require("cmdchain")

--[[--------------]]--
--[[ TargetSystem ]]--
--[[--------------]]--

TargetSystem = {}

--
-- There are three general use cases for this class.
--
-- The first case is when mounting a virgin target system
-- for a fresh install.  In this situation, the needed
-- mountpoint directories (taken from the subpartition descriptors
-- in the partition descriptor) are created on the target system
-- before being mounted:
--
--	local ts = TargetSystem.new{ partition = pd, base = "mnt" }
--	if ts:create() then
--		if ts:mount() then
--			...
--			ts:unmount()
--		end
--	end
--
-- The second case is when mounting an existing target system
-- for configuration.  In this situation, the root partition is
-- mounted, then the file /etc/fstab on that partition is parsed,
-- and everything that can reasonably be mounted from that is.
--
--	local ts = TargetSystem.new{ partition = pd, base = "mnt" }
--	if ts:probe() then
--		if ts:mount() then
--			...
--			ts:unmount()
--		end
--	end
--
-- The third case is when configuring the booted system, such as
-- when the configurator may be started from the installed system
-- itself.  In this case, no mounting is necessary.  But the target
-- system must know that this is the case, so that it need not
-- e.g. uselessly chroot to itself.
--
--	local ts = TargetSystem.new()
--	if ts:use_current() then
--		...
--	end
--
TargetSystem.new = function(tab)
	tab = tab or {}
	local pd = tab.partition or nil
	local base = tab.base or nil
	local ts = {}			-- instance variable
	local fstab = nil		-- our representation of fstab
	local root_is_mounted = false	-- flag: is the root fs mounted?
	local is_mounted = false	-- flag: is everything mounted?
	local using_current = false	-- flag: using booted system?

	--
	-- Private utility helper functions.
	--

	--
	-- Convert the options for swap-backed devices from their
	-- fstab format to command line format.
	--
	local convert_swap_options = function(opts)
		local opt
		local result = ""

		for opt in string.gfind(opts, "[^,]") do
			--
			-- Honour options that begin with -, but
			-- don't bother trying to honour the -C
			-- option, since we can't copy files from
			-- the right place anyway.
			--
			if string.find(opt, "^-[^C]") then
				result = result ..
				    string.gsub(opt, "=", " ") .. " "
			end
		end
		
		return result
	end

	local cmds_unmount_all_under = function(cmds)
		local dirname = App.expand("${root}${base}", {
		    base = base
		})
		dirname = FileName.remove_trailing_slash(dirname)
		pd:get_parent():get_parent():cmds_unmount_all_under(cmds, dirname)
	end

	--
	-- Mount this TargetSystem's root filesystem.
	-- Note: this doesn't just queue up commands, it actually does it.
	-- This is necessary for reading /etc/fstab.
	-- Any optimizations will come later...
	--
	local mount_root_filesystem = function(ts)
		local cmds, spd

		if root_is_mounted then
			return false, "Root filesystem is already mounted"
		end

		--
		-- Create a command chain.
		--
		cmds = CmdChain.new()
	
		--
		-- Find the root subpartition of the partition.
		-- It's always the first one, called "a".
		--
		spd = pd:get_subpart_by_letter("a")

		--
		-- If there isn't one, then this partition isn't
		-- correctly formatted.  One possible cause is
		-- an incomplete formatting operation; perhaps the
		-- partition was disklabeled, but never newfs'ed.
		--
		if not spd then
			return false
		end

		--
		-- Ensure that the devices we'll be using exist.
		--
		pd:get_parent():cmds_ensure_dev(cmds)
		pd:cmds_ensure_dev(cmds)
		spd:cmds_ensure_dev(cmds)
	
		--
		-- Make sure nothing is mounted under where we want
		-- to mount this filesystem.
		--
		cmds_unmount_all_under(cmds)

		--
		-- Mount the target's root filesystem
		--
		cmds:add({
		    cmdline = "${root}${MOUNT} ${root}dev/${dev} ${root}${base}",
		    replacements = {
			dev = spd:get_device_name(),
			base = base
		    }
		})
		
		--
		-- Do it.
		--
		root_is_mounted = cmds:execute()
		return root_is_mounted
	end

	--
	-- Accessor methods.
	--

	ts.get_part = function(ts)
		return(pd)
	end

	ts.get_base = function(ts)
		return(base)
	end

	ts.is_mounted = function(ts)
		return(is_mounted)
	end

	--
	-- Query methods.
	--

	--
	-- Heuristic to tell us what operating system is installed
	-- on this target system.
	--
	-- In the absence of a guaranteed text file sitting somewhere
	-- on the target system that says "This is a FooBSD 2.3 Install,"
	-- we have to make some wild guesses based on what we know.
	--
	-- Hopefully we will figure out better guesses in the future.
	--
	ts.guess_os = function(ts)
		--
		-- If /kernel is a directory, it's FreeBSD 5 or above.
		--
		if FileName.is_dir(App.expand("${root}${base}kernel", { base = base })) then
			return "FreeBSD5"
		end

		--
		-- If we can find $DragonFly$ CVS tags in some system files,
		-- it's DragonFly.
		--
		local cmd = App.expand(
		    "${root}${GREP} '$DragonFly' ${root}${base}usr/share/mk/sys.mk",
		    { base = base }
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		local result = pty:close()
		App.log("Result of grep was '%d'", result)
		if result == 0 then
			return "DragonFly"
		end

		--
		-- Otherwise (huge assumption) we guess FreeBSD 4.x.
		--
		return "FreeBSD4"
	end

	--
	-- Command-generating methods.
	--

	ts.cmds_set_password = function(ts, cmds, username, password)
		cmds:add({
		    cmdline = "${root}${CHROOT} ${root}${base} " ..
			      "/${PW} usermod ${username} -h 0",
		    replacements = {
		        base = base,
		        username = username
		    },
		    desc = _("Setting password for user `%s'...", username),
		    input = password .. "\n",
		    sensitive = password
		})
	end

	ts.cmds_add_user = function(ts, cmds, tab)

		local add_flag = function(flag, setting)
			if setting ~= nil and setting ~= "" then
				return flag .. " " .. tostring(setting)
			else
				return ""
			end
		end

		local home_skel = ""
		if not tab.home or not FileName.is_dir(tab.home) then
			home_skel = "-m -k /usr/share/skel"
		end

		cmds:add({
		    cmdline = "${root}${CHROOT} ${root}${base} /${PW} useradd " ..
			      "'${username}' ${spec_uid} ${spec_gid} -c \"${gecos}\"" ..
			      "${spec_home} -s ${shell} ${spec_groups} ${home_skel}",
		    replacements = {
		        base = base,
		        username = assert(tab.username),
			gecos = tab.gecos or "Unnamed User",
			shell = tab.shell or "/bin/sh",
		        spec_uid = add_flag("-u", tab.uid),
		        spec_gid = add_flag("-g", tab.group),
		        spec_home = add_flag("-d", tab.home),
			spec_groups = add_flag("-G", tab.groups),
			home_skel = home_skel
		    },
		})

		if tab.password then
			ts:cmds_set_password(cmds, tab.username, tab.password)
		end
	end

	--
	-- Iterator for srclists.
	--
	local each_srclist_element = function(srclist)
		local i = 0
		local n = table.getn(srclist)

		return function()
			i = i + 1
			if i <= n then
				local element = srclist[i]
				if type(element) == "string" then
					return element, element
				else
					assert(element.src and element.dest,
					   "Copy-element must specify " ..
					   "both 'src' and 'dest'")
					return element.src, element.dest
				end				
			else
				return nil
			end
		end
	end

	--
	-- Create commands to copy files and directories to the target system.
	-- The 'cleanout' flag determines whether files which exist on the
	-- target syste, but do not exist on the install media, are deleted.
	--
	ts.cmds_install_srcs = function(ts, cmds, srclist, cleanout)
		local i, element

		--
		-- Private function to copy the given element onto the HDD,
		-- using the 'cpdup' utility (if requested.)
		--
		local add_copy_command = function(src, dest)
			local filename = App.expand("${root}" .. src)
			local link = POSIX.readlink(filename)
			if link ~= nil then
				cmds:add{
				    cmdline = "${root}${LN} -s ${link} ${root}${base}${dest}",
				    replacements = {
					link = link,
					base = base,
					dest = dest
				    }
				}
			elseif FileName.is_dir(filename) then
				cmds:add{
				    cmdline = "${root}${MKDIR} -p ${root}${base}${dest}",
				    replacements = {
					base = base,
					src  = src,
					dest = dest
				    }
				}
				cmds:add{
				    cmdline = "${root}${TAR} -cf - -C ${root}${src} . | " ..
					"${root}${TAR} xpf - -C ${root}${base}${dest}",
				    replacements = {
					base = base,
					src  = src,
					dest = dest
				    }
				}
			else
				cmds:add{
				    cmdline = "${root}${CP} -p ${root}${src} ${root}${base}${dest}",
				    replacements = {
					base = base,
					src  = src,
					dest = dest
				    }
				}
			end
		end

		if App.conf.use_cpdup then
			add_copy_command = function(src, dest)
				cmds:add{
				    cmdline = "${root}${CPDUP} "	..
					"${cleanout}${root}${src} "	..
					"${root}${base}${dest}",
				    replacements = {
					cleanout = (cleanout and "") or "-o ",
					base = base,
					src  = src,
					dest = dest
				    },
				    log_mode = CmdChain.LOG_QUIET -- don't spam log
				}
			end
		end

		local src, dest
		for src, dest in each_srclist_element(srclist) do
			--
			-- Create intermediate directories as needed.
			--
			local dest_dir = FileName.dirname(dest)
			if FileName.remove_trailing_slash(dest_dir) ~= "." then
				cmds:add{
				    cmdline = "${root}${MKDIR} -p ${root}${base}${dest_dir}",
				    replacements = {
					base = base,
					dest_dir = dest_dir
				    }
				}
			end

			add_copy_command(src, dest)
		end

		--
		-- Now, because cpdup does not cross mount points,
		-- we must copy anything that the user might've made a
		-- seperate mount point for (e.g. /usr/libdata/lint.)
		--
		-- Only bother to copy the mountpoint IF:
		-- o   It is a regular (i.e. not swap) subpartition
		-- o   A directory exists on the install medium for it
		-- o   We have said to copy it at some point
		--     (something in srclist is a prefix of it); and
		-- o   We have not already said to copy it
		--     (it is not a prefix of anything in srclist.)
		--
		local spd
		for spd in pd:get_subparts() do
			local mountpoint = spd:get_mountpoint()
			local root = App.conf.dir.root

			local starts_with = function(str, prefix)
				return string.sub(str, string.len(prefix)) == prefix
			end

			local src, dest = nil, nil
			local i, element
			local src_cand, dest_cand -- "candidates"
			for src_cand, dest_cand in each_srclist_element(srclist) do
				if spd:get_fstype() ~= "4.2BSD" or
				   not FileName.is_dir(root .. mountpoint) then
					break
				end
				if starts_with(mountpoint, root .. dest_cand) then
					src = src_cand
					dest = dest_cand
				end
				if starts_with(root .. dest_cand, mountpoint) then
					return nil
				end
			end

			--
			-- If it needs to be copied, then copy it.
			--
			if src ~= nil and dest ~= nil then
				add_copy_command(src, dest)
			end
		end
	end

	--
	-- Generate commands that write a new fstab for this TargetSystem.
	-- The fstab is written to /etc/fstab on the TargetSystem.
	--
	ts.cmds_write_fstab = function(ts, cmds, tab)
		tab = tab or {}
		local filename = tab.filename or "/etc/fstab"
		local extra_fs = tab.extra_fs or {}

		cmds:set_replacements{
		    header = "# Device\t\tMountpoint\tFStype\tOptions\t\tDump\tPass#",
		    device = "???",
		    mountpoint = "???",
		    base = base,
		    filename = FileName.remove_leading_slash(filename)
		}

		cmds:add("${root}${ECHO} '${header}' >${root}${base}${filename}")

		--
		-- Add the mountpoints for the selected subpartitions
		-- (/, /usr, /var, and so on) to the fstab file.
		--
		for spd in pd:get_subparts() do
			cmds:set_replacements{
			    device = spd:get_device_name(),
			    mountpoint = spd:get_mountpoint()
			}

			if spd:get_mountpoint() == "/" then
				cmds:add("${root}${ECHO} '/dev/${device}\t\t${mountpoint}\t\tufs\trw\t\t1\t1' >>${root}${base}/${filename}")
			elseif spd:is_swap() then
				cmds:add("${root}${ECHO} '/dev/${device}\t\tnone\t\tswap\tsw\t\t0\t0' >>${root}${base}/${filename}")
			else
				cmds:add("${root}${ECHO} '/dev/${device}\t\t${mountpoint}\t\tufs\trw\t\t2\t2' >>${root}${base}/${filename}")
			end
		end

		--
		-- Add the mountpoints for the extra filesystems
		-- (/cdrom, /procfs, and so on) to the fstab file.
		-- Create the associated directories, as well.
		--
		local i, fs_desc
		for i, fs_desc in extra_fs do
			cmds:set_replacements{
			    device = fs_desc.dev,
			    mountpoint = fs_desc.mtpt,
			    fstype = fs_desc.fstype,
			    access = fs_desc.access
			}
			cmds:add("${root}${ECHO} '${device}\t\t\t${mountpoint}\t\t" ..
			    "${fstype}\t${access}\t\t0\t0' >>${root}${base}/${filename}")
			cmds:add("${root}${MKDIR} -p ${root}${base}/${mountpoint}")
		end
	end

	--
	-- Main manipulation methods.
	--

	--
	-- Create mountpoint directories on a new system, based on what
	-- the user wants (the subpartition descriptors under the given
	-- partition descriptor) and return a fstab structure describing them.
	--
	ts.create = function(ts)
		local spd, cmds

		--
		-- Mount the target system's root filesystem,
		-- if not already mounted
		--
		if not root_is_mounted then
			if not mount_root_filesystem() then
				return false
			end
		end

		--
		-- Create mount points for later mounting of subpartitions.
		--
		cmds = CmdChain.new()
		fstab = {}
		for spd in pd:get_subparts() do
			local mtpt = spd:get_mountpoint()
			local dev = spd:get_device_name()

			cmds:set_replacements{
				base = base,
				dev = dev,
				mtpt = FileName.remove_leading_slash(mtpt)
			}

			--
			-- Make a swap subpartition entry.
			--
			if spd:is_swap() then
				--
				-- If this swap subpart is large enough to be
				-- used as a dump device, and no dump device
				-- has been configured yet, activate it as the
				-- dump device and record the choice for later
				-- writing to rc.conf.
				--
				if App.conf.enable_crashdumps then
					spd:cmds_ensure_dev(cmds)
					cmds:add("${root}${DUMPON} -v ${root}dev/${dev}")
					App.state.rc_conf:set("dumpdev", "/dev/" ..
					    spd:get_device_name())
					App.state.rc_conf:set("dumpdir", "/var/crash")
				end
				fstab[mtpt] = {
				    device  = "/dev/" .. dev,
				    fstype  = "swap",
				    options = "sw",
				    dump    = 0,
				    pass    = 0
				}
			else
				if spd:get_mountpoint() ~= "/" then
					cmds:add("${root}${MKDIR} -p ${root}${base}${mtpt}")
				end
				fstype = "ufs"
				opts = "rw"
				fstab[mtpt] = {
				    device  = "/dev/" .. dev,
				    fstype  = "ufs",
				    options = "rw",
				    dump    = 2,
				    pass    = 2
				}
				if mtpt == "/" then
					fstab[mtpt].dump = 1
					fstab[mtpt].pass = 1
				end
			end
		end
		return cmds:execute()
	end

	--
	-- Parse the fstab of a mounted target system.
	-- Returns either a table representing the fstab, or
	-- nil plus an error message string.
	--
	-- As a side effect, this function also associates mountpoints
	-- with the subpartition descriptors under the partition
	-- descriptor with which this target system is associated.
	--
	ts.probe = function(ts)
		local fstab_filename, fstab_file, errmsg
		local spd

		--
		-- Mount the target system's root filesystem,
		-- if not already mounted.
		--
		if not root_is_mounted then
			if not mount_root_filesystem() then
				return nil, "Could not mount / of target system."
			end
		end

		--
		-- Open the target system's fstab and parse it.
		--
		fstab_filename = App.expand("${root}${base}etc/fstab", {
		    base = base
		})
		fstab_file, errmsg = io.open(fstab_filename, "r")
		if not fstab_file then
			return nil, "Could not open /etc/fstab of target system."
		end

		fstab = {}
		line = fstab_file:read()
		while line do
			--
			-- Parse the fstab line.
			--
			if string.find(line, "^%s*#") then
				-- comment: skip it
			elseif string.find(line, "^%s*$") then
				-- blank line: skip it
			else
				local found, len, dev, mtpt, fstype, opts, dump, pass =
				    string.find(line, "%s*([^%s]*)%s*([^%s]*)%s*" ..
				      "([^%s]*)%s*([^%s]*)%s*([^%s]*)%s*([^%s]*)")
				if not found then
					App.log("Warning: malformed line in fstab: " ..
					    line)
				else
					fstab[mtpt] = {
					    device  = dev,
					    fstype  = fstype,
					    options = opts,
					    dump    = dump,
					    pass    = pass
					}
					spd = pd:get_subpart_by_device_name(dev)
					if fstype ~= "ufs" then
						-- Don't associate non-ufs
						-- fs's with any mountpoint.
					elseif not spd then
						-- This can happen if e.g.
						-- the user has included a
						-- subpartition from another
						-- drive in their fstab.
					else
						-- Associate mountpoint.
						spd:set_mountpoint(mtpt)
					end
				end
			end
			line = fstab_file:read()
		end
		fstab_file:close()

		return fstab
	end

	ts.use_current = function(ts)
		using_current = true
		base = "/"
		return true
	end

	--
	-- Mount the system on the given partition into the given mount
	-- directory (typically "mnt".)
	--
	ts.mount = function(ts)
		local cmds, i, mtpt, mtpts, fsdesc

		if using_current or is_mounted then
			return true
		end

		if not root_is_mounted or fstab == nil then
			return false
		end

		--
		-- Go through each of the mountpoints in our fstab,
		-- and if it looks like we should, try mount it under base.
		--
	
		mtpts = {}
		for mtpt, fsdesc in fstab do
			table.insert(mtpts, mtpt)
		end
		table.sort(mtpts)

		cmds = CmdChain.new()
		for i, mtpt in mtpts do
			fsdesc = fstab[mtpt]
			if mtpt == "/" then
				-- It's already been mounted by
				-- read_target_fstab() or create_mountpoints.
			elseif string.find(fsdesc.options, "noauto") then
				-- It's optional.  Don't mount it.
			elseif (not string.find(fsdesc.device, "^/dev/") and
				fsdesc.device ~= "swap") then
				-- Device doesn't start with /dev/ and
				-- it isn't 'swap'.  Don't even go near it.
			elseif mtpt == "none" or fsdesc.fstype == "swap" then
				-- Swap partition.  Don't mount it.
			elseif fsdesc.device == "swap" then
				-- It's swap-backed.  mount_mfs it.
				
				cmds:add({
				    cmdline = "${root}${MOUNT_MFS} ${swap_opts} swap ${root}${base}${mtpt}",
				    replacements = {
					swap_opts = convert_swap_options(fsdesc.options),
					base = base,
					mtpt = FileName.remove_leading_slash(mtpt)
				    }
				})
			else
				-- If we got here, it must be normal and valid.
				cmds:set_replacements{
				    dev  = FileName.basename(fsdesc.device),
				    opts = fsdesc.options,	-- XXX this may need further cleaning?
				    base = base,
				    mtpt = FileName.remove_leading_slash(mtpt)
				}
				-- It's a subpartition, of sorts, but we don't
				-- have an object for it yet.
				if not App.conf.disklabel_on_disk then
					cmds:add(
					    "cd ${root}dev && ${root}${TEST_DEV} ${dev} || " ..
					      "${root}${SH} MAKEDEV ${dev}"
					)
				end
				cmds:add(
				    "${root}${MOUNT} -o ${opts} ${root}dev/${dev} ${root}${base}${mtpt}"
				)
			end
		end
	
		is_mounted = cmds:execute()
		return is_mounted
	end

	--
	-- Unmount the target system.
	--
	ts.unmount = function(ts)
		if using_current or
		   (not is_mounted and not root_is_mounted) then
			return true
		end
		local cmds = CmdChain.new()
		cmds_unmount_all_under(cmds)
		if cmds:execute() then
			is_mounted = false
			root_is_mounted = false
			return true
		else
			return false
		end
	end

	--
	-- 'Constructor' - initialize instance state.
	--

	--
	-- Fix up base.
	--
	base = base or ""
	base = FileName.add_trailing_slash(base)

	return ts
end

TargetSystem.use_current = function(tab)
	local ts = TargetSystem.new(tab)
	ts:use_current()
	return ts
end

return TargetSystem
