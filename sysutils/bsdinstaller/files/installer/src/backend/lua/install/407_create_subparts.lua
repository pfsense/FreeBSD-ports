-- $Id: 407_create_subparts.lua,v 1.3 2005/08/27 19:42:10 cpressey Exp $

return {
    id = "create_subparts",
    name = _("Create Subpartitions"),
    req_state = { "sel_disk", "sel_part" },
    interactive = false,
    effect = function(step)
        local cmds = CmdChain.new()

	App.state.sel_part:cmds_disklabel(cmds)
	App.state.sel_part:cmds_install_bootstrap(cmds)
	App.state.sel_part:cmds_initialize_filesystems(cmds)

	if not cmds:execute() then
		App.ui:inform(_(
		    "The subpartitions you have chosen were "	..
		    "not correctly created, and the "		..
		    "primary partition may now be in an "	..
		    "inconsistent state. We recommend "		..
		    "re-formatting it before proceeding."
		))
		return step:prev()
	end

	return step:next()
    end
}
