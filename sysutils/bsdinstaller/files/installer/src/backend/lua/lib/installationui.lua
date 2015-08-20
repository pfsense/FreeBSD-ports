-- $Id: InstallationUI.lua,v 1.16 2005/10/12 00:43:51 cpressey Exp $

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

module "target_system_ui"

local App = require("app")
local FileName = require("filename")

local ui

--[[----------------]]--
--[[ TargetSystemUI ]]--
--[[----------------]]--

TargetSystemUI = {}

TargetSystemUI.set_ui = function(given_ui)
	ui = given_ui
end

TargetSystemUI.set_root_password = function(ts)
	local done = false
	local result
	local cmds
	local form = {
	    id = "root_passwd",
	    name = _("Set Root Password"),
	    short_desc = _(
		"Here you can set the super-user (root) password."
	    ),

	    fields = {
		{
		    id = "root_passwd_1",
		    name = _("Root Password"),
		    short_desc = _("Enter the root password you would like to use"),
		    obscured = "true"
		},
		{
		    id = "root_passwd_2",
		    name = _("Re-type Root Password"),
		    short_desc = _("Enter the same password again to confirm"),
		    obscured = "true"
		}
	    },

	    actions = {
		{
		    id = "ok",
		    name = _("Accept and Set Password")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to Configure Menu")
		}
	    },

	    datasets = {
		{ root_passwd_1 = "", root_passwd_2 = "" }
	    }
	}

	while not done do
		result = ui:present(form)

		if result.action_id == "ok" then
			form.datasets = result.datasets

			--
			-- Fetch form field values.
			--

			local root_passwd_1 = result.datasets[1].root_passwd_1
			local root_passwd_2 = result.datasets[1].root_passwd_2

			-- XXX validate password for bad characters here XXX
			
			if root_passwd_1 == root_passwd_2 then
				--
				-- Passwords match, so set the root password.
				--
				cmds = CmdChain.new()
				ts:cmds_set_password(cmds,
				    "root", root_passwd_1)
				if cmds:execute() then
					ui:inform(
					    _("The root password has been changed.")
					)
					done = true
				else
					ui:inform(
					    _("An error occurred when " ..
					      "setting the root password.")
					)
					done = false
				end
			else
				--
				-- Passwords don't match - tell the user, let them try again.
				--
				ui:inform(
				    _("The passwords do not match.")
				)
				done = false
			end
		else
			-- Cancelled
			done = true
		end
	end
end

