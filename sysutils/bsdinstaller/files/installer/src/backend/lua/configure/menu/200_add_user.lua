-- $Id: 200_add_user.lua,v 1.12 2005/08/04 22:00:40 cpressey Exp $

return {
    id = "add_user",
    name = _("Add User"),
    effect = function()
	TargetSystemUI.add_user(App.state.target)
	return Menu.CONTINUE
    end
}
