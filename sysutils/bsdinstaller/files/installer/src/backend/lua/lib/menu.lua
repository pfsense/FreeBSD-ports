-- $Id: Menu.lua,v 1.20 2005/08/04 23:50:33 cpressey Exp $

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

-- Menus - "vertical" UINav elements.

module "menu"

local UINav = require("uinav")

Menu = {}

--[[-----------]]--
--[[ Menu.Item ]]--
--[[-----------]]--

Menu.Item = {}
Menu.Item.new = function(tab)			-- Constructor.
	--
	-- Inherit
	--
	local method = UINav.Atom.new(tab)	-- instance, inherited.
	if not method then return nil end

	--
	-- Additional methods.
	--
	method.to_action = function(self)
		return {
		    id = self:get_id(),
		    name = self:get_name(),
		    short_desc = self:get_short_desc(),
		    long_desc = self:get_long_desc(),
		    effect = function()
			App.log("Menu.Item Selected -> %s (%s)",
			    self:get_fqid(), self:get_name())
			return self:get_effect()()
		    end
		}
	end

	return method
end

--[[------]]--
--[[ Menu ]]--
--[[------]]--

-- Global (and public) symbolic constants:
Menu.CONTINUE = {}
Menu.DONE = {}

Menu.new = function(tab)			-- Constructor.
	--
	-- Inherit.
	--
	tab.atomClass = Menu.Item
	local method = UINav.new(tab)	-- instance, inherited.

	--
	-- Private functions.
	--

	local make_exit_item = function()
		local exit_item_name
	
		if parent ~= nil then
			exit_item_name = _(
			    "Return to %s",
			    parent:get_name()
			)
		else
			exit_item_name = _("Exit")
		end

		return {
		    name = exit_item_name,
		    accelerator = "ESC",
		    effect = function()
			return Menu.DONE
		    end
		}
	end

	local map_items_to_actions = function(menu)
		local item
		local action = {}

		for item in menu:get_atoms() do
			table.insert(action, item:to_action())
		end

		return action
	end

	--
	-- Additional methods.
	--

	--
	-- Present this menu to the user.
	--
	method.present = function(self)
		self:push()
		local response = self:get_ui():present{
			id = self:get_id(),
			name = self:get_name(),
			short_desc = self:get_short_desc(),
			long_desc = self:get_long_desc(),
			role = "menu",
			actions = map_items_to_actions(self)
		}
		self:pop()
		return response
	end

	--
	-- Display this menu repeatedly, until it returns something that
	-- the continue_constraint returns Menu.DONE to.
	--
	method.loop = function(self)
		local result = Menu.CONTINUE
	
		while result == Menu.CONTINUE do
			result = self:present().result
			if tab.continue_constraint then
				result = tab.continue_constraint(result)
			end
		end
	end

	local save_populate = method.populate
	method.populate = function(self, from_dir)
		save_populate(self, from_dir)
		-- Automatically add an 'exit' Menu.Item.
		method:add_atom(tab.exit_item or make_exit_item())
		return method
	end

	return method
end

return Menu
