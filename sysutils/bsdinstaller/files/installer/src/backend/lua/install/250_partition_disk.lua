-- $Id: 250_partition_disk.lua,v 1.79 2005/10/05 21:29:03 cpressey Exp $

--
-- Partition editor.
--
-- XXX This should probably be split up into more than one step.
-- XXX This should probably be compartmentalized into StorageUI.
--

local options_list = {}
local sysid_to_name_map = {}
local name_to_sysid_map = {}
local i, l
for i, l in ipairs(App.conf.sysids) do
	local name, sysid = l[1], l[2]
	table.insert(options_list, name)
	sysid_to_name_map[sysid] = name
	name_to_sysid_map[name] = sysid
end

--
-- Return a list of datasets apropos for formatting just one big partition.
--
local populate_one_big_partition = function(dd)
	return {
		{
			sectors   = "*",
			sysid     = sysid_to_name_map[App.conf.default_sysid],
			active    = "Y"
		}
	}
end

--
-- Get a list of datasets by examining what is currently in the disk
-- representation (i.e. in the Storage.Disk structure, which was
-- presumably gotten from Storage.System:survey() at some point.)
--
local populate_from_disk = function(dd)
	local pd
	local list = {}
	local active_pd = nil

	local toyn = function(bool)
		if bool then
			return "Y"
		else
			return "N"
		end
	end

	local offset = dd:get_geometry_sec()

	--
	-- Look for the active partition.
	--
	for pd in dd:get_parts() do
		if pd:is_active() then
			active_pd = pd
			break
		end
	end

	--
	-- If none was found, assume the first as the active partition.
	--
	if not active_pd then
		for pd in dd:get_parts() do
			active_pd = pd
			break
		end
	end

	for pd in dd:get_parts() do
		local start = pd:get_start()
		local sectors = pd:get_capacity():in_units("S")
		local sysid = sysid_to_name_map[pd:get_sysid()] or
				tostring(pd:get_sysid())

		if start ~= offset then
			App.ui:inform(_(
			    "WARNING: The partition layout currently "	..
			    "on this disk is non-standard.  It may "	..
			    "have gaps in between partitions, or the "	..
			    "partitions may be listed in something "	..
			    "other than strictly increading order. "	..
			    "\n\nWhile %s can handle this situation, "	..
			    "this installer's partition editor cannot "	..
			    "at present. You will be given the option "	..
			    "to completely repartition this disk, but "	..
			    "if you wish to retain any existing "	..
			    "information on the disk, you should exit "	..
			    "the installer and use a tool such as "	..
			    "`fdisk' to manually create a %s partition " ..
			    "on it before continuing.",
			    App.conf.product.name, App.conf.product.name
			))
			return populate_one_big_partition(dd)
		end
		offset = offset + sectors

		--
		-- Create the dataset.
		--
		table.insert(list, {
			sectors = tostring(sectors),
			sysid = sysid,
			active = toyn(pd == active_pd)
		})
	end

	return list
end

--
-- Actually show the partition editor and let the user edit partitions.
-- This does not do any setup or validation.
--
local edit_partitions = function(step, datasets_list)
	assert(datasets_list, "We need a list of datasets here, please")

	local fields_list = {
		{
		    id = "sectors",
		    name = _("Size (in Sectors)")
		},
		{
		    id = "sysid",
		    name = _("Partition Type"),
		    options = options_list,
		    editable = "false"
		},
		{
		    id = "active",
		    name = _("Active?"),
		    control = "checkbox"
		}
	}

	local actions_list = {
		{
		    id = "ok",
		    name = _("Accept and Create"),
	        },
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to %s", step:get_prev_name()),
	        },
		{
		    id = "revert",
		    name = _("Revert to Partitions on Disk"),
	        }
	}

	local form = {
	    id = "edit_partitions",
	    name = _("Edit Partitions"),
	    short_desc = _("Select the partitions (also known "		..
		"as `slices' in BSD tradition) you want to "		..
		"have on this disk.\n\n"				..
		"For Size, enter a raw size in sectors "		..
		"(1 gigabyte = 2097152 sectors) "			..
		"or a single '*' to indicate "				..
		"'use the remaining space on the disk'."),
	    special = "bsdinstaller_edit_partitions",
	    minimum_width = "64",

	    actions = actions_list,
	    fields = fields_list,
	    datasets = datasets_list,

	    multiple = "true",
	    extensible = "true"
	}

	while true do
		local response = App.ui:present(form)
		if response.action_id == "ok" then
			return true, response.datasets
		end
		if response.action_id == "cancel" then
			return false, datasets_list
		end
		if response.action_id == "revert" then
			datasets_list = populate_from_disk(App.state.sel_disk)
		end
	end
