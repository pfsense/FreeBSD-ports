-- $Id: 500_menu.lua,v 1.8 2005/07/08 21:24:09 cpressey Exp $

--
-- Display the configuration menu.
--

return {
    id = "configuration_menu",
    name = _("Configuration Menu"),
    req_state = { "target" },
    effect = function(step)
        App.descend("menu")
	return step:next()
    end
}
