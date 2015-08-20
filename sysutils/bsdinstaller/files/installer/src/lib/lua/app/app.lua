-- $Id: app.lua,v 1.70 2005/09/13 19:25:29 cpressey Exp $
-- Lua-based Application Environment.

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

-- BEGIN app.lua --

module("app")

local POSIX = require("posix")
local FileName = require("filename")
local Pty = require("pty")

--[[-----]]--
--[[ App ]]--
--[[-----]]--

--
-- Application Environment.
--
-- This package provides a global environment or framework for an
-- application written in Lua.  It was written for the BSD Installer,
-- but should be general enough to be suitable for many applications
-- which require some or all of the following:
--
--   o  highly abstracted user interface facilities
--   o  configuration, either loaded from configuration files,
--      or read from the command-line arguments, including:
--      - locations of directories (cmd root dir, temp dir, etc)
--      - names of system commands
--      - etc
--   o  application-wide state
--   o  logging
--   o  temporary files
--
-- Although App superficially resembles a class, it cannot be instantiated.
-- Hence it can be considered a "package", or a "singleton class" or
-- "static object", with a single global "instance".
--

--
-- Container.
--

App = {}

--
-- Private data.
--

local last_log_time = -1
local current_script = nil

--
-- Private functions.
--

--
-- Add a directory to package.path (used by compat-5.1.)
--
local add_pkg_path = function(dir)
	if package and package.path then
		if package.path ~= "" then
			package.path = package.path .. ";"
		end
		package.path = package.path .. tostring(dir) .. "/?.lua"
	end
end

--
-- Public static methods.
--

-----------------------------------------------------------------------
-------------------------- Startup and Shutdown -----------------------
-----------------------------------------------------------------------

--
-- Application startup.
--
App.start = function(arg)
	--
	-- Begin setting up the App.
	-- Initialize the global application containers.
	-- Make the configuration table "see through" so that
	-- the configuration files have access to all Lua functions.
	--
	App.conf = setmetatable({}, { __index = _G })
	App.state = {}

	--
	-- Set the current script to the script that was invoked, as it was
	-- recorded as the first command-line argument.
	--
	current_script = assert(arg[0], "Missing script name")

	--
	-- Set up the default search path, based on the current script.
	--
	add_pkg_path(FileName.dirname(current_script) .. "lib")

	--
	-- Process each command-line argument in turn.
	--
	local argn = 1
	while arg[argn] do
		if arg[argn] == "-L" then
			argn = argn + 1
			add_pkg_path(arg[argn])
		elseif arg[argn] == "-t" then -- obsolete dfui transport
			argn = argn + 1
			App.set_property("ui.transport=" .. arg[argn])
		elseif arg[argn] == "-r" then -- obsolete dfui rendezvous
			argn = argn + 1
			App.set_property("ui.rendezvous=" .. arg[argn])
		elseif string.find(arg[argn], "=") then
			App.set_property(arg[argn])
		else
			App.load_conf(arg[argn])
		end

		argn = argn + 1
	end

	--
	-- Fix up configuration:
	--

	--
	-- Set the product name to the OS name, if not given.
	--
	App.conf.product.name =
	    App.conf.product.name or App.conf.os.name
	App.conf.product.version =
	    App.conf.product.version or App.conf.os.version

	--
	-- Make sure each directory in App.conf.dir ends with a slash.
	--
	local name, dir
	for name, dir in App.conf.dir do
		App.conf.dir[name] = FileName.add_trailing_slash(dir)
	end

	--
	-- Open our logfile.
	--
	App.open_log(App.conf.dir.tmp .. App.conf.log_filename)
	App.log("%s started", App.conf.app_name)

	--
	-- Set up temporary files.
	--
	App.tmpfile = {}

	--
	-- Seed the random-number generator.
	--
	math.randomseed(os.time())
end

--
-- Start the user interface.  XXX this interface is a bit ugly now.
--
App.start_ui = function(ui)
	--
	-- Set up the App's UI bridge.
	--
	App.ui = ui or App.UIBridge.new(App.DummyUI)
	if not App.ui:start() then
		App.log_fatal("Could not start user interface")
	end
end

--
-- Application shutdown.
--
App.stop = function()
	App.clean_tmpfiles()
	App.ui:stop()
	App.log("Shutting down")
	App.close_log()
