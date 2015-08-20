-- $Id: dfui.lua,v 1.46 2005/08/01 22:05:45 cpressey Exp $
-- Wrapper/helper/extra abstractions for DFUI.

--[[------]]--
--[[ DFUI ]]--
--[[------]]--

--
-- This is a wrapper object around DFUI.Connection and DFUI.Progress,
-- intended to be used as a client of the the App.UIBridge object.
--

module("dfui")

DFUI = require "ldfui"
local POSIX = require "posix"

DFUI.new = function(tab)
	local dfui = {}
	local transport = tab.transport or "tcp"
	local rendezvous = tab.rendezvous or "9999"
	local connection
	local log = tab.log or function(fmt, ...)
		print(string.format(fmt, unpack(arg)))
	end

	dfui.start = function(dfui)
		connection = DFUI.Connection.new(transport, rendezvous)
		if connection:start() == 0 then
			connection:stop()
			log("Could not establish DFUI connection " ..
			    " on %s:%s", transport, rendezvous)
			return false
		end
		log("DFUI connection on %s:%s successfully established",
			transport, rendezvous)
		return true
	end

	dfui.stop = function(dfui)
		return connection:stop()
	end

	dfui.present = function(dfui, tab)
		local response = connection:present(tab)
		local i, action

		-- Handle the 'effect' field which may be given in
		-- any action table within a form table.  When it
		-- is given, it should be a function which the
		-- user wishes to be executed automatically when
		-- the response is caused by that action.  This lets
		-- the user write simpler Lua code (c:present(f) can
		-- execute things directly, instead of returning an
		-- id code which the user must look up in a table etc.)

		for i, action in ipairs(tab.actions or {}) do
			if action.id == response.action_id and
			   type(action.effect) == "function" then
				response.result = action.effect(response)
			end
		end

		return response
	end

	dfui.set = function(dfui, key, value)
		if key == "lang_envars" then
			return connection:set_lang_envars(id) ~= 0
		elseif key == "lang_syscons" then
			return connection:set_lang_syscons(id) ~= 0
		end
		if connection:set_global_setting(key, value) ~= 0 then
			return nil, "Cancelled"
		else
			return true
		end
	end

	--
	-- Constructor within a constructor, here...
	--
	dfui.new_progress_bar = function(dfui, tab)
		local method = {}
		local pr
		local title = tab.title or "Working..."
		local short_desc = tab.short_desc or title
		local long_desc = tab.long_desc or ""
		local amount = 0

		pr = DFUI.Progress.new(connection,
		    title, short_desc, long_desc, amount)

		method.start = function(method)
			return pr:start()
		end

		method.set_amount = function(method, new_amount)
			return pr:set_amount(new_amount)
		end

		method.set_short_desc = function(method, new_short_desc)
			return pr:set_short_desc(new_short_desc)
		end

		method.update = function(method)
			return pr:update()
		end

		method.stop = function(method)
			return pr:stop()
		end

		return method
	end

	return dfui
end

return DFUI