end

--
-- Given a proposed size for a partition, check that it starts on a
-- head boundary and ends on a cylinder boundary.  Allow the user to
-- easily adjust it to do so if it does not.
--
local align_to_boundary = function(dd, size, num, start)

	local is_divisible_by = function(x, y)
		return math.floor(x / y) == math.ceil(x / y)
	end
			
	--
	-- Get "sectors per track" value - the start sector
	-- should be divisible by this value in order
	-- for the partition to be aligned to head boundaries.
	--
	local sectrk = dd:get_geometry_sec()

	--
	-- Get "blocks per cylinder" value - the end sector
	-- should be divisible by this value in order
	-- for the partition to be aligned to cylinder boundaries.
	--
	local cylsec = dd:get_geometry_head() * sectrk

	--
	-- The start sector MUST be on a head boundary,
	-- or we're in the Twilight Zone.
	--
	assert(is_divisible_by(start, sectrk))

	--
	-- From the start, and the proposed size, calculate the end sector.
	--
	local end_sector = start + size

	--
	-- Check to see if it ends on a cylinder boundary.
	-- If so, everything's peachy, and just return.
	--
	if is_divisible_by(end_sector, cylsec) then
		return size
	end

	--
	-- Calculate the next smallest and next largest
	-- cylinder boundaries where the end sector could be.
	--
	local shrink_sec = math.floor(end_sector / cylsec) * cylsec
	local expand_sec = math.ceil(end_sector / cylsec) * cylsec

	--
	-- Calculate the next smallest and largest sizes of the partition
	-- such that its end sector falls on a cylinder boundary.
	--
	local shrink_to = shrink_sec - start
	local expand_to = expand_sec - start

	--
	-- Ask the user what they want to do.
	--
	local response = App.ui:present{
	    id = "align_partition",
	    name = _("Align Partition"),
	    short_desc = _(
		"Partition #%d does not begin and end "		..
		"on a cylinder boundary (i.e. its size, %d, "	..
		"is not a multiple of %d.)\n\nWould you "	..
		"like to adjust it?  NOTE that this may "	..
		"result in subsequent partitions being moved!",
		num, size, cylsec
	    ),

	    actions = {
		{
		    id = "shrink",
		    name = _("Shrink to %d Sectors", shrink_to),
	        },
		{
		    id = "expand",
		    name = _("Expand to %d Sectors", expand_to),
	        },
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to Edit Partitions"),
	        }
	    }
	}

	if response.action_id == "shrink" then
		return shrink_to
	end
	if response.action_id == "expand" then
		return expand_to
	end

	return nil
end

--
-- Given a proposed size for a partition, check that it does not
-- exceed or fall short of the disk size.  If it does, allow the
-- user to easily adjust it.
--
local align_to_disk_size = function(dd, size, num, used_size, disk_size, is_last)
	if used_size + size == disk_size then
		return size	-- perfect fit
	end
	if used_size + size < disk_size and not is_last then
		return size	-- don't worry, still some partitions to go
	end

	local response
	local new_size = disk_size - used_size

	if used_size + size < disk_size then
		local under = disk_size - (used_size + size)
		response = App.ui:present{
		    id = "expand_partition",
		    name = _("Expand Partition"),
		    short_desc = _(
			"Partition #%d falls short of the end of " ..
			"the disk by %d sectors (%s).  Would you " ..
			"like to expand it so that it takes up the " ..
			"entire rest of the disk?",
			num, under,
			Storage.Capacity.new(under, "S"):format()
		    ),
	
		    actions = {
			{
			    id = "ok",
			    name = _("Expand to %d Sectors", new_size)
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("Return to Edit Partitions")
			}
		    }
		}
	else
		local over = (used_size + size) - disk_size
		response = App.ui:present{
		    id = "truncate_partition",
		    name = _("Truncate Partition"),
		    short_desc = _(
			"Partition #%d extends past the end of "   ..
			"the disk by %d sectors (%s).  Would you " ..
			"like to shrink it so that it fits?",
			num, over,
			Storage.Capacity.new(over, "S"):format()
		    ),

		    actions = {
			{
			    id = "ok",
			    name = _("Shrink to %d Sectors", new_size)
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("Return to Edit Partitions")
			}
		    }
		}
	end

	if response.action_id == "ok" then
		return new_size
	end

	return nil
