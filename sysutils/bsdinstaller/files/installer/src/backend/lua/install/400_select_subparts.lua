-- $Id: 400_select_subparts.lua,v 1.50 2005/08/27 08:35:58 cpressey Exp $

--
-- Subpartition editor.
--
-- XXX This should probably be available from StorageUI so that we
-- can do it during configuration, too.  But that will get ugly if
-- we attempt to allow existing subpartitions to be retained, etc.
--

local expert_mode = false

local datasets_list = nil

return {
    id = "select_subparts",
    name = _("Select Subpartitions"),
    req_state = { "sel_disk", "sel_part" },
    effect = function(step)
	local part_no, pd
	local part_actions = {}
	local i, letter

	---------------------
	-- Local functions --
	---------------------

	local fillout_missing_expert_values = function()
		local i, dataset
	
		for i, dataset in ipairs(datasets_list) do
			if not dataset.softupdates and
			   not dataset.fsize and not dataset.bsize then
				if dataset.mountpoint == "/" then
					dataset.softupdates = "N"
				else
					dataset.softupdates = "Y"
				end
	
				if dataset.capstring == "*" or
				   (Storage.Capacity.is_valid_capstring(dataset.capstring) and
				    Storage.Capacity.new(dataset.capstring):in_units("G") >= 1.0) then
					dataset.fsize = "2048"
					dataset.bsize = "16384"
				else
					dataset.fsize = "1024"
					dataset.bsize = "8192"
				end
			end
		end
	end

	--
	-- Make sure all the given subpart descriptors are OK.
	--
	local validate_subpart_descriptors = function(pd)
		local spd, k, v
		local part_size = pd:get_capacity():in_units("S")
		local used_size = 0
		local min_size = {}

		--
		-- Read the minimum required subpart capacities from the conf file.
		--
		for k, v in App.conf.limits.subpart_min do
			min_size[k] = Storage.Capacity.new(v):in_units("S")
		end

		--
		-- If the user didn't select a /usr partition, / is going to
		-- have to hold all that stuff - so make sure it's big enough.
		--
		if not pd:get_subpart_by_mountpoint("/usr") then
			min_size["/"] = min_size["/"] + min_size["/usr"]
		end

		for spd in pd:get_subparts() do
			local spd_size = spd:get_capacity():in_units("S")
			local mtpt = spd:get_mountpoint()
			local min_mt_size = min_size[mtpt]

			used_size = used_size + spd_size

			if min_mt_size and spd_size < min_mt_size then
				if not App.ui:confirm(_(
				    "WARNING: the %s subpartition should "	..
				    "be at least %s in size or you will "	..
				    "risk running out of space during "		..
				    "the installation.\n\n"			..
				    "Proceed anyway?",
				    mtpt,
				    Storage.Capacity.new(min_mt_size, "S"):format()
				)) then
					return false
				end
			end
		end
	
		if used_size > part_size then
			if not App.ui:confirm(_(
			    "WARNING: The total number of sectors needed "	..
			    "for the requested subpartitions (%d) exceeds the "	..
			    "number of sectors available in the partition (%d) " ..
			    "by %d sectors (%s.)\n\n"				..
			    "This is an invalid configuration; we "		..
			    "recommend shrinking the size of one or "		..
			    "more subpartitions before proceeding.\n\n"		..
			    "Proceed anyway?",
			    used_size, part_size, used_size - part_size,
			    Storage.Capacity.new(used_size - part_size, "S"):format()
			)) then
				return false
			end
		end
	
		if used_size < part_size - App.conf.limits.waste_max then
			if not App.ui:confirm(_(
			    "Note: the total capacity required "	..
			    "for the requested subpartitions (%s) does not make "	..
			    "full use of the capacity available in the "	..
			    "partition (%s.)  %d sectors (%s) of space will go " ..
			    "unused.\n\n"					..
			    "You may wish to expand one or more subpartitions "	..
			    "before proceeding.\n\n"				..
			    "Proceed anyway?",
			    Storage.Capacity.new(used_size, "S"):format(),
			    Storage.Capacity.new(part_size, "S"):format(),
			    part_size - used_size,
			    Storage.Capacity.new(part_size - used_size, "S"):format()
			)) then
				return false
			end
		end
	
		if App.conf.enable_crashdumps then
			local num_swap_subparts = 0
			local num_dumponable = 0
	
			for spd in pd:get_subparts() do
				if spd:is_swap() then
					num_swap_subparts = num_swap_subparts + 1
				end
			end

			if num_swap_subparts == 0 then
				if not App.ui:confirm(_(
				    "Note: no swap subpartitions configured thus "	..
				    "holding a crash dump (an image of the "	..
				    "computers' memory at the time of failure.) "	..
				    "Because this complicates troubleshooting, "	..
				    "we recommend that you create a swap partition "	..
				    "Proceed anyway?",
				    mtpt, min_cap)) then
					return false
				end
			end
		end

		return true
	end

	--
	-- Take a list of tables representing the user's choices and
	-- create a matching set of subpartition descriptors under
	-- the given partition description from them.  In the process,
	-- the desired subpartitions are checked for validity.
	--
	local create_subpart_descriptors = function(pd, list)
		local i, letter, dataset
		local result, size
		local offset, fstype
		local total_size = 0
		local wildcard_size = false
	
		pd:clear_subparts()
	
		offset = 0
		for i, dataset in list do
			if dataset.capstring == "*" then
				if wildcard_size then
					App.ui:inform(_(
					    "Only one subpartition may have " ..
					    "a capacity of '*'."
					))
					return false
				end
				wildcard_size = true
			else
				if Storage.Capacity.is_valid_capstring(dataset.capstring) then
					total_size = total_size + 
					    Storage.Capacity.new(dataset.capstring):in_units("S")
				else
					App.ui:inform(_(
					    "'%s' is not a valid capacity specifier. "	..
					    "Capacity must either end in 'M' "		..
					    "for megabytes, 'G' for gigabytes, "	..
					    "or be '*' to indicate 'use all "		..
					    "remaining space.'",
					    dataset.capstring
					))
					return false
				end
			end
		end

		local next_letter = function(letter)
			local done = false
			while not done do
				done = true
				letter = string.char(string.byte(letter) + 1)
				local i, test
				for i, test in ipairs(App.conf.window_subpartitions) do
					if test == letter then
						done = false
					end
				end
			end
			return letter
		end

		offset = 0
		letter = nil
		for i, dataset in ipairs(list) do
			if not letter then
				letter = "a"
			else
				letter = next_letter(letter)
			end

			if dataset.capstring == "*" then
				size = pd:get_capacity():in_units("S") - total_size
			else
				-- This has already been determined to be valid
				size = Storage.Capacity.new(dataset.capstring):in_units("S")
			end
	
			if dataset.mountpoint == "swap" then
				fstype = "swap"
			else
				fstype = "4.2BSD"
			end
	
			pd:add_subpart(Storage.Subpartition.new{
			    parent = pd,
			    letter = letter,
			    size   = size,
			    offset = offset,
			    fstype = fstype,
			    fsize  = tonumber(dataset.fsize),
			    bsize  = tonumber(dataset.bsize),
			    mountpoint = dataset.mountpoint
			})
	
			offset = offset + size
		end
	
		return validate_subpart_descriptors(pd)
	end

	--
	-- Begin main logic!
	--

	if not datasets_list then
		datasets_list = App.conf.mountpoints(
		    App.state.sel_part:get_capacity():in_units("M"),
		    App.state.storage:get_ram_capacity():in_units("M")
		)
	end

	local fields_list = {
		{
		    id = "mountpoint",
		    name = _("Mountpoint")
		},
		{
		    id = "capstring",
		    name = _("Capacity")
		}
	}

	local actions_list = {
		{
		    id = "ok",
		    name = _("Accept and Create"),
		    effect = function()
			return step:next()
		    end
	        },
		{
		    id = "cancel",
		    name = _("Return to %s", step:get_prev_name()),
		    accelerator = "ESC",
		    effect = function()
			return step:prev()
		    end
	        }
	}

	if expert_mode then
		table.insert(fields_list,
		    {
			id = "softupdates",
			name = _("Softupdates?"),
			control = "checkbox"
		    }
		)
		table.insert(fields_list,
		    {
			id = "fsize",
			name = _("Frag Size")
		    }
		)
		table.insert(fields_list,
		    {
			id = "bsize",
			name = _("Block Size")
		    }
		)

		table.insert(actions_list,
		    {
			id = "switch",
			name = _("Switch to Normal Mode"),
			effect = function()
				expert_mode = not expert_mode
				return step
			end
		    }
		)
	else
		table.insert(actions_list,
		    {
			id = "switch",
			name = _("Switch to Expert Mode"),
			effect = function()
				expert_mode = not expert_mode
				return step
			end
		    }
		)
	end

	local response = App.ui:present({
	    id = "select_subpartitions",
	    name = _("Select Subpartitions"),
	    short_desc = _("Set up the subpartitions (also known "	..
		"as just `partitions' in BSD tradition) you want to "	..
		"have on this primary partition.\n\n"			..
		"For Capacity, use 'M' to indicate megabytes, 'G' to "	..
		"indicate gigabytes, or a single '*' to indicate "	..
		"'use the remaining space on the primary partition'."),
	    long_desc = _("Subpartitions further divide a primary partition for " ..
		"use with %s.  Some reasons you may want "		..
		"a set of subpartitions are:\n\n"			..
		"- you want to restrict how much data can be written "	..
		"to certain parts of the primary partition, to quell "	..
		"denial-of-service attacks; and\n"			..
		"- you want to speed up access to data on the disk.",
		App.conf.product.name),
	    special = "bsdinstaller_create_subpartitions",
	    minimum_width = "64",

	    actions = actions_list,
	    fields = fields_list,
	    datasets = datasets_list,

	    multiple = "true",
	    extensible = "true"
	})

	-- remember these subpartition selections in case we come back here.
	datasets_list = response.datasets
	fillout_missing_expert_values()

	if response.action_id == "ok" then
		if not create_subpart_descriptors(App.state.sel_part, datasets_list) then
			return step
		end
	end

	return response.result
    end
}
