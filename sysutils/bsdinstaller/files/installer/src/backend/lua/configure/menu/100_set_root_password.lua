-- $Id: 100_set_root_password.lua,v 1.10 2005/08/04 22:00:40 cpressey Exp $

return {
    id = "set_root_password",
    name = _("Set Root Password"),
    effect = function()
	TargetSystemUI.set_root_password(App.state.target)
	return Menu.CONTINUE
    end
}