end

-----------------------------------------------------------------------
----------------- Locating and Running Scriptlets ---------------------
-----------------------------------------------------------------------

--
-- Determine the name of the currently running script.
--
App.get_current_script = function()
	return current_script
end

--
-- Run a Lua script.
-- Note that the script name must be either relative to the
-- current working directory, or fully-qualified.
-- If relative to the current script, use App.find_script first.
-- This function returns two values:
--    the first is the success code, either true or false
--    if true, the second is the result of the script
--    if false, the second is an error message string.
--
App.run = function(script_name, ...)
	local save_script = current_script
	local save_args = ARG
	local ok, result, fun, errmsg

	if App.conf.fatal_errors then
		assert(script_name and type(script_name) == "string",
		       "bad filename " .. tostring(script_name))
	end
	if not script_name or type(script_name) ~= "string" then
		return false, "bad filename " .. tostring(script_name)
	end

	fun, errmsg = loadfile(script_name)

	if App.conf.fatal_errors then
		assert(fun, errmsg)
	end
	if not fun then
		return false, errmsg
	end

	current_script = script_name
	ARG = arg
	if App.conf.fatal_errors then
		ok = true
		result = fun()
	else
		ok, result = xpcall(fun, function(errmsg)
					     return debug.traceback(errmsg)
					 end)
	end
	ARG = save_args
	current_script = save_script

	return ok, result
end

--
-- Find a Lua script.
--
App.find_script = function(script_name)
	script_name = FileName.dirname(current_script) .. script_name

	if FileName.is_dir(script_name) then
		if string.sub(script_name, -1, -1) ~= "/" then
			script_name = script_name .. "/"
		end
		return script_name .. "main.lua"
	elseif FileName.is_file(script_name) then
		--
		-- Just execute that script.
		--
		return script_name
	else
		--
		-- Couldn't find it relative to the current script.
		--
		io.stderr:write("WARNING: could not find `" .. script_name .. "'\n")
		return nil
	end
end

--
-- Run a script.  Expects the full filename (will not search.)
-- Displays a nice dialog box if the script contained errors.
--
App.run_script = function(script_name, ...)
	local ok, result = App.run(script_name, unpack(arg))
	if ok then
		return result
	end
	App.log_warn("Error occurred while loading script `" ..
		      tostring(script_name) .. "': " .. tostring(result))
	if App.ui then
		App.ui:present{
		    id = "script_error",
		    name = "Error Loading Script",
		    short_desc = 
			"An internal Lua error occurred while " ..
			"trying to run the script " ..
			tostring(script_name) .. ":\n\n" ..
			tostring(result),
		    role = "alert",
		    actions = {
		        {
			    id = "ok",
			    name = "OK"
			}
		    }
		}
	end
	return nil
end

--
-- Run a sub-application (a script relative to the current script.)
--
App.descend = function(script_name, ...)
	return App.run_script(App.find_script(script_name), unpack(arg))
end

-----------------------------------------------------------------------
-------------- Locating and Loading Configuration Files ---------------
-----------------------------------------------------------------------

--
-- A metatable that overloads the '+' operator on tables, so that
-- 'a + b' results in an overriding merge of b's pairs into a.
--
local overload_mt = {
	__add = function(a, b)
		App.merge_tables(a, b, function(key, dest_val, src_val)
			return src_val
		end)
		return a
	end
}

--
-- Load a configuration file, given its name.
--
App.load_conf = function(filename)
	App.log("Loading configuration file '%s'...", filename)

	--
	-- Load and configuration file and compile it into a
	-- function.  Give the function the App.conf table
	-- as its global context.  Run the function, and all
	-- of the values it has set will go into App.conf.
	--
	local conf_func, err = loadfile(filename)
	if not conf_func then
		App.log_fatal("Could not load configuration file '%s': %s",
		    name, err)
	end
	setfenv(conf_func, App.conf)
	conf_func()

	--
	-- Make all the tables in the configuration extendable with
	-- the '+' operator, for future conf files that may be loaded.
	--
	local name, value
	for name, value in App.conf do
		if type(value) == "table" then
			setmetatable(value, overload_mt)
		end
	end
end


-----------------------------------------------------------------------
----------------------------- Logging ---------------------------------
-----------------------------------------------------------------------

