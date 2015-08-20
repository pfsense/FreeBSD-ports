-- $Id: 550_upgrade_configuration.lua,v 1.5 2005/08/05 17:30:11 cpressey Exp $

--
-- Upgrade the target system's configuration files.
--
return {
    id = "upgrade_configuration",
    name = _("Upgrade Configuration"),
    req_state = { "storage", "sel_disk", "sel_part", "target" },
    effect = function(step)
	local cmds

	if App.ui:confirm(_(
	    "Would you like to perform automatic upgrades to your "	..
	    "system configuration files?\n\n" ..
	    "This is likely to work well if your configuration is not "	..
	    "highly customized.  However, if it is highly customized, "	..
	    "or if you have special concerns, you may want to skip "	..
	    "this upgrade and upgrade your configuration manually.\n\n"	..
	    "Upgrade configuration now?"
	)) then
		cmds = CmdChain.new()
		cmds:set_replacements{
		    base = FileName.remove_trailing_slash(
			     App.state.target:get_base()
			   )
		}

		cmds:add("${root}${MAKE} -f${root}etc/Makefile " ..
			"__MAKE_CONF=${root}${base}/etc/make.conf " ..
			"BINARY_UPGRADE=YES " ..
			"UPGRADE_SRCDIR=${root}etc/ " ..
			"DESTDIR=${root}${base} " ..
			"upgrade_etc")

		if cmds:execute() then
			App.ui:inform(
			    _("Target system's configuration was successfully upgraded!")
			)
		else
			App.ui:inform(
			    _("Target system's configuration was not successfully upgraded.")
			)
		end
	end

	return step:next()
    end
}
