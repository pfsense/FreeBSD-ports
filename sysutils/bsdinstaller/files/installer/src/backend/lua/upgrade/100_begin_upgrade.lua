-- $Id: 100_begin_upgrade.lua,v 1.4 2005/08/26 04:25:25 cpressey Exp $

return {
    id = "begin_upgrade",
    name = _("Begin Upgrade"),
    req_state = { "storage" },
    effect = function(step)
	return App.ui:present({
	    id = "begin_upgrade",
    	    name =  _("Begin Upgrade"),
	    short_desc = _(
		"This experimental application will upgrade an existing "	..
		"installation of %s (or a similar supported system) "		..
		"on this computer to %s version %s.\n\n"			..
		"If you have special requirements that are not addressed "	..
		"by this upgrade process, or if you have problems using it, "	..
		"you are welcome to upgrade %s manually. "			..
		"To do so select Exit to %s from the main menu, "		..
		"login as root, and follow the instructions given "		..
		"in the file /README .\n\n"					..
		"NOTE! This upgrade process is EXPERIMENTAL! "			..
		"As with any experimental process, YOU ARE "			..
		"STRONGLY ENCOURAGED TO BACK UP ANY IMPORTANT DATA ON THIS "	..
		"COMPUTER BEFORE PROCEEDING!",
		App.conf.product.name, App.conf.product.name,
		App.conf.product.version, App.conf.product.name,
		App.conf.media_name),

	    actions = {
		{
		    id = "proceed",
		    name = _("Upgrade to %s-%s",
		        App.conf.product.name,
			App.conf.product.version),
		    effect = function()
			return step:next()
		    end
		},
		{
		    id = "cancel",
		    name = _("Return to %s", step:get_upper_name()),
		    effect = function()
			return nil
		    end
		}
	    }
	}).result
    end
}
