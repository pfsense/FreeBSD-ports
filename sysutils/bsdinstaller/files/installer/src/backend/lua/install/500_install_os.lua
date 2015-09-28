-- $Id: 500_install_os.lua,v 1.82 2006/07/27 21:47:52 sullrich Exp $

--
-- Actually install the OS.
-- XXX this could probably be split up into further steps:
--
-- 2) activate swap
-- 3) create and mount the target system
-- 4) copy the files
-- 5) clean up
--

return {
    id = "install_os",
    name = _("Install OS"),
    req_state = { "storage", "sel_disk", "sel_part", "sel_pkgs" },
    effect = function(step)
	local spd, cmds

	--
	-- If there is a target system mounted, unmount it before starting.
	--
	if App.state.target ~= nil and App.state.target:is_mounted() then
		if not App.state.target:unmount() then
			App.ui:inform(
			    _("Warning: already-mounted target system could " ..
			      "not be correctly unmounted first."))
			return step:prev()
		end
	end

	--
	-- Create a command chain.
	--
	cmds = CmdChain.new()

	--
	-- Activate swap, if there is none activated so far.
	--
	if App.state.storage:get_activated_swap():in_units("K") == 0 then
		for spd in App.state.sel_part:get_subparts() do
			if spd:get_fstype() == "swap" then
				cmds:add{
				    cmdline = "${root}${SWAPON} ${root}dev/${dev}",
				    replacements = {
					dev = spd:get_device_name()
				    }
				}
			end
		end
	end

	--
	-- Initialize the target system, create the mountpoint directories
	-- configured for the selected partition (presumably set up by the
	-- user,) and mount the appropriate subpartitions on them.
	--
	App.state.target = TargetSystem.new{
	    partition = App.state.sel_part,
	    base      = "mnt"
	}
	if not App.state.target:create() then
		App.ui:inform(
		    _("Could not create the skeletal target system.")
		)
		return step:prev()
	end
	if not App.state.target:mount() then
		App.ui:inform(
		    _("Could not mount the skeletal target system.")
		)
		return step:prev()
	end
	cmds:set_replacements{
	    base = App.state.target:get_base(),
	    logfile = App.conf.log_filename,
		devicename = App.state.sel_part:get_escaped_device_name(),
		part = App.state.sel_part:get_device_name()
	}

	--
	-- Create the commands which will install the chosen directories
	-- onto the target system.
	--
	-- App.state.target:cmds_install_srcs(cmds, App.conf.install_items)

	cmds:add(
	    "${root}${TAR} -C ${root}${base} -xzpf ${root}install/" .. App.conf.product.name .. ".txz"
	)

	--
	-- Some directories may not have been copied to the HDD, but
	-- may still be required/desired on a default install.  For
	-- example, we generally don't want to copy the entire
	-- "local packages" hierarchy, because the user may not want
	-- all those packages on their system.  Instead, we can create
	-- the heretofore uncopied directory trees using "mtree".
	--
	local mtree_dir, mtree_file
	for mtree_dir, mtree_file in pairs(App.conf.mtrees_post_copy or {}) do
		cmds:set_replacements{
		    mtree_dir = mtree_dir,
		    mtree_file = mtree_file
		}
		cmds:add(
		    "${root}${MKDIR} -p ${root}${base}${mtree_dir}",
		    {
			cmdline = "${root}${MTREE} -deU -f ${root}${mtree_file} -p ${root}${base}${mtree_dir}",
			log_mode = CmdChain.LOG_QUIET -- don't spam log
		    }
		)
	end

	--
	-- Create symlinks to temporary directory.
	--

	local real_tmp_dir = "tmp"

	if App.state.sel_part:get_subpart_by_mountpoint("/tmp") then
		--
		-- If the user has a /tmp subparition, regardless of whether
		-- they also have a /var subpartition, we assume they would
		-- like /var/tmp to be symlinked to /tmp.
		--
		cmds:add(
		    "${root}${RM} -rf ${root}${base}var/tmp",
		    "${root}${LN} -s /tmp ${root}${base}var/tmp"
		)
	else
		--
		-- If the user has no /tmp, but does have /var or /usr,
		-- symlink /tmp to /var/tmp or /usr/tmp.
		--
		if App.state.sel_part:get_subpart_by_mountpoint("/var") then
			real_tmp_dir = "var/tmp"
			cmds:add(
			    "${root}${LN} -s /var/tmp ${root}${base}tmp"
			)
		elseif App.state.sel_part:get_subpart_by_mountpoint("/usr") then
			real_tmp_dir = "usr/tmp"
			cmds:add(
			    "${root}${LN} -s /usr/tmp ${root}${base}tmp"
			)
		end
	end

	--
	-- [Re]create the temporary directory in the desired place.
	--
	cmds:set_replacements{ real_tmp_dir = real_tmp_dir }
	if not App.state.sel_part:get_subpart_by_mountpoint("/" .. real_tmp_dir) then
		cmds:add(
		    "${root}${RM} -rf ${root}${base}${real_tmp_dir}",
		    "${root}${MKDIR} -p ${root}${base}${real_tmp_dir}"
		)
	end
	cmds:add(
	    "${root}${CHMOD} 1777 ${root}${base}${real_tmp_dir}"
	)

	--
	-- Create symlinks to home directory.
	--

	--
	-- If the user has no /home, but does have /usr or /var,
	-- symlink /home to /usr/home or /var/home.
	--
	if not App.state.sel_part:get_subpart_by_mountpoint("/home") then
		if App.state.sel_part:get_subpart_by_mountpoint("/usr") then
			cmds:add(
			     "${root}${RM} -rf ${root}${base}/home",
			     "${root}${MKDIR} -p ${root}${base}/usr/home",
			     "${root}${LN} -s /usr/home ${root}${base}/home"
			)
		elseif App.state.sel_part:get_subpart_by_mountpoint("/var") then
			cmds:add(
			     "${root}${RM} -rf ${root}${base}/home",
			     "${root}${MKDIR} -p ${root}${base}/var/home",
			     "${root}${LN} -s /var/home ${root}${base}/home"
			)
		end
	end

	--
	-- Clean up unwanted/unneeded files.
	-- Use 'rm -f' in case the file never existed in the first place.
	--
	local i, filename
	for i, filename in ipairs(App.conf.cleanup_items or {}) do
		cmds:add{
		    cmdline = "${root}${RM} -f ${root}${base}${filename}",
		    replacements = {
		        filename = filename
		    }
		}
	end

	--
	-- Create missing directories.
	--
	cmds:add(
	    "${root}${MKDIR} -p ${root}${base}/mnt"
	)

	--
	-- Write the fstab.
	--
	App.state.target:cmds_write_fstab(cmds, {
	    extra_fs = App.state.extra_fs
	})

	--
	-- Install requested packages.
	--
	-- Note that we have to explicitly say what the temporary directory
	-- will be, because the symlink (if any) won't be created yet.
	--
	local pkg_graph = App.state.sel_pkgs:to_graph(function(pkg)
	    return pkg:get_prerequisites(App.state.source)
	end, true)
	local pkg_list = pkg_graph:topological_sort()
	pkg_list:cmds_install_all(cmds, App.state.target, {
	    tmp_dir = real_tmp_dir
	})

	--
	-- Backup the disklabel.
	--
	cmds:add{
	    "${root}${DISKLABEL} ${part} >${root}${base}/etc/disklabel.${devicename}"
	}

	--
	-- Finally, write the rc.conf modifications.
	--
	App.state.rc_conf:cmds_write(cmds,
	    cmds:expand("${root}${base}/etc/rc.conf"), "sh")

	--
	-- Do it!
	--
	if cmds:execute() then
		--
		-- Success!
		--
		-- Put a copy of the log on the installed system.
		-- It looks like it might be necessary to close the log
		-- while copying it on some system, so we do that here.
		--
		App.close_log()
		cmds = CmdChain.new()
		cmds:set_replacements{
		    base = App.state.target:get_base(),
		    logfile = App.conf.log_filename
		}
		cmds:add(
		    "${root}${CP} ${tmp}${logfile} ${root}${base}/var/log/${logfile}",
		    "${root}${CHMOD} 600 ${root}${base}/var/log/${logfile}"
		)
		cmds:execute()
		App.reopen_log()

		return step:next()
	else
		App.ui:inform(
		    _("%s was not fully installed.", App.conf.product.name)
		)
		return step:prev()
	end
    end
}
