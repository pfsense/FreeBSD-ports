-- $Id: 500_perform_upgrade.lua,v 1.12 2005/08/26 04:25:25 cpressey Exp $

--
-- Perform an upgrade by copying files from the root of the installation
-- medium to the target system.
--
return {
    id = "perform_upgrade",
    name = _("Perform Upgrade"),
    req_state = { "storage", "sel_disk", "sel_part", "target" },
    effect = function(step)
	local cmds

	cmds = CmdChain.new()
	cmds:set_replacements{
	    base = App.state.target:get_base()
	}

	--
	-- First, we must record which files on the installed system have
	-- the 'noschg' flag set.
	--
	App.register_tmpfile("schg.txt")
	cmds:add("${root}${FIND} ${root}${base} -flags -schg, >${tmp}schg.txt")

	--
	-- Next, we take away the schg flag from these files, so that we
	-- can overwrite them.
	--
	cmds:add("${root}${XARGS} -t ${root}${CHFLAGS} noschg <${tmp}schg.txt")

	--
	-- Add commands to copy sources (files and directories) now.
	--
	App.state.target:cmds_install_srcs(cmds, App.conf.upgrade_items)

	--
	-- Before we are done, we must use chflags to restore the flags
	-- of files which we 'noschg'ed so that we could upgrade them.
	--
	cmds:add("${root}${XARGS} -t ${root}${CHFLAGS} schg <${tmp}schg.txt")
	
	--
	-- Finally: confirm and do it.
	--
	if App.ui:confirm(_(
	    "WARNING!  ALL system files in ALL system directories "	..
	    "in primary partition #%d,\n\n%s\n\non the "		..
	    "disk\n\n%s\n\n will be FORCIBLY REPLACED!\n\n"		..
	    "Are you ABSOLUTELY SURE you wish to take this action? "	..
	    "This is your LAST CHANCE to cancel!",
	    App.state.sel_part:get_number(),
	    App.state.sel_part:get_desc(),
	    App.state.sel_disk:get_desc()
	)) then
		if cmds:execute() then
			App.ui:inform(_(
			    "Target system executables were "		..
			    "successfully upgraded!\n\n"		..
			    "Note that this does NOT include any "	..
			    "3rd-party software, such as 'ports' "	..
			    "or 'packages', that you may have "		..
			    "installed. You should re-install or "	..
			    "recompile these programs before using "	..
			    "them, as changes to the system "		..
			    "interfaces that they use may have "	..
			    "rendered them inoperable."
			))
			return step:next()
		else
			App.ui:inform(_(
			    "Target system executables were "		..
			    "not successfully upgraded."
			))
			return "unmount_target_system"
		end
	else
		App.ui:inform(_(
		    "Action cancelled.  "				..
		    "No changes were made to the target system."
		))
		return "unmount_target_system"
	end
    end
}
