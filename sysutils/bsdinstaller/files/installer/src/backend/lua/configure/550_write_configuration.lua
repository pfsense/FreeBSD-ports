-- $Id: 550_write_configuration.lua,v 1.4 2005/08/01 20:29:59 cpressey Exp $

--
-- Write the configuration variables out to the files they belong in.
-- XXX non-interactive for now, but could easily be turned into an
-- interactive "are you sure you wish to write these settings?" in the future.
--

return {
    id = "write_configuration",
    name = _("Write Configuration"),
    interactive = false,
    req_state = { "storage", "sel_disk", "sel_part", "target", "rc_conf" },
    effect = function(step)
	local cmds = CmdChain.new()

        App.state.rc_conf:cmds_write(cmds, App.expand("${root}${base}etc/rc.conf", {
	    base = App.state.target:get_base()
	}), "sh")

        App.state.resolv_conf:cmds_write(cmds, App.expand("${root}${base}etc/resolv.conf", {
	    base = App.state.target:get_base()
	}), "resolv")

	if not cmds:execute() then
		App.ui:inform(_(
		    "Couldn't write changes to configuration files for some reason."
		))
	end

	return step:next()
    end
}
