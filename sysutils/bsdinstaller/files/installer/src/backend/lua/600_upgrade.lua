-- $Id: 600_upgrade.lua,v 1.2 2005/04/09 19:04:20 cpressey Exp $

return {
    id = "upgrade_installed_system",
    name = _("Upgrade an Installed System"),
    short_desc = _("Upgrade a system with to the newest available version"),
    effect = function()
	App.descend("upgrade")
	return Menu.CONTINUE
    end
}
