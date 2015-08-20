-- $Id: 450_init_configuration.lua,v 1.1 2005/07/12 21:15:05 cpressey Exp $

--
-- Initialize the configuration variables.
--

return {
    id = "init_configuation",
    name = _("Initialize Configuration"),
    interactive = false,
    req_state = { "storage", "sel_disk", "sel_part" },
    effect = function(step)

	App.state.rc_conf = ConfigVars.new()

	return step:next()
    end
}
