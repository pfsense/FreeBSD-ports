-- $Id: 100_welcome.lua,v 1.14 2005/08/26 04:25:24 cpressey Exp $

--
-- Show welcome information.
--

return {
    id = "welcome",
    name = _("Welcome"),
    effect = function(step)
	return App.ui:present({
	    id = "begin_install",
    	    name =  _("Begin Installation"),
	    short_desc = _(
		"This experimental application will install %s"			..
		" on one of the hard disk drives attached to this computer. "	..
		"It has been designed to make it easy to install "		..
		"%s in the typical case. "					..
		"If you have special requirements that are not addressed "	..
		"by this installer, or if you have problems using it, you "	..
		"are welcome to install %s manually. "				..
		"To do so select Exit to Live CD, login as root, and follow "	..
		"the instructions given in the file /README ."			..
		"\n\n"								..
		"NOTE! As with any installation process, YOU ARE "		..
		"STRONGLY ENCOURAGED TO BACK UP ANY IMPORTANT DATA ON THIS "	..
		"COMPUTER BEFORE PROCEEDING!",
		App.conf.product.name, App.conf.product.name, App.conf.product.name),
	    long_desc = _(
		"Some situations in which you might not wish to use this "	..
		"installer are:\n\n"						..
		"- you want to install %s onto a "				..
		"logical/extended partition;\n"					..
		"- you want to install %s "					..
		"onto a ``dangerously dedicated'' disk; or\n"			..
		"- you want full and utter control over the install process.",
		App.conf.product.name, App.conf.product.name),

	    actions = {
		{
		    id = "proceed",
		    name = _("Install %s", App.conf.product.name),
		    effect = function()
			return step:next()
		    end
		},
		{
		    id = "cancel",
		    name = _("Return to %s", step:get_prev_name()),
		    accelerator = "ESC",
		    effect = function()
			return nil
		    end
		}
	    }
	}).result
    end
}
