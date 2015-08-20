-- $Id: 400_load_kernel_modules.lua,v 1.5 2005/07/20 04:26:39 cpressey Exp $

return {
    id = "load_kernel_modules",
    name = _("Load Kernel Modules"),
    effect = function(step)
	--
	-- Select a file.
	--
	local dir = App.expand("${root}${MODULES_DIR}")

	local filename = App.ui:select_file{
	    title = _("Select Kernel Module to Load"),
	    short_desc = _(
	       "You may wish to load some kernel modules before "  ..
	       "using the system (for example, to enable network " ..
	       "interfaces which require drivers which are not "   ..
	       "included in the kernel by default.)  You may "     ..
	       "select a kernel module to load here."
	    ),
	    cancel_desc = _("Do not Load any Further Kernel Modules"),
	    cancel_pos = "top",
	    dir = dir,
	    predicate = function(filename)
		return string.find(filename, "%.ko$")
	    end
	}

	if filename == "cancel" then
		return step:next()
	else
		local cmds = CmdChain.new()

		cmds:add("${root}${KLDLOAD} ${root}modules/" .. filename)

		if cmds:execute() then
			App.ui:inform(_(
			    "Kernel module '%s' was successfully loaded.",
			    filename
			))
		else
			App.ui:inform(_(
			    "Warning: kernel module '%s' could not "	..
			    "successfully be loaded.",
			    filename
			))
		end

		return step
	end
    end
}
