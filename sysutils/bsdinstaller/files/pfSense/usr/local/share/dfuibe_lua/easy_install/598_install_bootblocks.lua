-- $Id: 598_install_bootblocks.lua,v 1.13 2007/08/02 07:42:52 sullrich Exp $

--
-- Install bootblocks on the disks that the user selects.
--

return {
    id = "install_bootblocks",
    name = _("Install Bootblocks"),
    req_state = { "storage" },
    effect = function(step)
	local datasets_list = {}
	local dd
	local disk_ref = {}	-- map from raw name to ref to Storage.Disk

	for dd in App.state.storage:get_disks() do
		local raw_name = dd:get_raw_device_name()

		disk_ref[raw_name] = dd

		--
		-- Only install to selected disk
		--
		if App.state.sel_disk == dd then
			local dataset = {
				disk = raw_name,
				boot0cfg = "Y",
				packet = "N"
			}
			--
			-- For disks larger than 8 gigabytes in size,
			-- enable "packet mode" booting by default.
			--
			if dd:get_capacity():in_units("G") >= 8 then
				dataset.packet = "Y"
			end
			if raw_name == App.state.sel_disk:get_name() then
				table.insert(datasets_list, dataset)		
			end
		end

	end
		local cmds = CmdChain.new()
		local i, dataset

		for i, dataset in ipairs(datasets_list) do
			if dataset.boot0cfg == "Y" then
				if dataset.usegrub == "Y" then
					dd = disk_ref[dataset.disk]
					disk = dd:get_name()
					--
					-- execute Grub boot block installer
					--
					cmds:set_replacements{
					    disk = disk
					}
					cmds:add("/sbin/sysctl kern.geom.debugflags=16")
					cmds:add("/usr/local/sbin/grub-install --root-directory=/mnt/ /dev/${disk}")
					cmds:add("echo \"default=0\" > /mnt/boot/grub/menu.lst")
					cmds:add("echo \"timeout=5\" >> /mnt/boot/grub/menu.lst")
					cmds:add("echo \"title pfSense\" >> /mnt/boot/grub/menu.lst")
					cmds:add("echo \"	root (hd0,0,a)\" >> /mnt/boot/grub/menu.lst")
					cmds:add("echo \"	kernel /boot/loader\" >> /mnt/boot/grub/menu.lst")
					cmds:add("/usr/local/sbin/grub-install --root-directory=/mnt/ /dev/${disk}")
				else
					dd = disk_ref[dataset.disk]
					dd:cmds_install_bootblock(cmds,
					    (dataset.packet == "Y"))
					disk = dd:get_name()
					cmds:set_replacements{
					    disk = disk
					}
					cmds:add("/usr/sbin/boot0cfg -B -b /boot/boot0 /dev/${disk}")
				end
			end
		end

		cmds:execute()
		return step:next()
    end
}
