-- $Id: 050_welcome.lua,v 1.10 2005/08/26 04:25:25 cpressey Exp $

--
-- Confirmation message
--

return {
    id = "centipede_confirm_basic",
    name = _("Confirmation Message"),
    effect = function(step)

		if App.ui:confirm(_(
			"Easy Install will automatically install without asking any questions. \n\n" ..
			"WARNING: This will erase all contents in your first hard disk! "	..
			"This action is irreversible. Do you really want to continue?\n\n"	..
			"If you wish to have more control on your setup, "			..
			"choose Custom Installation from the Main Menu."
		)) then
			return step:next()
		else
			return step:prev()
		end

    end
}