TargetSystemUI.add_user = function(ts)
	local done = false
	local result
	local cmds

	--
	-- Predicates which validate entered data.
	--
	local is_gecos_clean = function(gecos)
		local i, char

		i = 1
		while i <= string.len(gecos) do
			char = string.sub(gecos, i, i)
			if string.find(char, "%c") or		  -- no ctrl chars
			   string.byte(char) == 127 or		  -- no 'DEL' char
			   string.find(":!@", char, 1, true) then -- none of these
				return false
			end
			i = i + 1
		end

		return true
	end

	local is_name_clean = function(name)
		local i, char

		i = 1
		while i <= string.len(name) do
			char = string.sub(name, i, i)
			if string.find(char, "%c") or		-- no ctrl chars
			   string.byte(char) == 127 or		-- no 'DEL' char
			   string.byte(char) > 127 or		-- no 8-bit chars
								-- and none of these:
			   string.find(" ,\t:+&#%^()!@~*?<>=|\\/\"", char, 1, true) or
			   (char == "-" and i == 1) or		-- no '-' at start
								-- '$' only at end:
			   (char == "$" and i ~= string.len(name)) then
				return false
			end
			i = i + 1
		end

		return true
	end

	local is_filename_clean = function(filename)	-- XXX incomplete
		return true
	end

	local is_uid_clean = function(uid)		-- XXX incomplete
		return true
	end

	local is_grouplist_clean = function(grouplist)	-- XXX incomplete
		return true
	end

	--
	-- Description of the form to display.
	--
	local form = {
	    id = "add_user",
	    name = _("Add User"),
	    short_desc = _("Here you can add a user to an installed system.\n\n" ..
		"You can leave the Home Directory, User ID, and Login Group "	..
		"fields empty if you want these items to be automatically "	..
		"allocated by the system."),
	    fields = {
		{
		    id = "username",
		    name = _("Username"),
		    short_desc = _("Enter the username the user will log in as")
		},
		{
		    id = "gecos",
		    name = _("Real Name"),
		    short_desc = _("Enter the real name (or GECOS field) of this user")
		},
		{
		    id = "passwd_1",
		    name = _("Password"),
		    short_desc = _("Enter the user's password (will not be displayed)"),
		    obscured = "true"
		},
		{
		    id = "passwd_2",
		    name = _("Password (Again)"),
		    short_desc = _("Re-enter the user's password to confirm"),
		    obscured = "true"
		},
		{
		    id = "shell",
		    name = _("Shell"),
		    short_desc = _("Enter the full path to the user's shell program")
		},
		{
		    id = "home",
		    name = _("Home Directory"),
		    short_desc = _("Enter the full path to the user's home directory, or leave blank")
		},
		{
		    id = "uid",
		    name = _("User ID"),
		    short_desc = _("Enter this account's numeric user id, or leave blank")
		},
		{
		    id = "group",
		    name = _("Login Group"),
		    short_desc = _("Enter the primary group for this account, or leave blank")
		},
		{
		    id = "groups",
		    name = _("Other Group Memberships"),
		    short_desc = _(
			"Enter a comma-separated list of other groups "	..
			"that this user should belong to"
		    )
		}
	    },
	    actions = {
		{
		    id = "ok",
		    name = _("Accept and Add User")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("Return to Configure Menu")
		}
	    },
	    datasets = {
		{
		    username = "",
		    gecos = "",
		    passwd_1 = "",
		    passwd_2 = "",
		    shell = "/bin/tcsh",
		    home = "",
		    uid = "",
		    group = "",
		    groups = ""
		}
	    }
	}

	--
	-- Main loop which repeatedly displays the form until either
	-- the user cancels or everything validates.
	--
	while not done do
		result = ui:present(form)
		if result.action_id == "ok" then
			form.datasets = result.datasets

			--
			-- Fetch form field values.
			--
			local username	= result.datasets[1].username
			local gecos	= result.datasets[1].gecos
			local passwd_1	= result.datasets[1].passwd_1
			local passwd_2	= result.datasets[1].passwd_2
			local shell	= result.datasets[1].shell
			local home	= result.datasets[1].home
			local uid	= result.datasets[1].uid
			local group	= result.datasets[1].group
			local groups	= result.datasets[1].groups
			local full_shell = App.conf.dir.root ..
			    ts:get_base() ..
			    FileName.remove_leading_slash(shell)

			--
			-- Valid field values.
			--

			if string.len(username) == 0 then
				ui:inform(_(
				    "You must enter a username."
				))
			elseif passwd_1 ~= passwd_2 then
				ui:inform(_(
				    "The passwords do not match."
				))
			elseif not is_name_clean(username) then
				ui:inform(_(
				    "The username contains illegal characters."
				))
			elseif not is_gecos_clean(gecos) then
				ui:inform(_(
				    "The text specified in the Real Name " ..
				    "field contains illegal characters."
				))
			elseif not is_name_clean(group) then
				ui:inform(_(
				    "The name of the login group contains " ..
				    "illegal characters."
				))
			elseif not is_filename_clean(home) then
				ui:inform(_(
				    "The name of the home directory contains " ..
				    "illegal characters."
				))
			elseif not is_uid_clean(uid) then
				ui:inform(_(
				    "The user ID (uid) contains " ..
				    "illegal characters."
				))
			elseif not is_grouplist_clean(groups) then
				ui:inform(_(
				    "The list of group memberships contains " ..
				    "illegal characters."
				))
			elseif not FileName.is_program(full_shell) and
			    shell ~= "/nonexistent" then
				ui:inform(_(
				    "The selected shell (%s) does not " ..
				    "exist on the system (%s).",
				    shell, full_shell
				))
			else
				local cmds = CmdChain.new()

				ts:cmds_add_user(cmds, {
				    username = username,
				    gecos = gecos,
				    shell = shell,
				    uid = uid,
				    group = group,
				    home = home,
				    groups = groups,
				    password = passwd_1
				})
				if cmds:execute() then
					ui:inform(_(
					    "User `%s' was added.",
					    username
					))
					done = true
				else
					ui:inform(_(
					    "User was not successfully added."
					))
				end
			end
		else
			-- Cancelled.
			done = true
		end
	end
end

TargetSystemUI.configure_console = function(tab)
	tab = tab or {}
	local form = {
	    id = "configure_console",
	    name = _("Configure Console"),
	    short_desc = _(
		"Your selected environment uses the following " ..
		"console settings, shown in parentheses. "	..
		"Select any that you wish to change."
	    ),
	    role = "menu"
	}

	local done = false
	local response
	while not done do
		form.actions = {
			{
			    id = "vidfont",
			    name = _("Change Video Font (%s)",
				App.state.vidfont or _("default")),
			    effect = function()
				TargetSystemUI.set_video_font(tab.ts)
				return false
			    end
			},
			{
			    id = "scrnmap",
			    name = _("Change Screenmap (%s)",
				App.state.scrnmap or _("default")),
			    effect = function()
				TargetSystemUI.set_screen_map(tab.ts)
				return false
			    end
			},
			{
			    id = "keymap",
			    name = _("Change Keymap (%s)",
				App.state.keymap or _("default")),
			    effect = function()
				TargetSystemUI.set_keyboard_map(tab.ts)
				return false
			    end
			},
			{
			    id = "ok",
			    name = tab.ok_desc or _("Accept these Settings"),
			    effect = function()
				return true
			    end
			}
		}
		if tab.allow_cancel then
			table.insert(form.actions, {
			    id = "cancel",
			    accelerator = "ESC",
			    name = tab.cancel_desc or _("Cancel"),
			    effect = function()
				return true
			    end
			})
		end

		response = ui:present(form)
		done = response.result
	end
	return response.action_id == "ok"
