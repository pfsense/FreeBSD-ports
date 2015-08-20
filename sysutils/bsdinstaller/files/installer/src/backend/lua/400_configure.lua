-- $Id: 400_configure.lua,v 1.7 2005/08/26 04:25:24 cpressey Exp $

return {
    id = "configure_installed_system",
    name = _("Configure an Installed System"),
    short_desc = _("Configure an existing %s installation",
		   App.conf.product.name),
    effect = function()
	--
	-- If there is currently a target system mounted,
	-- unmount it before starting.
	--
	if App.state.target ~= nil and App.state.target:is_mounted() then
		if not App.state.target:unmount() then
			App.ui:inform(
			    _("Warning: already-mounted target system could " ..
			      "not be correctly unmounted first.")
			)
			return step:prev()
		end
	end

	App.descend("configure")
	return Menu.CONTINUE
    end
}
