-- $Id: 050_welcome.lua,v 1.10 2005/08/26 04:25:25 cpressey Exp $

--
-- "Welcome screen" for the BSD Installer.
--

return {
    id = "welcome",
    name = _("Welcome Screen"),
    effect = function(step)
	local result = App.ui:present({
	    id = "welcome",
    	    name =  _("Welcome to the BSD Installer!"),
	    short_desc = _(
	       "Welcome to the BSD Installer!\n\n"			..
	       "Before we begin, you will be asked a few questions "	..
	       "so that this installation environment can be set up "	..
	       "to suit your needs.\n\n"				..
	       "You will then be presented a menu of items from which "	..
	       "you may select to install a new system, or configure "	..
	       "or upgrade an existing system."
	    ),
	    actions = {
		{
		    id = "ok",
		    name = _("Proceed"),
		    short_desc = _("Set up the installation environment and continue")
		},
		{
		    id = "skip",
		    accelerator = "ESC",
		    --
		    -- XXX This should really be something more like this:
		    -- name = _("Skip to %s", step:get_upper_name())
		    -- ...but current technical limitations prevent this.
		    -- (The pre-install tasks are invoked explicitly from
		    -- the main script, and not from the Select Task menu.)
		    --
		    name = _("Skip to Select Task Menu"),
		    short_desc = _("Don't configure the environment; accept the default settings and continue")
		},
		{
		    id = "reboot",
		    name = _("Reboot"),
		    short_desc = _("Reboot this computer")
		},
		{
		    id = "exit",
		    name = _("Exit to %s", App.conf.media_name),
		    short_desc = _("Cancel this process and return to a command prompt")
		}
	    }
	}).action_id

	if result == "ok" then
		return step:next()
	elseif result == "skip" then
		return nil
	elseif result == "reboot" then
		if TargetSystemUI.ask_reboot{
		    cancel_desc = _("Return to %s", step:get_name())
		} then
			App.state.do_reboot = true
			return nil
		else
			return step
		end
		return nil
	elseif result == "exit" then
		App.state.do_exit = true
		return nil
	end
    end
}
