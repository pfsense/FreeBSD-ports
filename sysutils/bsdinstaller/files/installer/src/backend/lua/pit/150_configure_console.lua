-- $Id: 150_configure_console.lua,v 1.4 2005/08/04 22:00:40 cpressey Exp $

return {
    id = "configure_console",
    name = _("Configure Console"),
    effect = function(step)
	TargetSystemUI.configure_console{
	    ts = App.state.source,
	    allow_cancel = false
	}
	return step:next()
    end
}