end

--
-- Validate that the given datasets are properly formed.
--
local check_datasets = function(dd, datasets_list)
	local i, dataset
	local result, size
	local disk_size = dd:get_capacity():in_units("S")
	local used_size = dd:get_geometry_sec()		-- initial offset
	local wildcard_dataset = nil
	local active_dataset = nil

	--
	-- Check to see that they configured at least one.
	--
	if table.getn(datasets_list) == 0 then
		App.ui:inform(_(
		    "No partitions were configured!  Please " ..
		    "create at least one partition."
		))
		return false
	end

	--
	-- Check that each of them has a valid sysid and capacity.
	--
	for i, dataset in ipairs(datasets_list) do
		if tonumber(dataset.sysid) == nil and
		   name_to_sysid_map[dataset.sysid] == nil then
			App.ui:inform(_(
			    "'%s' is not a recognized partition type. " ..
			    "Please use a numeric identifier if you "	..
			    "wish to use an unlisted partition type.",
			    dataset.sysid
			))
			return false
		end

		if dataset.active == "Y" then
			if active_dataset then
				App.ui:inform(_(
				    "Only one partition may be marked 'active'."
				))
				return false
			end
			active_dataset = dataset
		end

		if dataset.sectors == "*" then
			if wildcard_dataset ~= nil then
				App.ui:inform(_(
				    "Only one partition may have a " ..
				    "capacity of '*'."
				))
				return false
			end
			wildcard_dataset = dataset
		else
			result, size = pcall(function()
				local s = tonumber(dataset.sectors)
				assert(dataset.sectors == tostring(s))
				return s
			end)
			if not result then
				App.ui:inform(_(
				    "'%s' is not a valid size in sectors.",
				    dataset.sectors
				))
				return false
			end

			size = align_to_disk_size(dd, size, i, used_size, disk_size,
			    i == table.getn(datasets_list) and wildcard_dataset == nil)
			if not size then
				return false
			end

			size = align_to_boundary(dd, size, i, used_size)
			if not size then
				return false
			end
			
			dataset.sectors = tostring(size)

			used_size = used_size + size
		end
	end

	if not active_dataset then
		App.ui:inform(_(
		    "One partition must be marked 'active'."
		))
		return false
	end

	--
	-- Fill in the wildcard dataset.
	--
	if wildcard_dataset ~= nil then
		wildcard_dataset.sectors = tostring(disk_size - used_size)
		used_size = used_size + tonumber(wildcard_dataset.sectors)
	end

	--
	-- Assert that the sizes total up exactly.
	--
	-- XXX in the future, we may want to allow passing through "bad"
	-- sizes, to not disturb existing whacky partitionings.
	-- In which case, we'll need to drop this check.
	--
	assert(used_size == disk_size)

	return true
end

--
-- Return a list of partitions that have been changed by the user's edits,
-- as well as a list of partitions that have been added by the user.
-- Assumes check_datasets has already been called successfully.
--
local find_changed_and_added_partitions = function(dd, datasets_list)
	local i, dataset
	local offset = dd:get_geometry_sec()		-- initial offset
	local changed = {}
	local added = {}

	for i, dataset in ipairs(datasets_list) do
		local pd = dd:get_part_by_number(i)
		local size = tonumber(dataset.sectors)

		local descriptor = {
		    pd = pd,
		    size = size,
		    offset = offset
		}

		if not pd then
			table.insert(added, descriptor)
		elseif pd:get_capacity():in_units("S") ~= size or
		       pd:get_start() ~= offset then
			table.insert(changed, descriptor)
		end

		offset = offset + size
	end

	return changed, added
end

