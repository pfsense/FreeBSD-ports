-- $Id: CmdChain.lua,v 1.44 2005/09/13 19:25:29 cpressey Exp $

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

module "cmdchain"

local App = require("app")
local Pty = require("pty")
local SMTP = require("smtp")

--[[----------]]--
--[[ CmdChain ]]--
--[[----------]]--

-- Global "class" variable:
CmdChain = {}

-- Some 'symbolic constants':
CmdChain.LOG_SILENT		= {}
CmdChain.LOG_QUIET		= {}
CmdChain.LOG_VERBOSE		= {}

CmdChain.FAILURE_IGNORE		= {}
CmdChain.FAILURE_WARN		= {}
CmdChain.FAILURE_ABORT		= {}

CmdChain.RESULT_NEVER_EXECUTED	= {}
CmdChain.RESULT_POPEN_ERROR	= {}
CmdChain.RESULT_SELECT_ERROR	= {}
CmdChain.RESULT_CANCELLED	= {}
CmdChain.RESULT_SKIPPED		= {}

-- Create a new command chain object instance.
CmdChain.new = function(...)
	local method = {}	-- instance/method object
	local list = {}		-- list of commands
	local capture = {}	-- dict of captured outputs
	local replacements = {}	-- App.expand replacements

	--
	-- Private functions.
	--

	--
	-- Fix up a command descriptor.  If it is just a string, turn
	-- it into a table; fill out any missing default values in the
	-- table; and expand the string as appropriate.
	--
	local fix_cmd = function(cmd)
		if type(cmd) == "string" then
			cmd = { cmdline = cmd }
		end
		assert(type(cmd) == "table")

		if cmd.cmdline == nil then
			cmd.cmdline = ""
		end
		assert(type(cmd.cmdline) == "string")

		if cmd.log_mode == nil then
			cmd.log_mode = CmdChain.LOG_VERBOSE
		end
		assert(cmd.log_mode == CmdChain.LOG_SILENT or
		       cmd.log_mode == CmdChain.LOG_QUIET or
		       cmd.log_mode == CmdChain.LOG_VERBOSE)

		if cmd.failure_mode == nil then
			cmd.failure_mode = CmdChain.FAILURE_ABORT
		end
		assert(cmd.failure_mode == CmdChain.FAILURE_IGNORE or
		       cmd.failure_mode == CmdChain.FAILURE_WARN or
		       cmd.failure_mode == CmdChain.FAILURE_ABORT)

		if cmd.replacements == nil then
			cmd.replacements = {}
		end
		assert(type(cmd.replacements) == "table")

		cmd.cmdline = App.expand(cmd.cmdline,
		    cmd.replacements, replacements)

		cmd.display_cmdline = cmd.cmdline

		if cmd.on_executed ~= nil then
			assert(type(cmd.on_executed) == "function")
		end

		-- cmd.desc, cmd.tag, cmd.sensitive, cmd.on_executed,
		-- and cmd.input are left as nil if not specified.

		return cmd
	end

	--
	-- Open a stream to a command to be executed, read from it,
	-- and update the progress bar as data comes and (and/or as the
	-- read from the stream times out.)
	--
	-- Returns:
	--   - the return value of the command on the other end of the stream;
	--   - a boolean indicating whether it was cancelled by the user; and
	--   - the output of the command, if requested (cmd.capture.)
	--
	local stream_loop = function(pr, cmd)
		local done = false
		local cancelled = false
		local pty
		local output = {}
		local cmdline

		local escape = function(pat)
			return string.gsub(pat, "([^%w])", "%%%1")
		end

		cmdline = "(" .. cmd.cmdline .. ") 2>&1"
		if not cmd.input then
			cmdline = cmdline .. " </dev/null"
		end
		pty = Pty.open(cmdline)
		if not pty then
			App.log("! could not open pty to: " .. cmd.cmdline)
			return CmdChain.RESULT_POPEN_ERROR, false, output
		end
		if cmd.input then
			pty:write(cmd.input)
			pty:flush()
		end

		while not done do
			if cancelled then break end
			line, err = pty:readline(1000)

			if line then
				if cmd.sensitive ~= nil then
					assert(type(cmd.sensitive) == "string")
					line = string.gsub(
					    line, escape(cmd.sensitive), "***not*shown***"
					)
				end
				cancelled = not pr:update()
				if cmd.log_mode == CmdChain.LOG_VERBOSE then
					App.log("| " .. line)
				elseif cmd.log_mode == CmdChain.LOG_QUIET then
					io.stderr:write("| " .. line .. "\n")
				else -- cmd.log_mode == CmdChain.LOG_SILENT
					-- do nothing
				end
				if cmd.capture then
					table.insert(output, line)
				end
			else
				if err == Pty.TIMEOUT then
					cancelled = not pr:update()
				elseif err == Pty.EOF then
					break
				else
					App.log("! pty:read() failed, err=%d", err)
					pty:close()
					return CmdChain.RESULT_SELECT_ERROR,
					    false, output;
				end
			end
		end
	
		if cancelled then
			pty:signal(Pty.SIGTERM)
		end

		return pty:close(), cancelled, output
	end

	--
	-- XXX this is not very good abstraction.
	--
	local get_log_contents = function()
		local contents = ""
		local fh

		App.close_log()

		fh = io.open(App.conf.dir.tmp .. App.conf.log_filename, "r")
		for line in fh:lines() do
			contents = contents .. line .. "\n"
		end
		fh:close()

		App.reopen_log()
		return contents
	end

	--
	-- XXX this isn't either.
	--
	local mail_log = function()
		local response = App.ui:present{
		    id = "mail_log",
		    name = "Mail Log",
		    short_desc = "Please enter the sender and destination " ..
				 "e-mail addresses, as well as the network " ..
				 "particulars, to send the log as an e-mail.",
		    fields = {
		        {
			    id = "to_addr",
			    name = "To (E-Mail Address)",
			},
		        {
			    id = "from_addr",
			    name = "From (E-Mail Address)",
			},
		        {
			    id = "server",
			    name = "SMTP Server",
			},
		        {
			    id = "port",
			    name = "SMTP Port",
			}
		    },
		    datasets = {
		        {
			    to_addr = "help@example.com",
			    from_addr = "me@example.com",
			    server = "smtp.example.com",
			    port = "25"
			}
		    },
		    actions = {
			{
			    id = "send_log",
			    name = "Send Log",
			    short_desc = "Send the log to the given address"
			},
			{
			    id = "cancel",
			    name = "Cancel"
			}
		    }
		}

		if response.action_id == "cancel" then
			return true
		end
		local dataset = response.datasets[1]

		local mailresult, err = SMTP.send{
		    from = dataset.from_addr,
		    rcpt = { dataset.to_addr },
		    server = dataset.server,
		    port = tonumber(dataset.port),
		    source = SMTP.message{
			headers = {
			    To = dataset.to_addr,
			    From = dataset.from_addr,
			   Subject = "BSD Installer log"
			},
			body = get_log_contents()
		    }
		}

		if mailresult then
			App.ui:inform(
			    "Mail was successfully sent."
			)
		else
			App.ui:inform(
			    "Mail was not sent successfully:\n\n" .. err
			)
		end

		return mailresult, err
	end

	--
	-- Show a dialog when the process of executing commands is
	-- interrupted (either by an error, or a command failure.)
	--
	local interruption_dialog = function(cmd, cancelled, result)
		local done_interruption = false
		local done_command = false
		local msg
		
		if cancelled then
			msg = "was cancelled."
		else
			msg = "FAILED with a return code of " .. tostring(result) .. "."
		end
		
		while not done_interruption do
			App.ui:present({
			    id = "cancelled",
			    name = "Cancelled",
			    short_desc = "Execution of the command\n\n" ..
				cmd.display_cmdline .. "\n\n" .. msg,
			    actions = {
				{
				    id = "view_log",
				    name = "View Log",
				    short_desc = "View the command output that led up to this",
				    effect = function()
					App.view_log()
				    end
				},
				{
				    -- XXX only show if network is connected?
				    id = "mail_log",
				    name = "Mail Log",
				    short_desc = "Mail the failing command output to an e-mail address",
				    effect = function()
					mail_log()
				    end
				},
				{
				    id = "retry",
				    name = "Retry",
				    short_desc = "Try executing this command again",
				    effect = function()
					done_interruption = true
				    end
				},
				{
				    id = "cancel",
				    name = "Cancel",
				    short_desc = "Abort this sequence of commands",
				    effect = function()
					result = CmdChain.RESULT_CANCELLED
					done_interruption = true
					done_command = true
				    end
				},
				{
				    id = "skip",
				    name = "Skip",
				    short_desc = "Skip this particular command and resume with the next one",
				    effect = function()
					result = CmdChain.RESULT_SKIPPED
					done_interruption = true
					done_command = true
				    end
				}
			    }
			})
		end
	
		return done_command, result
	end

	--
	-- Execute a single command.
	-- Return values are:
	--  - a COMMAND_RESULT_* constant, or a value from 0 to 255
	--    to indicate the exit code from the utility; and
	--  - the output of the command, if requested (cmd.capture.)
	--
	local command_execute = function(pr, cmd)
		local filename
		local cancelled = false
		local done_command = false
		local result, output = CmdChain.RESULT_NEVER_EXECUTED, ""
	
		if cmd.desc then
			pr:set_short_desc(cmd.desc)
		else
			pr:set_short_desc(cmd.display_cmdline)
		end
		cancelled = not pr:update()
	
		if App.conf.confirm_execution then
			done_command = not App.ui:confirm(
			    "About to execute:\n\n" .. cmd.display_cmdline ..
			    "\n\nIs this acceptable?"
			)
		end
	
		while not done_command do
			output = nil
			if cmd.log_mode ~= CmdChain.LOG_SILENT then
				App.log(",-<<< Executing `" .. cmd.display_cmdline .. "'")
			end
			if App.conf.fake_execution then
				if cmd.log_mode ~= CmdChain.LOG_SILENT then
					App.log("| (not actually executed)")
				end
				result = 0
			else
				result, cancelled, output = stream_loop(pr, cmd)
			end
			if cmd.log_mode ~= CmdChain.LOG_SILENT then
				App.log("`->>> Exit status: " .. tostring(result))
			end
	
			if cancelled then
				pr:stop()
				done_command, result = interruption_dialog(cmd, cancelled, result)
				pr:start()
			elseif cmd.failure_mode == CmdChain.FAILURE_IGNORE then
				result = 0
				done_command = true
			elseif (result ~= 0 and cmd.failure_mode ~= CmdChain.FAILURE_WARN) then
				pr:stop()
				done_command, result = interruption_dialog(cmd, cancelled, result)
				pr:start()
			else
				done_command = true
			end
		end
		if cmd.on_executed ~= nil then
			cmd.on_executed(cmd, result, output)
		end
	
		return result, output
	end

	--
	-- Methods.
	--

	--
	-- Set the global replacements for this command chain.
	-- These will be applied to each command that is
	-- subsequently added (although local replacements will
	-- be applied first.)
	--
	method.set_replacements = function(self, new_replacements)
		App.merge_tables(replacements, new_replacements,
		    function(key, dest_val, src_val)
			return src_val
		    end)
	end

	--
	-- Expand a string as it would be if it were executed.
	-- While not a generally elegant or recommended thing to do,
	-- this can sometimes be useful.
	--
	method.expand = function(self, str)
		return App.expand(str, replacements)
	end

	--
	-- Get captured output by its id.
	--
	method.get_output = function(self, cap_id)
		return capture[cap_id]
	end

	--
	-- Add one or more commands to this command chain.
	--
	method.add = function(self, ...)
		local cmd_no, cmd

		if table.getn(arg) == 0 then
			return
		end

		for cmd_no, cmd in ipairs(arg) do
			table.insert(list, fix_cmd(cmd))
		end
	end

	--
	-- Execute a series of external utility programs.
	-- Returns 1 if everything executed OK, 0 if one of the
	-- critical commands failed or if the user cancelled.
	--
	method.execute = function(self)
		local pr
		local cmd
		local i, n, result = 0, 0, 0
		local return_val = true
		local output

		n = table.getn(list)

		pr = App.ui:new_progress_bar{
		    title = "Executing Commands"
		}

		pr:start()

		for i, cmd in ipairs(list) do
			result, output = command_execute(pr, cmd)
			if result == CmdChain.RESULT_CANCELLED then
				return_val = false
				break
			end
			if type(result) == "number" and result > 0 and result < 256 then
				return_val = false
				if cmd.failure_mode == CmdChain.FAILURE_ABORT then
					break
				end
			end
			if cmd.capture then
				capture[cmd.capture] = output
			end
			pr:set_amount((i * 100) / n)
		end
	
		pr:stop()

		if return_val and CmdChain.record_file then
			self:record(CmdChain.record_file)
		end

		return return_val
	end

	--
	-- Show the commands that have been added to this
	-- command chain to the user in a dialog box.
	--
	method.preview = function(self)
		local contents = ""
		local i, cmd

		for i, cmd in ipairs(list) do
			contents = contents .. cmd.cmdline .. "\n"
		end

		App.ui:present({
			id = "cmd_preview",
			name = "Command Preview",
			short_desc = contents,
			role = "informative",
			minimum_width = "72",
			monospaced = "true",
			actions = {
				{ id = "ok", name = "OK" }
			}
		})
	end

	--
	-- Record these commands in a shell script file.
	--
	method.record = function(self, file)
		local contents = ""
		local i, cmd

		local gen_rand_string = function(len)
			local n = 1
			local s = ""
			local A = string.byte("A")
			assert(len >= 0)

			while n <= len do
				s = s .. string.char(math.random(A, A + 25))
				n = n + 1
			end

			return s
		end

		local get_marker_for = function(text)
			local s

			s = gen_rand_string(5)
			while string.find(text, s, 1, true) do
				s = gen_rand_string(5)
			end
			return s
		end

		for i, cmd in ipairs(list) do
			--
			-- Write lines apropos to the cmdline being
			-- executed, taking in account the failure mode.
			--
			if cmd.failure_mode == CmdChain.FAILURE_IGNORE then
				file:write(cmd.cmdline)
			elseif cmd.failure_mode == CmdChain.FAILURE_WARN then
				file:write("((" .. cmd.cmdline .. ") || " ..
				   "echo \"WARNING: " .. cmd.cmdline ..
				   " failed with exit code $?\")")
			elseif cmd.failure_mode == CmdChain.FAILURE_ABORT then
				file:write("(" .. cmd.cmdline .. ")")
			end

			--
			-- If the command has input, include that as a heredoc.
			--
			if cmd.input then
				local marker = get_marker_for(cmd.input)
				file:write(" <<" .. marker .. "\n" .. cmd.input .. marker)
			end

			if cmd.failure_mode == CmdChain.FAILURE_WARN or
			   cmd.failure_mode == CmdChain.FAILURE_ABORT then
				file:write(" && \\")
			end

			--
			-- Write the description as a comment following.
			--
			if cmd.desc then
				file:write("   # " .. cmd.desc)
			end

			file:write("\n")
		end
	end

	--
	-- ``Constructor'' - initialize our instance data.
	--

	if table.getn(arg) > 0 then
		method:add(unpack(arg))
	end

	return method
end

--
-- Static method: begin recording all successfully executed commands.
--
CmdChain.record_to = function(filename)
	CmdChain.record_file = io.open(filename, "w")
	CmdChain.record_file:write(App.expand([[
#${root}${SH} -x

# Shell script for installing ${product_name}-${product_version}/${os_name}-${os_version}
# Automatically generated by BSD Installer on ${date}

]],	{
	    product_name = App.conf.product.name,
	    product_version = App.conf.product.version,
	    os_name = App.conf.os.name,
	    os_version = App.conf.os.version,
	    date = os.date()
	}))
end

--
-- Static method: stop recording commands
--
CmdChain.stop_recording = function()
	CmdChain.record_file:close()
	CmdChain.record_file = nil
end

return CmdChain
