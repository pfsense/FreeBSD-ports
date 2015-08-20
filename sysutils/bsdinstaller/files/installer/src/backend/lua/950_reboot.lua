-- $Id: 950_reboot.lua,v 1.2 2005/08/13 18:46:09 cpressey Exp $

return {
    id = "reboot",
    name = _("Reboot"),
    short_desc = _("Reboot this computer"),
    effect = function()
	if TargetSystemUI.ask_reboot{
	    cancel_desc = _("Return to Select Task") -- XXX this_menu_name
	} then
		App.state.do_reboot = true
		return nil
	else
		return Menu.CONTINUE
	end
    end
}
