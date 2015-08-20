-- $Id: 800_configure_network.lua,v 1.17 2005/08/28 23:36:49 cpressey Exp $

return {
    id = "configure_network",
    name = _("Configure your Network"),
    req_state = { "net_if" },
    effect = function(step)
	local actions, ifname, ni, result

	if App.state.net_if:ip_addr_count() > 0 then
		--
		-- Looks like at least one interface is 'up';
		-- we assume that means it's already been
		-- configured.
		--
		return step:next()
	end

	if not App.ui:confirm(_(
	    "You have not yet configured your network settings. "	..
	    "Would you like to do so now? (Having an operational "	..
	    "network connection will enhance the ability of "		..
	    "subsequent tasks, such as installing.)"
	)) then
		return step:next()
	end

	ni = NetworkUI.select_interface(App.state.net_if)
	if not ni then
		return step:next()
	end

	NetworkUI.configure_interface(ni)

	return step:next()
    end
}