end

TargetSystemUI.set_keyboard_map = function(ts)
	local cmds, files, dir, filename, full_filename

	--
	-- Select a file.
	--
	dir = App.expand("${root}${base}usr/share/syscons/keymaps",
	    {
		base = ts:get_base()
	    }
	)

	filename = ui:select_file{
	    title = _("Select Keyboard Map"),
	    short_desc = _(
		"Select a keyboard map appropriate to your keyboard layout."
	    ),
	    cancel_desc = _("Return to Configure Console"),
	    dir = dir,
	    predicate = function(filename)
		return string.find(filename, "%.kbd$")
	    end
	}
	if filename == "cancel" then
		return false
	end
	filename = dir .. "/" .. filename

	cmds = CmdChain.new()
	cmds:add{
	    cmdline = "${root}${KBDCONTROL} -l ${filename} < /dev/ttyv0",
	    replacements = { filename = filename }
	}
	if cmds:execute() then
		--
		-- Add this to future rc.conf settings, and also
		-- note it in the App.state.
		--
		filename = FileName.remove_extension(FileName.basename(filename))
		App.state.rc_conf:set("keymap", filename)

		App.state.keymap = filename

		return true
	else
		ui:inform(_(
		    "Errors occurred; keyboard map was not successfully set."
		))
	end
end

TargetSystemUI.set_video_font = function(ts)
	local cmds, files, dir, filename, full_filename

	--
	-- Select a file.
	--
	dir = App.expand("${root}${base}usr/share/syscons/fonts",
	    {
		base = ts:get_base()
	    }
	)

	filename = ui:select_file{
	    title = _("Select Console Font"),
	    short_desc = _("Select a font appropriate to your video monitor and language."),
	    cancel_desc = _("Return to Configure Console"),
	    dir = dir,
	    predicate = function(filename)
		return string.find(filename, "%.fnt$")
	    end
	}
	if filename == "cancel" then
		return false
	end
	filename = dir .. "/" .. filename

	cmds = CmdChain.new()
	cmds:add{
	    cmdline = "${root}${VIDCONTROL} -f ${filename} < /dev/ttyv0",
	    replacements = { filename = filename }
	}
	if cmds:execute() then
		local found, len, w, h = string.find(filename, "(%d+)x(%d+)")
		if found then
			w = tonumber(w)
			h = tonumber(h)

			--
			-- Add this to future rc.conf settings, and also
			-- note it in the App.state.
			--
			filename = FileName.remove_extension(FileName.basename(filename))
			App.state.rc_conf:set(
			    App.expand("font${width}x${height}", {
				width = tostring(w),
				height = tostring(h)
			    }),
			    filename
			)
			App.state.vidfont = filename
		end

		return true
	else
		ui:inform(_(
		    "Errors occurred; video font was not successfully set."
		))
	end
end

TargetSystemUI.set_screen_map = function(ts)
	local cmds, files, dir, filename, full_filename

	--
	-- Select a file.
	--
	dir = App.expand("${root}${base}usr/share/syscons/scrnmaps",
	    {
		base = ts:get_base()
	    }
	)

	filename = ui:select_file{
	    title = _("Select Screen Map"),
	    short_desc = _(
		"Select a mapping for translating characters as they " ..
		"appear on your video console screen."
	    ),
	    cancel_desc = _("Return to Configure Console"),
	    dir = dir,
	    predicate = function(filename)
		return string.find(filename, "%.scm$")
	    end
	}
	if filename == "cancel" then
		return false
	end
	filename = dir .. "/" .. filename

	cmds = CmdChain.new()
	cmds:add{
	    cmdline = "${root}${VIDCONTROL} -l ${filename} < /dev/ttyv0",
	    replacements = { filename = filename }
	}
	if cmds:execute() then
		--
		-- Add this to future rc.conf settings, and also
		-- note it in the App.state.
		--
		filename = FileName.remove_extension(FileName.basename(filename))
		App.state.rc_conf:set("scrnmap", filename)
		App.state.scrnmap = filename

		return true
	else
		ui:inform(_(
		    "Errors occurred; screen map was not successfully set."
		))
	end
end

TargetSystemUI.ask_reboot = function(tab)
	local cancel_desc = tab.cancel_desc
	local response = App.ui:present{
	    id = "reboot",
	    name = _("Reboot"),
	    short_desc = _("This machine is about to be shut down. " ..
	        "After the machine has reached its shutdown state, " ..
	        "you may remove the CD from the CD-ROM drive tray " ..
	        "and press Enter to reboot from the HDD."),
	    role = "confirm",
	    actions = {
	        {
		    id = "ok",
		    name = _("Reboot"),
		},
	        {
		    id = "cancel",
		    accelerator = "ESC",
		    name = cancel_desc
		}
	    }
	}
	return (response.action_id == "ok")
end

return TargetSystemUI
