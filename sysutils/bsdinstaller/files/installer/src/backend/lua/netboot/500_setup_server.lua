-- $Id: 500_setup_server.lua,v 1.11 2005/09/02 02:39:32 cpressey Exp $

--
-- Setup a remote boot installation environment where a machine
-- can boot via DHCP/TFTP/NFS and have a running environment
-- where the installer can setup the machine.
--

return {
    id = "setup_server",
    name = _("Set Up NetBoot Server"),
    req_state = { "net_if" },
    effect = function(step)

	local response = App.ui:present{
	    name = _("Enable NetBoot Installation Services?"),
	    short_desc =
	        _("NetBoot Installation Services allow this machine to become "	..
                  "an Installation Server that will allow other machines "	..
		  "on this network to boot as PXE clients, and will start the "	..
		  "Installation Environment on them.\n\n"			..
		  "Would you like to provision this machine to serve up the "	..
		  "Installation Environment to other machines?"),
	    actions = {
		{
		    id = "ok",
		    name = _("Enable NetBoot Installation Services")
		},
		{
		    id = "cancel",
		    accelerator = "ESC",
		    name = _("No thanks")
		}
	    }
	}

	if response.action_id == "ok" then
		local ni

		if App.state.net_if:ip_addr_count() == 0 then
			App.ui:inform(
			    _("No network interfaces on this machine "	..
			      "have been configured yet.  Please "	..
			      "select an interface to configure.")
			)
			ni = NetworkUI.select_interface(App.state.net_if)
			if not ni then
				App.log("select_interface was cancelled")
				return nil
			end
			if not NetworkUI.configure_interface(ni) then
				App.log("configure_interface failed")
				return nil
			end
		end

		--
		-- If the user was forced to configure an interface,
		-- assume that it is the one they'll be using,
		-- (there are, after all, no other ones configured,)
		-- otherwise, ask which one they want to use.
		--
		if not ni then
			ni = NetworkUI.select_interface(App.state.net_if, {
			    filter = function(ni)
				return ni:is_up()
			    end
			})
		end
		if not ni then
			App.log("select_interface was cancelled")
			return nil
		end

		local cmds = CmdChain.new()
		ni:cmds_start_netboot_server(cmds)
		if cmds:execute() then
			App.ui:inform(
			    _("NetBoot installation services are now started.")
			)
		else
			App.ui:inform(
			    _("A failure occured while provisioning "	..
			      "the NetBoot environment.  Please "	..
			      "consult the log file for details.")
			)
		end
	end

	return step:next()
    end
}
