-- $Id: 800_easy_install.lua,v 1.3 2005/04/12 13:28:31 den Exp $

return {
    id = "set_up_easy_install",
    name = _("Quick/Easy Install"),
    short_desc = _("Invoke Installer with minimal questions"),
    effect = function()
	App.descend("easy_install")
	return Menu.CONTINUE
    end
}