--
-- Open the log.
-- XXX This doesn't really need to be public, does it?
--
App.open_log = function(filename, mode)
	if App.log_file then
		return
	end
	if not mode then
		mode = "w"
	end
	local fh, err = io.open(filename, mode)
	App.log_file = nil
	if fh then
		App.log_file = fh
	else
		error(err)
	end
end

--
-- Reopen the log with the same filename as it was opened with,
-- after (temporarily) closing it.
--
App.reopen_log = function()
	return App.open_log(App.conf.dir.tmp .. App.conf.log_filename, "a")
end

--
-- Close the log.
--
App.close_log = function()
	if App.log_file then
		App.log_file:close()
		App.log_file = nil
	end
end

--
-- Write a line to the log.
--
App.log = function(str, ...)
	local stamp = math.floor(os.time())

	local write_log = function(s)
		s = s .. "\n"
		io.stdout:write(s)
		io.stdout:flush()
		if App.log_file then
			App.log_file:write(s)
			App.log_file:flush()
		end
	end

	if stamp > last_log_time then
		last_log_time = stamp
		write_log("[" .. os.date() .. "]")
	end
	write_log(App.format(str, unpack(arg)))
end

App.format = function(str, ...)
	str = string.gsub(str, "%%%a", "%%s")

        local i
	for i = 1, table.getn(arg) do
		arg[i] = tostring(arg[i])
	end

	return string.format(str, unpack(arg))
end

--
-- Write a plain string to the log (no formatting.)
--
App.log_string = function(str)
	App.log("%s", str)
end

--
-- Write a warning to the log.
--
App.log_warn = function(str, ...)
	App.log("WARNING: " .. str, unpack(arg))
end

--
-- Write an error to the log.
--
App.log_fatal = function(str, ...)
	App.log(str, unpack(arg))
	error(App.format(str, unpack(arg)))
end

--
-- Display the log in the abstract user interface.
--
App.view_log = function()
	local contents = ""
	local fh

	App.close_log()

	fh = io.open(App.conf.dir.tmp .. App.conf.log_filename, "r")
	for line in fh:lines() do
		contents = contents .. line .. "\n"
	end
	fh:close()

	App.ui:present({
		id = "app_log",
		name = App.conf.app_name .. ": Log",
		short_desc = contents,
		role = "informative",
		minimum_width = "72",
		monospaced = "true",
		actions = {
			{ id = "ok", name = "OK" }
		}
	})
	
	App.open_log(App.conf.dir.tmp .. App.conf.log_filename, "a")
end

--
-- Install logging wrappers around every method in a class/object.
-- This is more useful for debugging purposes than for everyday use.
--
App.log_methods = function(obj_method_table)
	local k, v
	for k, v in pairs(obj_method_table) do
		local method_name, orig_fun = k, method[k]
		method[k] = function(...)
			App.log("ENTERING: %s", method_name)
			orig_fun(unpack(arg))
			App.log("EXITED: %s", method_name)
		end
	end
end

-----------------------------------------------------------------------
------------------------- Temporary Files -----------------------------
-----------------------------------------------------------------------

--
-- Delete all known temporary files.
--
App.clean_tmpfiles = function()
	local filename, unused

	for filename, unused in App.tmpfile do
		App.log("Deleting tmpfile: " .. filename)
		os.remove(App.conf.dir.tmp .. filename)
	end
end

--
-- Register that the given file (which resides in App.conf.dir.tmp)
-- is a temporary file, and may be deleted when upon exit.
--
App.register_tmpfile = function(filename)
	App.tmpfile[filename] = 1
end

--
-- Create and open a new temporary file (in App.conf.dir.tmp).
-- If the filename is omitted, one is chosen using the mkstemp
-- system call.  If the mode is omitted, updating ("w+") is
-- assumed.  The file object and the file name are returned.
--
App.open_tmpfile = function(filename, mode)
	local fh, err

	if not filename then
		fh, filename = POSIX.mkstemp(App.conf.dir.tmp ..
		    "Lua.XXXXXXXX")
		filename = FileName.basename(filename)
	else
		fh, err = io.open(App.conf.dir.tmp .. filename, mode or "w+")
		if err then
			return nil, err
		end
	end
	App.register_tmpfile(filename)
	return fh, filename