--
-- Given the list of datasets, actually create the Storage.Partition objects.
-- This function assumes that check_datasets has already been called.
-- This function can never fail.
--
local create_partitions_from_datasets = function(dd, datasets_list)
	local i, dataset
	local part_no = 1
	local disk_size = dd:get_capacity():in_units("S")
	local offset = dd:get_geometry_sec()			-- initial offset
	local size
	local sysid

	dd:clear_parts()
	for i, dataset in ipairs(datasets_list) do
		size = tonumber(dataset.sectors)

		dd:add_part(Storage.Partition.new{
		    parent = dd,
		    number = part_no,
		    start  = offset,
		    size   = size,
		    sysid  = name_to_sysid_map[dataset.sysid] or tonumber(dataset.sysid),
		    active = (dataset.active == "Y")
		})

		offset = offset + size
		part_no = part_no + 1
	end
end

--
-- Actually confirm with the user and make changes to the disk.
--
local alter_disk = function(dd, datasets_list, changed)
	--
	-- Generate text from the list of changed partitions
	--
	local i, tab
	local changed_list = ""
	for i, tab in ipairs(changed) do
		changed_list = changed_list .. _(
		    "Partition #%d (was %d long at %d; now %d long at %d)\n",
		    tab.pd:get_number(),
		    tab.pd:get_capacity():in_units("S"), tab.pd:get_start(),
		    tab.size, tab.offset
		)
	end

	--
	-- Confirm that this is what the user wants to do
	--
	local confirm = function()
		local response = App.ui:present{
		    id = "confirm_alter_disk",
		    name = _("Alter these Partitions?"),
		    short_desc = _(
			    "WARNING!  The parameters of the following " ..
			    "partitions have been MODIFIED for the disk" ..
			    "\n\n%s\n\n"				 ..
			    "ANY meaningful data that may currently be " ..
			    "on ANY of them will NOT remain meaningful " ..
			    "after this operation has completed. "	 ..
			    "In other words, they should be considered " ..
			    "IRREVOCABLY ERASED if you proceed!\n\n%s\n" ..
			    "Are you ABSOLUTELY SURE you wish to take "  ..
			    "this action?  This is your LAST CHANCE "    ..
			    "to cancel!",
			    dd:get_desc(),
			    changed_list
		    ),
		    actions = {
			{
			    id = "ok",
			    name = _("Alter these Partitions")
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("Return to Edit Partitions")
			}
		    }
		}
		return response.action_id == "ok"
	end

	if dd:has_been_touched() or confirm() then
		local cmds = CmdChain.new()

		--
		-- Create partition descriptors under the disk,
		-- then create the commands to partition based on
		-- them, then execute those commands.
		-- XXX might be better to:
		-- - create the partition descriptors under a
		--   temporary disk descriptor, or
		-- - refresh the partition descriptors if the
		--   partitioning fails, or
		-- - create the commands directly from the
		--   datasets
		-- ... so that it is possible/easy to restore the
		-- list of partition descriptors back to what is
		-- really on the disk.
		--
		create_partitions_from_datasets(dd, datasets_list)
		dd:cmds_partition(cmds)

		if not cmds:execute() then
			App.ui:inform(_(
			    "The disk\n\n%s\n\nwas "		  ..
			    "not correctly partitioned, and may " ..
			    "now be in an inconsistent state. "	  ..
			    "We recommend partitioning it again"  ..
			    "before attempting to install "	  ..
			    "%s on it.",
			    dd:get_desc(),
	    		    App.conf.product.name
			))
			return false
		end

		--
		-- The extents of the Storage.System have probably
		-- changed, so refresh our knowledge of it.
		--
		local result
		result, App.state.sel_disk, App.state.sel_part, dd =
		    StorageUI.refresh_storage(
			App.state.sel_disk, App.state.sel_part, dd
		    )
		if not result then
			return false
		end

		--
		-- XXX mark all changed partitions as having been
		-- changed, here.
		--

		App.ui:inform(_(
		    "The disk\n\n%s\n\nwas successfully partitioned.",
		    dd:get_desc()
		))

		return true
	else
		App.ui:inform(_(
		    "Action cancelled.  No partitions were changed."
		))
		return false
	end
end

