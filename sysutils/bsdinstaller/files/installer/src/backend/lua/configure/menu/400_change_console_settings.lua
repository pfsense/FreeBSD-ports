-- $Id: 400_change_console_settings.lua,v 1.8 2005/08/04 22:00:40 cpressey Exp $

return {
    id = "change_console_settings",
    name = _("Change Console Settings"),
    effect = function()
        TargetSystemUI.configure_console{
	    ts = App.state.target,
	    allow_cancel = true,
	    cancel_desc = _("Return to Configure Menu")
	}
	return Menu.CONTINUE
    end
}
