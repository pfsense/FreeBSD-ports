-- $Id: 200_install.lua,v 1.12 2005/08/30 00:39:05 cpressey Exp $

--
-- Menu.Item scriptlet that generates the install menu item.
-- Note that, if we detect that we weren't started from the
-- installation media, we don't provide this menu item.
--

if not App.conf.booted_from_install_media then
	return nil, "not booted from install media"
end

return {
    id = "install_os",
    name = _("Install %s", App.conf.product.name),
    short_desc = _("Install %s on this computer system",
		   App.conf.product.name),
    effect = function()
	--
	-- Make a recording of the install session.
	-- XXX this might make more sense elsewhere, like as
	-- Flow.Steps during the install itself?
	--
	CmdChain.record_to(App.expand("${tmp}install-session.sh"))
	App.descend("install")
	CmdChain.stop_recording()

	return Menu.CONTINUE
    end
}