--
-- High-level function which drives all others:
--   populate_from_disk
--   edit_partitions
--   check_datasets
--   create_partitions_from_datasets
--
local let_user_edit_partitions = function(step, population_function)
	local ok = false
	local datasets_list = population_function(App.state.sel_disk)

	while not ok do
		ok, datasets_list = edit_partitions(step, datasets_list)
		if not ok then -- user cancelled
			return step:prev()
		end
		ok = check_datasets(App.state.sel_disk, datasets_list)
	end

	--
	-- Determine what changed.
	--
	local changed, added =
	    find_changed_and_added_partitions(App.state.sel_disk, datasets_list)

	if table.getn(changed) == 0 and table.getn(added) == 0 then
		response = App.ui:present{
		    id = "partition_anyway",
		    name = _("Partition Anyway?"),
		    short_desc = _(
			"No changes appear to have been made to the "	..
			"partition table layout.\n\n"			..
			"Do you want to execute the commands to "	..
			"partition the disk anyway?"
		    ),

		    actions = {
			{
			    id = "ok",
			    name = _("Yes, partition %s",
			        App.state.sel_disk:get_name())
			},
			{
			    id = "skip",
			    name = _("No, Skip to Next Step")
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = _("No, Return to Edit Partitions")
			}
		    }
		}
		if response.action_id == "cancel" then
			return step
		elseif response.action_id == "skip" then
			return step:next()
		end
	end

	--
	-- Actually write the partitions to the disk, with accompanying warnings
	-- and such in the user interface.
	--
	if alter_disk(App.state.sel_disk, datasets_list, changed) then
		return step:next()
	else
		return step
	end
end

--
-- The Flow.Step descriptor itself follows.
--
return {
    id = "partition_disk",
    name = _("Partition Disk"),
    req_state = { "storage", "sel_disk" },
    effect = function(step)
	--[[--
	if App.state.sel_disk:has_been_touched() then
		return let_user_edit_partitions(step, populate_from_disk)
	end
	--]]--

	if App.state.sel_disk:is_mounted() then
		local response = App.ui:present{
		    id = "partition_disk",
		    name = _("Partition Disk?"),
		    short_desc = _(
			"One or more subpartitions of one or more "	..
			"primary partitions of the selected disk "	..
			"are already in use (they are currently "	..
			"mounted on mountpoints in the filesystem.) "	..
			"You cannot repartition the disk under "	..
			"these circumstances. If you wish to do so, "	..
			"you must unmount the subpartitions before "	..
			"proceeding."
		    ),
		    actions = {
			{
			    id = "unmount",
			    name = _("Unmount Subpartitions"),
			    effect = function()
				local cmds = CmdChain.new()
				App.state.sel_disk:cmds_unmount_all_under(cmds)
				cmds:execute()
				return step
			    end
			},
			{
			    id = "skip",
			    name = _("Skip this Step"),
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
		}
		return response.result
	end

	if App.state.sel_disk:get_part_count() == 0 then
		App.ui:inform(_(
		    "No valid partitions were found on this disk. "	..
		    "You will have to create at least one in which "	..
		    "to install %s."					..
		    "\n\n"						..
		    "A single partition covering the entire disk "	..
		    "will be selected for you by default, but if you "	..
		    "wish, you may create multiple partitions instead.",
		    App.conf.product.name
		))
		return let_user_edit_partitions(step, populate_one_big_partition)
	end

	local response = App.ui:present{
	    id = "partition_disk",
	    name = _("Partition Disk?"),
	    short_desc = _(
		"You may now partition this disk if you desire."		..
		"\n\n"								..
		"If you formatted this disk, and would now like to install "	..
		"multiple operating systems on it, you can reserve a part "	..
		"of the disk for each of them here.  Create multiple "		..
		"partitions, one for each operating system."			..
		"\n\n"								..
		"If this disk already has operating systems on it that you "	..
		"wish to keep, you should be careful not to change the "	..
		"partitions that they are on, if you choose to partition."	..
		"\n\n"								..
		"Partition this disk?"
	    ),
	    actions = {
	        {
		    id = "ok",
	    	    name = _("Partition Disk"),
		    effect = function()
		    	return let_user_edit_partitions(step, populate_from_disk)
		    end
		},
		{
		    id = "skip",
		    name = _("Skip this Step"),
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
	}
	return response.result
    end
}
