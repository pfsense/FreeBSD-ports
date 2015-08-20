-- $Id: uinav.lua,v 1.12 2005/08/30 20:31:33 cpressey Exp $

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

--
-- UINav is the "Abstract Base Class" for user interface navigation elements,
-- of which there are currently two:
--   o  Flow, which is linear or "vertical", and
--   o  Menu, which is random-access or "horizontal".
-- Both of them are implemented as "subclasses" of UINav.
--
-- In addition, Flows are composed of many Steps; Menus are composed of
-- many Items.  Each of these objects inherits from UINav.Atom.
--

module "uinav"

local POSIX = require("posix")
local FileName = require("filename")
local App = require("app")

UINav = {}

--[[------------]]--
--[[ UINav.Atom ]]--
--[[------------]]--

--
-- Global "class" variable.
-- This isn't local, because subclasses need access to it.
--

UINav.Atom = {}

--
-- Classwide-private state and functions.
--

local next_id = 0		-- next unique id to create
local new_id = function()	-- Create a new unique identifier.
	next_id = next_id + 1
	return tostring(next_id)
end

local globs_to_regexes = function(in_tab)
	local k, v
	local out_tab = {}

	for k, v in pairs(in_tab) do
		out_tab["^" .. string.gsub(k, "%*", ".*") .. "$"] = v
	end

	return out_tab
end

--
-- Load configuration file, which tells us which UINav.Atoms to ignore,
-- by specifying their id's.
--
local uinav_ctl = globs_to_regexes(App.conf.ui_nav_control or {})

--
-- Constructor.
--
UINav.Atom.new = function(tab)
	local method = {}	-- instance method table
	local id = tab.id or "uinav_atom_" .. new_id()
	local name = tab.name or ""
	local short_desc = tab.short_desc
	local long_desc = tab.long_desc
	local effect = assert(tab.effect,
	    "UINav atom '" .. name .. "' is missing 'effect' property")
	assert(type(effect) == "function",
	    "'effect' of UINav atom '" .. name .. "' is not a function")
	local container = assert(tab.container) -- UINav that we belong to

	-- Private functions.
	local assemble_fqid = function()
		local s = id
		local c = container

		while c ~= nil do
			s = c:get_id() .. "/" .. s
			c = c:get_parent()
		end

		return s
	end
	local fqid = assemble_fqid()

	--
	-- Methods:
	--

	method.get_id = function(self)
		return id
	end

	method.get_name = function(self)
		return name
	end

	method.get_short_desc = function(self)
		return short_desc
	end

	method.get_long_desc = function(self)
		return long_desc
	end

	method.get_effect = function(self)
		return effect
	end

	method.get_container = function(self)
		return container
	end

	--
	-- Retrieve the fully qualified id of this Item.
	--
	method.get_fqid = function(item)
		return fqid
	end

	local is_available = function()
		local k, v

		for k, v in pairs(uinav_ctl) do
			if string.find(fqid, k) then
				if v == "ignore" then
					return false
				end
			end
		end
	
		return true
	end

	--
	-- Constructor.  Might fail to return anything!
	--

	if is_available() then
		return method
	else
		App.log("UINav.Atom '%s' was configured as 'ignore'", fqid)
		return nil
	end
end

--[[-------]]--
--[[ UINav ]]--
--[[-------]]--

--
-- Classwide private state.
--

local current_uinav = nil	-- Currently active UI navigation element,
				-- be it a Menu or a Flow.
local uinav_stack = {}

--
-- Constructore: create a new UI navigation object instance.
--
UINav.new = function(tab)
	local method = {}	-- instance
	local atom = {}		-- Contains these atoms
	local id = tab.id or "uinav_" .. new_id()
	local ui = tab.ui or App.ui
	local name = tab.name or ""
	local short_desc = tab.short_desc
	local long_desc = tab.long_desc
	local parent = tab.parent or current_uinav
	local atomClass = assert(tab.atomClass)

	--
	-- Methods.
	--

	method.add_atom = function(self, tab)
		-- XXX: if tab is already an object of type atomClass,
		-- just add it to the atom table.  Otherwise:
		tab.container = self
		local new_atom = atomClass.new(tab)
		if new_atom then
			table.insert(atom, new_atom)
			return new_atom
		else
			return nil
		end
	end

	method.get_id = function(self)
		return id
	end

	method.get_name = function(self)
		return name
	end

	method.get_short_desc = function(self)
		return short_desc
	end

	method.get_long_desc = function(self)
		return long_desc
	end

	method.get_ui = function(self)
		return ui
	end

	method.get_parent = function(self)
		return parent
	end

	--
	-- Iterator over contained Atoms.
	--
	method.get_atoms = function(self)
		local i, n = 0, table.getn(atom)

		return function()
			if i <= n then
				i = i + 1
				return atom[i]
			end
		end
	end

	--
	-- Save and restore the current UINav element.
	--

	method.push = function(self)
		table.insert(uinav_stack, current_uinav)
		current_uinav = self
	end

	method.pop = function(self)
		current_uinav = table.remove(uinav_stack)
	end

	--
	-- Populate this UINav with Atoms defined by the Lua scriptlets
	-- in a given directory.
	-- Each scriptlet should return a table describing the Atom.
	--
	method.populate = function(self, from_dir)
		local i, filename, filenames

		-- XXX this is perhaps not ideal, but it gets the point across.
		if from_dir == "." then
			from_dir = FileName.dirname(App.get_current_script())
		end

		filenames = POSIX.dir(from_dir)
		table.sort(filenames)

		for i, filename in ipairs(filenames) do
			local full_filename = from_dir .. "/" .. filename

			--
			-- Ensure that the scriptlet is not the currently running
			-- script; is not a directory; does not begin with a .;
			-- and ends with '.lua'.  If all these pass, add it.
			--
			-- XXX this filename check isn't perfect either (it
			-- assumes the current script is in the dir) but
			-- for practical purposes it won't fail utterly
			--
			if filename == FileName.basename(App.get_current_script()) then
				App.log("'%s' skipped, it is the currently executing script", filename)
			elseif FileName.is_dir(full_filename) then
				App.log("'%s' skipped, it is a directory", full_filename)
			elseif not string.find(filename, "^[^%.].*%.lua$") then
				App.log("'%s' skipped, it is not a validly named scriptlet", filename)
			else
				local atom_tab, reason = App.run_script(full_filename)
				if atom_tab then
					local atom = method:add_atom(atom_tab)
					if atom then
						App.log("registered UINav atom '%s'", atom:get_fqid())
					else
						App.log("couldn't register UINav atom from file '%s'", filename)
					end
				else
					App.log("'%s' skipped, reason: %s", filename, reason)
				end
			end
		end

		return method
	end

	return method
end

return UINav
