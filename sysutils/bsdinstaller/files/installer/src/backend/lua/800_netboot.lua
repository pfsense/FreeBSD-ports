-- $Id: 800_netboot.lua,v 1.3 2005/04/12 13:28:31 den Exp $

return {
    id = "set_up_netboot",
    name = _("Set Up NetBoot Server"),
    short_desc = _("Make this computer a boot server " ..
		   "for other machines on the network"),
    effect = function()
	App.descend("netboot")
	return Menu.CONTINUE
    end
}
