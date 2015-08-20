-- $Id: 800_finished.lua,v 1.16 2005/08/26 04:25:24 cpressey Exp $

--
-- Display available choices when the install is finished.
--

return {
    id = "finished",
    name = _("Finished"),
    effect = function(step)
	return App.ui:present({
	    id = "finished_install",
	    name = _("%s is now installed", App.conf.product.name),
	    short_desc = _("Congratulations, %s is now installed "	 ..
			    "on your hard drive! You may now do one "	 ..
			    "of three things: you can perform some "	 ..
			    "initial configuration of this system, you " ..
			    "can reboot to test out your new "		 ..
			    "installation, or you can go back to the "	 ..
			    "main menu and select other actions to "	 ..
			    "perform.",
			    App.conf.product.name),
	    actions = {
		{
		    id = "configure",
		    name = _("Configure"),
		    short_desc = _("Configure the system that was just installed"),
		    effect = function()
		        App.descend("../configure")
			return nil
		    end
		},
		{
		    id = "reboot",
		    name = _("Reboot"),
		    short_desc = _("Reboot this computer"),
		    effect = function()
			return step:next()
		    end
		},
		{
		    id = "cancel",
		    name = _("Return to %s", step:get_upper_name()),
		    short_desc = _("Return to %s", step:get_upper_name()),
		    accelerator = "ESC",
		    effect = function()
			return nil
		    end
		}
	    }
	}).result
    end
}