end

-----------------------------------------------------------------------
----------------------------- Utility ---------------------------------
-----------------------------------------------------------------------

--
-- Dump the contents of the given table to stdout,
-- primarily intended for debugging.
--
App.dump_table = function(tab, indent)
	local k, v

	if not indent then
		indent = ""
	end

	for k, v in tab do
		if type(v) == "table" then
			print(indent .. tostring(k) .. "=")
			App.dump_table(v, indent .. "\t")
		else
			print(indent .. tostring(k) .. "=" .. tostring(v))
		end
	end
end

--
-- Merge two tables by looking at each item from the second (src)
-- table and putting a value into the first (dest) table based on
-- the result of a provided callback function which receives the
-- key and bother values, and returns the resulting value.
--
-- An 'overriding' merge can be accomplished with:
--	function(key, dest_val, src_val)
--		return src_val
--	end
--
-- A 'non-overriding' merge can be accomplished with:
--	function(key, dest_val, src_val)
--		if dest_val == nil then
--			return src_val
--		else
--			return dest_val
--		end
--	end
--
App.merge_tables = function(dest, src, fun)
	local k, v

	for k, v in src do
		if type(v) == "table" then
			if not dest[k] then
				dest[k] = {}
			end
			if type(dest[k]) == "table" then
				App.merge_tables(dest[k], v, fun)
			end
		else
			dest[k] = fun(k, dest[k], v)
		end
	end
end

--
-- Expand strings which have ${} variables inside them, similar
-- to Perl or Bourne shell or Makefiles, but slightly more
-- restrained.  (Only registered variables can be replaced, not any
-- global Lua variable.)
--
App.expand = function(str, ...)
	local ltables = arg or {}
	local gtables = {App.conf.cmd_names, App.conf.dir}

	local result = string.gsub(str, "%$%{([%w_]+)%}", function(key)
		local i, tab, value

		if table.getn(ltables) > 0 then
			for i, tab in ipairs(ltables) do
				value = tab[key]
				if value then
					return value
				end
			end
		end

		if table.getn(gtables) > 0 then
			for i, tab in ipairs(gtables) do
				value = tab[key]
				if value then
					return value
				end
			end
		end

		App.log_warn("Could not expand `${%s}'", key)
		return "${" .. key .. "}"
	end)

	return result
end

--
-- Given a string in the form "foo.bar=baz", set the member "bar" of the
-- subtable "foo" of the App.conf object to "baz".
--
App.set_property = function(expr)
	local found, len, k, v, c, r, i, t

	t = App.conf
	r = {}
	found, len, k, v = string.find(expr, "^(.*)=(.*)$")

	-- quick and dirty type coercion.
	if v == "true" then
		v = true
	elseif v == "false" then
		v = false
	elseif string.find(v, "^%d+$") then
		v = tonumber(v)
	end

	for c in string.gfind(k, "[^%.]+") do
		table.insert(r, c)
	end
	for i, c in r do
		if i == table.getn(r) then
			t[c] = v
		else
			if not t[c] then
				t[c] = {}
			end
			if type(t[c]) == "table" then
				t = t[c]
			else
				App.log_warn("%s: not a table", tostring(c))
			end
		end
	end
end

--
-- Wait for a condition to come true.
-- Display a (cancellable) progress bar while we wait.
-- Returns two values: whether the condition eventually
-- did come true, and roughly how long it took (if it
-- timed out, this value will be greater than the timeout.)
--
App.wait_for = function(tab)
	local predicate = tab.predicate
	local timeout = tab.timeout or 30
	local frequency = tab.frequency or 2
	local title = tab.title or "Please wait..."
	local short_desc = tab.short_desc or title
	local pr
	local time_elapsed = 0
	local cancelled = false

	assert(type(predicate) == "function")

	if predicate() then
		return true
	end

	pr = App.ui:new_progress_bar{
	    title = title,
	    short_desc = short_desc
	}
	pr:start()
	
	while time_elapsed < timeout and not cancelled and not result do
		POSIX.nanosleep(frequency)
		time_elapsed = time_elapsed + frequency
		if predicate() then
			return true, time_elapsed
		end
		pr:set_amount((time_elapsed * 100) / timeout)
		cancelled = not pr:update()
	end

	pr:stop()

	return false, time_elapsed
