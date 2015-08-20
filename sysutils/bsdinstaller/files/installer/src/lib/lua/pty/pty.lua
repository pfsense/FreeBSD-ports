-- $Id: pty.lua,v 1.2 2005/08/13 20:00:40 cpressey Exp $
-- Lua wrapper functions for Lua 5.0.x Pty (pseudo-terminal) binding.

module("pty")

Pty = require("lpty")

--[[------------]]--
--[[ Pty.Logged ]]--
--[[------------]]--

--
-- Wraps a plain Pty by adding a logging function callback.
--

Pty.Logged = {}
Pty.Logged.open = function(command, log_fn)
	local method = {} -- instance
	local pty = Pty.open(command)

	if not pty then
		log_fn("WARNING: could not open pty to '" .. command .. "'")
		return nil
	end

	log_fn(",- opened pty to '" .. command .. "'")

	method.readline = function(self)
		local line = pty:readline()
		if line then
			log_fn("< " .. line)
		else
			log_fn("( EOF )")
		end
		return line
	end

	method.write = function(self, str)
		log_fn("> " .. str)
		return pty:write(str)
	end

	method.flush = function(self)
		return pty:flush()
	end

	method.signal = function(self, sig)
		return pty:signal(sig)
	end

	method.close = function(self)
		log_fn("`- closed pty to '" .. command .. "'")
		return pty:close()
	end

	return method
end

return Pty
