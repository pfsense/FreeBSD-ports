-- $Id: 270_install_bootblocks.lua,v 1.19 2005/08/29 18:30:31 cpressey Exp $

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

		local cmdsGPT = CmdChain.new()
		cmdsGPT:set_replacements{
	    	raw_name = raw_name,
			raw_disk = App.state.sel_disk
		}
		cmdsGPT:add("echo ${raw_name} ${raw_disk} > /tmp/debug");
		cmdsGPT:execute()

		if raw_name == App.state.sel_disk:get_name() then
			table.insert(datasets_list, dataset)		
		end

	end

	local response = App.ui:present({
	    id = "install_bootstrap",
	    name = _("Install Bootblock(s)"),
	    short_desc = _(
		"You may now wish to install bootblocks on one or more disks. "	..
		"If you already have a boot manager installed, you can skip "	..
		"this step (but you may have to configure your boot manager "	..
		"separately.)  If you wish to install %s on a disk other "		..
		"than your first disk, you will need to put the bootblock "	..
		"on at least your first disk and the %s disk.",
		App.conf.product.name, App.conf.product.name),
	    long_desc = _(
	        "'Packet Mode' refers to using newer BIOS calls to boot " ..
	        "from a partition of the disk.  It is generally not " ..
	        "required unless:\n\n" ..
	        "- your BIOS does not support legacy mode; or\n" ..
	        "- your %s primary partition resides on a " ..
	        "cylinder of the disk beyond cylinder 1024; or\n" ..
	        "- you just can't get it to boot without it.",
		App.conf.product.name
	    ),
	    special = "bsdinstaller_install_bootstrap",

	    fields = {
		{
		    id = "disk",
		    name = _("Disk Drive"),
		    short_desc = _("The disk on which you wish to install a bootblock"),
		    editable = "false"
		},
		{
		    id = "boot0cfg",
		    name = _("Install Bootblock?"),
		    short_desc = _("Install a bootblock on this disk"),
		    control = "checkbox"
		},
		{
		    id = "packet",
		    name = _("Packet mode?"),
		    short_desc = _("Select this to use 'packet mode' to boot the disk"),
		    control = "checkbox"
		}
	    },
	
	    actions = {
		{
		    id = "ok",
		    name = _("Accept and Install Bootblocks")
		},
		{
		    id = "skip",
		    name = _("Skip this Step")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name())
		}
	    },

	    datasets = datasets_list,

	    multiple = "true",
	    extensible = "false"
	})

	if response.action_id == "ok" then
		local cmds = CmdChain.new()
		local i, dataset

		for i, dataset in ipairs(response.datasets) do
			if dataset.boot0cfg == "Y" then
				dd = disk_ref[dataset.disk]
				dd:cmds_install_bootblock(cmds,
				    (dataset.packet == "Y"))
			end
		end

		if cmds:execute() then
			App.ui:inform(_(
			    "Bootblocks were successfully installed!"
			))
			return step:next()
		else
			App.ui:inform(_(
			    "Bootblocks were not successfully installed."
			))
			return step
		end
	elseif response.action_id == "skip" then
		return step:next()
	else
		return step:prev()
	end
    end
}