end

--[[--------------]]--
--[[ App.UIBridge ]]--
--[[--------------]]--

App.UIBridge = {}
App.UIBridge.new = function(class, ...)
	local method = class.new(unpack(arg))

	--
	-- Handy dialogs.  (Perhaps a bit too handy?)
	--
	method.inform = function(ui, msg)
		return ui:present{
		    id = "inform",
		    name = "Information",
		    short_desc = msg,
		    role = "informative",
		    actions = {
			{
			    id = "ok",
			    name = "OK",
			    accelerator = "ESC"
			}
		    }
		}
	end
	
	method.confirm = function(ui, msg)
		local response = ui:present{
		    id = "confirm",
		    name = "Are you SURE?",
		    short_desc = msg,
		    role = "alert",
		    actions = {
			{
			    id = "ok",
			    name = "OK"
			},
			{
			    id = "cancel",
			    accelerator = "ESC",
			    name = "Cancel"
			}
		    }
		}
		return (response.action_id == "ok")
	end

	method.select_file = function(ui, tab)
		local title = tab.title or "Select File"
		local short_desc = tab.short_desc or title
		local long_desc = tab.long_desc or ""
		local cancel_desc = tab.cancel_desc or "Cancel"
		local cancel_pos = tab.cancel_pos or "bottom"
		local dir = assert(tab.dir,
		    "Call to select_file must specify a directory")
		local predicate = assert(tab.predicate,
		    "Call to select_file must specify a filename-filtering predicate")		
		local extra_actions = tab.extra_actions or {}
		local files, i, filename

		local form = {
		    id = "select_file",
		    name = title,
		    short_desc = short_desc,
		    long_desc = long_desc,
		    
		    role = "menu",

		    actions = extra_actions
		}
		local cancel_action = {
		    id = "cancel",
		    accelerator = "ESC",
		    name = cancel_desc
		}

		if cancel_pos == "top" then
			table.insert(form.actions, cancel_action)
		end

		files = POSIX.dir(dir) or {}
		table.sort(files)
		for i, filename in files do
			if predicate(filename) then
				table.insert(form.actions, {
				    id = filename,
				    name = filename
				})
			end
		end

		if cancel_pos == "bottom" then
			table.insert(form.actions, cancel_action)
		end

		return ui:present(form).action_id
	end

	return method
end

--[[-------------]]--
--[[ App.DummyUI ]]--
--[[-------------]]--

App.DummyUI = {}
App.DummyUI.new = function()
	local method = {}

	method.start = function(method)
		App.log("Dummy user interface started")
		return true
	end

	method.stop = function(method)
		App.log("Dummy user interface stopped")
		return true
	end

	method.present = function(method, tab)
		App.dump_table(tab)
		return {
		    action_id = tab.actions[1].id,
		    datasets = tab.datasets
		}
	end

	method.inform = function(method, msg)
		App.log("INFORM: %s", msg)
		return { action_id = "ok", datasets = {} }
	end
	
	method.confirm = function(method, msg)
		App.log("CONFIRM: %s", msg)
		return true
	end

	method.select = function(method, msg, map)
		local k, v
		App.log("SELECT: %s", msg)
		for k, v in map do
			return v
		end
	end

	method.select_file = function(method, tab)
		App.log("SELECT FILE: %s", tab.title or "Select File")
		return "cancel"
	end

	--
	-- Constructor within a constructor, here...
	--
	method.new_progress_bar = function(method, tab)
		local method = {}

		method.start = function(method)
			App.log("START PROGRESS BAR")
			return true
		end

		method.set_amount = function(method, new_amount)
			App.log("SET PROGRESS AMOUNT: %d", new_amount)
			return true
		end

		method.set_short_desc = function(method, new_short_desc)
			App.log("SET PROGRESS DESC: %d", new_short_desc)
			return true
		end

		method.update = function(method)
			App.log("PROGRESS UPDATE: %d", new_amount)
			return true
		end

		method.stop = function(method)
			App.log("STOP PROGRESS BAR")
			return true
		end

		return method
	end

	return method
end

--
-- Finally, return the container, as per the Compat-5.1 package system.
--
return App

-- END of lib/app.lua --
