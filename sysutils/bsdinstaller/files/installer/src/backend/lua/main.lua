-- main.lua
-- $Id: main.lua,v 1.69 2005/08/27 04:16:03 cpressey Exp $
-- Main program/menu for BSD Installer Lua backend.

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
-- Save the command-line arguments.
--
local arg = arg

--
-- Load the application framework.
--
App = require("app")

--
-- Check if gettext functionality is available to us.
-- If so, load it, but if not, stub it out.
--
if pcall(function() require("gettext") end) then
	GetText = require("gettext")
	GetText.set_package("dfuibe_lua")
	-- XXX use App.conf.dir.root here:
	GetText.set_locale_dir("/usr/local/share/locale")
	GetText.init()
else
	GetText = nil
	_ = string.format
end

--
-- Start the application.
--
App.start(arg)

--
-- Load modules and make them available globally (to all scriptlets.)
--
FileName = require("filename")
Pty = require("pty")

Bitwise = require("bitwise")
CmdChain = require("cmdchain")
ConfigVars = require("configvars")
Storage = require("storage")
Network = require("network")
NetworkUI = require("network_ui")
Flow = require("flow")
Menu = require("menu")
StorageUI = require("storage_ui")
TargetSystem = require("target_system")
TargetSystemUI = require("target_system_ui")
Package = require("package")
PackageUI = require("package_ui")
Socket = require("socket")
socket = Socket -- Although this is not preferred, it is a common alias...

App.start_ui(App.UIBridge.new(require("dfui"), {
	transport = App.conf.dfui_transport or "tcp",
	rendezvous = App.conf.dfui_rendezvous or "9999",
	log = App.log
    }))

-----------------------------------------------------------------------------
--
-- Set up the initial App.state, which is global, and shared by everything.
--
-- App.state. | Type           | Description
-- -----------+----------------+---------------------------------------------
-- storage    | Storage.System | Represents the storage potentials (disks,
--            |                | &c) of the entire running computer system.
-- sel_disk   | Storage.Disk   | The disk under examination (to install onto
--            |                | or to configure)
-- sel_part   | Storage.       | The partition under examination (to install
--            |   Partition    | onto, or to configure)
-- source     | TargetSystem   | The currently running system; this is where
--            |                | stuff will be installed *from*.
-- target     | TargetSystem   | The system (as in, set of disks) which will
--            |                | be installed onto, or which is being cfg'd
-- net_if     | Network.       | Represents the available network interfaces 
--            |   Interfaces   | of the entire running computer system.
-- all_pkgs   | Package.Set    | The set of all packages that the user
--            |                | has access to for installing.
-- sel_pkgs   | Package.Set    | The set of packages that the user has
--            |                | selected to be installed onto the system.
-- rc_conf    | ConfigVars     | Settings that will be written to rc.conf
--            |                | when the install or configure is finished.
-- resolv_conf| ConfigVars     | Similar to rc_conf, but for DNS settings.
-- extra_fs   | table          | A list of extra filesystem descriptions,
--            |                | to be written to the new /etc/fstab.
-- do_exit    | boolean        | If set, exit after returning to main menu.
-- do_reboot  | boolean        | If set, start the reboot sequence after
--            |                | returning to the main menu.
-- lang_id    | string         | Identifier of the language the user has
--            |                | selected to work in, or nil for default.
-- vidfont    | string         | \
-- scrnmap    | string         | - Console settings.
-- keymap     | string         | /
--
-----------------------------------------------------------------------------

-- Create a representation of the storage devices and probe them.
App.state.storage = Storage.System.new()
App.state.storage:survey()

-- Point to the source system.
App.state.source = TargetSystem.use_current()
TargetSystemUI.set_ui(App.ui)

-- Create a representation of the network interfaces and probe them.
App.state.net_if = Network.Interfaces.new()
App.state.net_if:probe()

-- Enumerate available packages from the install media.
App.state.all_pkgs = Package.Set.new()
App.state.all_pkgs:enumerate_installed_on(App.state.source)

-- Set up the initial configuration variables
App.state.rc_conf = ConfigVars.new()
App.state.resolv_conf = ConfigVars.new()

--
-- First let the user configure the important user-interface aspects
-- of their system (language, keyboard/screenmap if on console,
-- internet connection for logging to remote machine over net, etc.)
--
-- These are termed "pre-install tasks" even though that is a slight
-- misnomer (and an unfortunate acronym):
--
App.descend("pit")

if not App.state.do_reboot and not App.state.do_exit then
	--
	-- Show the Main Menu.
	--
	Menu.new{
	    id = "main",
	    name = _("Select Task"),
	    short_desc = _("Choose one of the following tasks to perform."),
	    continue_constraint = function(result)
		if App.state.do_reboot or App.state.do_exit then
			return Menu.DONE
		else
			return result
		end
	    end
	}:populate("."):loop()
end

--
-- If there is a target system mounted, unmount it before leaving.
--
if App.state.target ~= nil and App.state.target:is_mounted() then
	if not App.state.target:unmount() then
		App.ui:inform(
		    _("Warning: subpartitions were not correctly unmounted.")
		)
	end
end

App.stop()

if App.state.do_reboot then
	-- exit with reboot code
	os.exit(5)
else
	os.exit(0)
end
