-- $Id: StorageSystemUI.lua,v 1.21 2005/08/06 07:04:26 cpressey Exp $

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

module "storage_ui"

--[[-----------]]--
--[[ StorageUI ]]--
--[[-----------]]--

StorageUI = {}

--
-- Present a form to the user from which they can select any disk
-- present in the given Storage.System.
--
StorageUI.select_disk = function(tab)
	local dd
	local disk_actions = {}

	local sd = tab.sd or error("Need a storage descriptor")
	local filter = tab.filter or function() return true end

	local add_disk_action = function(tdd)
		table.insert(disk_actions,
		    {
			id = tdd:get_name(),
			name = tdd:get_desc(),
			effect = function()
				return tdd
			end
		    }
		)
	end

	for dd in sd:get_disks() do
		if filter(dd) then
			add_disk_action(dd)
		end
	end

	table.insert(disk_actions,
	    {
		id = "cancel",
		name = tab.cancel_desc or _("Cancel"),
		accelerator = "ESC",
		effect = function()
		    return nil
		end
	    }
	)

	return App.ui:present({
	    id = tab.id or "select_disk",
	    name = tab.name or _("Select a Disk"),
	    short_desc = tab.short_desc or _("Select a disk."),
	    long_desc = tab.long_desc,
	    actions = disk_actions,
	    role = "menu"
	}).result
end

--
-- Present a form to the user from which they can select any
-- partition present on the given Storage.Disk.
--
StorageUI.select_part = function(tab)
	local pd
	local part_actions = {}

	local dd = tab.dd or error("Need a disk descriptor")
	local filter = tab.filter or function() return true end

	local add_part_action = function(tpd)
		table.insert(part_actions,
		    {
			id = tostring(tpd:get_number()),
			name = tpd:get_desc(),
			effect = function()
				return tpd
			end
		    }
		)
	end

	for pd in dd:get_parts() do
		if filter(pd) then
			add_part_action(pd)
		end
	end

	table.insert(part_actions,
	    {
		id = "cancel",
		name = tab.cancel_desc or _("Cancel"),
		accelerator = "ESC",
		effect = function()
		    return nil
		end
	    }
	)

	return App.ui:present({
	    id = tab.id or "select_part",
	    name = tab.name or _("Select a Partition"),
	    short_desc = tab.short_desc or _("Select a partition."),
	    long_desc = tab.long_desc,
	    actions = part_actions,
	    role = "menu"
	}).result
end

--
-- Refresh the application's view of available storage.
-- Warn the user if the selected disk or partition was lost during this.
--
-- This accepts a list of disk/part descriptors and returns a
-- success/fail code and a list of new disk/part descriptors
-- which correspond to those passed in.
--
-- The first two list elements corrspond to a 'selected' disk and
-- partition; this function will warn the user if these were
-- lost during the resurveying.
--
StorageUI.refresh_storage = function(sel_disk, sel_part, ...)
	local sel_disk_name = ""
	local sel_part_no = 0
	local needs_disk = false
	local needs_part = false

	if sel_disk then
		needs_disk = true
		sel_disk_name = sel_disk:get_name()
	end

	if sel_part then
		needs_part = true
		sel_part_no = sel_part:get_number()
	end

	local pack = function(...)
		return arg
	end

	local ret = pack(
	    App.state.storage:resurvey(sel_disk, sel_part, unpack(arg))
	)

	sel_disk = ret[1]
	sel_part = ret[2]

	if needs_disk and not sel_disk then
		App.ui:inform(_(
		    "Warning!  Action was completed, but "	..
		    "the storage parameters of the "		..
		    "system have changed, and the previously "	..
		    "selected disk, %s, could no longer "	..
		    "be found!",
		    sel_disk_name
		))
		return false, unpack(ret)
	end

	if needs_part and not sel_part then
		App.ui:inform(_(
		    "Warning!  Action was completed, but "	..
		    "the storage parameters of the "		..
		    "system have changed, and the previously "	..
		    "selected partition, partition #%d of "	..
		    "disk %s, could no longer be found!",
		    sel_part_no,
		    sel_disk_name
		))
		return false, unpack(ret)
	end

	return true, unpack(ret)
end

return StorageUI
