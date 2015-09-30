
--
--  pfSense after installation routines
--
--  This file cleans up after a normal install.
--

return {
    id = "pfsense_after_install",
    name = _("pfSense after installation routines"),
    effect = function(step)
	local cmds = CmdChain.new()

	-- Label partitions
	cmds:add("/usr/bin/env DESTDIR=/mnt /bin/sh /usr/local/sbin/ufslabels.sh commit")
	cmds:add("/bin/sh /usr/local/bin/after_installation_routines.sh")

	cmds:execute()
	
	return step:next()

    end
}
