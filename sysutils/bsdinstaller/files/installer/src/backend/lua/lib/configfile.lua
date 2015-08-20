-- $Id: ConfigFile.lua,v 1.9 2005/08/04 19:47:08 cpressey Exp $

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

module "configvars"

local App = require("app")
local CmdChain = require("cmdchain")

--[[------------]]--
--[[ ConfigVars ]]--
--[[------------]]--

ConfigVars = {}
ConfigVars.new = function()
	local method = {}	-- instance variable
	local var = {}		-- table of config var settings

	--
	-- Get the value of a named configuration variable.
	--
	method.get = function(self, name)
		return var[name]
	end

	--
	-- Set the value of a named configuration variable.
	--
	method.set = function(self, name, value)
		var[name] = value
	end

	--
	-- Populate this set of variables from a file.
	--
	-- This isn't perfect.  It doesn't handle variables
	-- with embedded newlines, for example.  It also 
	-- has to execute the script, which is undesirable.
	--
	method.read = function(self, filename, filetype)
		local cmds = CmdChain.new()
		local diff, i

		cmds:add(
		   "set | ${root}${SORT} >${tmp}env.before",
		    {
		        cmdline = ". ${filename} && set | ${root}${SORT} >${tmp}env.after",
			replacements = {
			    filename = filename
			}
		    },
		    {
		        cmdline = "${root}${COMM} -1 -3 ${tmp}env.before ${tmp}env.after",
			capture = "comm"
		    },
		    "${root}${RM} -f  ${tmp}env.before ${tmp}env.after"
		)
		
		if not cmds:execute() then
			return false
		end

		diff = cmds:get_output("comm")
		for i in diff do
			local found, ends, k, v

			found, ends, k, v =
			    string.find(diff[i], "^([^=]+)='(.*)'$")
			if found then
				self:set(k, v)
			else
				found, ends, k, v =
				    string.find(diff[i], "^([^=]+)=(.*)$")
				if found then
					self:set(k, v)
				end
			end
		end

		return true
	end

	--
	-- Generate CmdChain commands to write this set of
	-- configuration variable settings to a file.
	--
	method.cmds_write = function(self, cmds, filename, filetype)
		local k, v
		local written = false
		assert(filetype == "sh" or filetype == "resolv",
		    "Filetype must be Bourne shell 'sh' or resolv.conf 'resolv'")

		cmds:set_replacements{
		    filename = filename,
		}
		for k, v in var do
			if not written then
				written = true
				cmds:add(
				    "${root}${ECHO} >>${filename}",
				    "${root}${ECHO} '# -- BEGIN BSD Installer automatically generated configuration  -- #' >>${filename}",
				    "${root}${ECHO} '# -- Written on '`date`'-- #' >>${filename}"
				)
			end

			if filetype == "sh" then
				cmds:add("${root}${ECHO} \"" .. k .. "='" .. v .. "'\" >>${filename}")
			elseif filetype == "resolv" then
				cmds:add("${root}${ECHO} \"" .. k .. " " .. v .. "\" >>${filename}")
			end
		end

		if written then
			cmds:add(
			    "${root}${ECHO} '# -- END of BSD Installer automatically generated configuration -- #' >>${filename}"
			)
		end
	end

	--
	-- Return a generic, human-readable string representation
	-- of the settings in this ConfigVars object.
	--
	method.render = function(self)
		local k, v
		local str = ""

		for k, v in pairs(var) do
			str = str .. k .. "=" .. v .. "\n"
		end

		return str
	end

	return method
end

return ConfigVars
