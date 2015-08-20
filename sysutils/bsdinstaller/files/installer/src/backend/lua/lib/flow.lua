-- $Id: Flow.lua,v 1.33 2005/08/30 20:31:33 cpressey Exp $

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
-- Flows: "horizontal" user interface navigation elements;
--        a "Wizard"-like workflow abstraction.
--

module "flow"

local UINav = require("uinav")

Flow = {}

--[[-----------]]--
--[[ Flow.Step ]]--
--[[-----------]]--

Flow.Step = {}
Flow.Step.new = function(tab)
	--
	-- Inherit.
	--
	local method = UINav.Atom.new(tab)	-- instance (inherited)
	if not method then return nil end
	local position = tab.position or tab.container:next_free_position()
	local interactive = true
	if tab.interactive ~= nil then
		interactive = tab.interactive
	end
	local req_state = tab.req_state or {}

	--
	-- Additional accessor functions.
	--

	method.get_position = function(self)
		return position
	end

	method.is_interactive = function(self)
		return interactive
	end

	method.next = function(self)
		return self:get_container():next(self)
	end

	method.prev = function(self)
		return self:get_container():prev(self)
	end

	--
	-- Safer and cleaner compound methods (shortcuts.)
	--

	--
	-- Get the name of the overlying Menu or Flow.
	--
	method.get_upper_name = function(self)
		local upper = self:get_container():get_parent()

		if upper then
			return upper:get_name()
		else
			return _("Previous Menu")	-- XXX
		end
	end

	--
	-- Get the name of the previous Step, or, if there is no previous
	-- Step, get the name of the overlying Menu or Flow.
	--
	method.get_prev_name = function(self)
		local prev = self:get_container():prev(self)

		if prev then
			return prev:get_name()
		else
			return self:get_upper_name()
		end
	end

	--
	-- Significant: execute this step.
	--
	method.execute = function(self)
		local i, state_name

		--
		-- Assert that all needed state is present.
		--
		for i, state_name in ipairs(req_state) do
			assert(App.state[state_name],
			    "application state '" .. state_name ..
			    "' must be initialized before Flow.Step '" ..
			    self:get_name() .. " can be executed'")
		end

		--
		-- Force a garbage collection.
		--
		local used, thresh = gcinfo()
		collectgarbage(0)
		collectgarbage(thresh)

		--
		-- Run the effect function.
		--
		return self:get_effect()(self)
	end

	return method
end

--[[------]]--
--[[ Flow ]]--
--[[------]]--

Flow.new = function(tab)
	local step_id = {}		-- a dictionary of: id -> step
	local sequence = {}		-- an array of:     position -> step
	local current = nil		-- reference to the current step

	--
	-- Private functions.
	--
	local resolve_step = function(x)
		if type(x) == "string" then
			if step_id[x] then
				return step_id[x]
			else
				return nil, "No Step with id '" .. x ..
				      "' exists in this Flow"
			end
		elseif type(x) == "number" then
			if sequence[x] then
				return sequence[x]
			else
				return nil, "No Step numbered '" ..
				    tostring(x) .. "' exists in this Flow"
			end
		elseif x == nil then
			return nil, "Can't resolve nil Step"
		elseif type(x) == "table" then
			local k, v
			
			for k, v in pairs(step_id) do
				if x == v then
					return x
				end
			end
			return nil, "Step object '" .. tostring(x) ..
			      "' does not exist in this Flow"
		end
		return nil, "Values of type '" .. type(x) ..
		      "' are not valid Step references"
	end

	tab.atomClass = Flow.Step
	local method = UINav.new(tab)	-- instance

	--
	-- Add a Flow.Step to this Flow.
	--
	local super_add_atom = method.add_atom
	method.add_atom = function(self, tab)
		local step = super_add_atom(self, tab)
		if step then
			step_id[step:get_id()] = step
			table.insert(sequence, step)
			return step
		else
			return nil
		end
	end

	method.next_free_position = function(self)
		return table.getn(sequence) + 1
	end

	--
	-- Positional access of constituent Steps....
	--

	method.first = function(self)
		return sequence[1]
	end

	method.last = function(self)
		return sequence[table.getn(sequence)]
	end

	method.next = function(self)
		return sequence[current:get_position() + 1]
	end

	--
	-- Return the Flow.Step previous to this one.
	-- Because this is for the purposes of navigating backward,
	-- non-interactive Flow.Steps are skipped.
	--
	method.prev = function(self)
		local c = current
		while true do
			c = sequence[c:get_position() - 1]
			if c == nil or c:is_interactive() then
				return c
			end
		end
	end

	method.current = function(self)
		return current
	end

	--
	-- Access of Steps by attributes.
	--
	method.get_step = function(self, step_ref)
		return resolve_step(step_ref)
	end

	method.step_count = function(self)
		return table.getn(sequence) or 0
	end

	--
	-- Significant method: run through a Flow.
	--
	method.run = function(self, start_step)
		local result, errmsg
		start_step = start_step or self:first()

		local save_flow = current_flow
		current_flow = self

		current, errmsg = resolve_step(start_step)
		assert(current, errmsg)
		while true do
			App.log("Flow executing -> %s (%s)",
			    current:get_fqid(), current:get_name())
			result = current:execute()
			if not result then
				break
			end
			current, errmsg = resolve_step(result)
			assert(current, errmsg)
		end
		
		current_flow = save_flow
	end

	return method
end

return Flow
