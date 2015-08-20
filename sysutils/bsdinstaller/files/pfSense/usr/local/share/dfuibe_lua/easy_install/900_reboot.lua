-- $Id: 900_reboot.lua,v 1.15 2005/08/13 18:46:09 cpressey Exp $

--
-- Reboot the system, so that the user can test booting from
-- their newly-installed system.
--

return {
    id = "reboot",
    name = _("Reboot"),
    effect = function(step)
	App.state.do_reboot = TargetSystemUI.ask_reboot{
	    cancel_desc = _("Return to %s", step:get_upper_name())
	}
	return nil
    end
}
