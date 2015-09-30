-- $Id$

-- (C)2007 Scott Ullrich
-- All rights reserved.

--
-- Determine if the current system is running a 64-bit architecture
--

function is64bit()
	-- Identify the system
	local arch
	arch = App.conf.product.arch

	if arch == "amd64" then
		return true
	else
		return false
	end
end

--
-- Install custom kernel 
--

return {
    id = "install_kernel",
    name = _("Install Kernel"),
    req_state = { "storage" },
    effect = function(step)
	local datasets_list = {}
	local actions_list = {}

	local is_adi = (os.execute("/bin/kenv -q smbios.system.product | /usr/bin/egrep -q 'RCC-(VE|DFF)'") == 0)

	if is_adi then
		actions_list = {
			{
			    id = "Embedded",
			    name = _("Embedded kernel (no VGA console, keyboard")
			},
			{
			    id = "SMP",
			    name = _("Standard Kernel")
			}
		}
	else
		actions_list = {
			{
			    id = "SMP",
			    name = _("Standard Kernel")
			},
			{
			    id = "Embedded",
			    name = _("Embedded kernel (no VGA console, keyboard")
			}
		}
	end
	
	local response = App.ui:present({
	    id = "install_kernel",
	    name = _("Install Kernel"),
	    short_desc = _(
		"You may now wish to install a custom Kernel configuration. ",
		App.conf.product.name, App.conf.product.name),
	    long_desc = _(
	        "",
		App.conf.product.name
	    ),
	    special = "bsdinstaller_install_kernel",

	    actions = actions_list,
	    datasets = datasets_list,
	    multiple = "true",
	    extensible = "false"
	})

	if response.action_id == "SMP" then
		local cmds = CmdChain.new()
		cmds:add("env ASSUME_ALWAYS_YES=true pkg -c /mnt delete -f " .. App.conf.product.name .. "-default-config*")
		cmds:add("cp /pkgs/" .. App.conf.product.name .. "-default-config-[0-9]*txz /mnt")
		cmds:add("env ASSUME_ALWAYS_YES=true pkg -c /mnt add -f /" .. App.conf.product.name .. "-default-config*txz")
		cmds:add("rm -f /mnt/" .. App.conf.product.name .. "-default-config-[0-9]*txz")
		cmds:execute()
	end
	if response.action_id == "Embedded" then
		local cmds = CmdChain.new()
		cmds:add("env ASSUME_ALWAYS_YES=true pkg -c /mnt delete -f " .. App.conf.product.name .. "-default-config*")
		cmds:add("cp /pkgs/" .. App.conf.product.name .. "-default-config-serial-[0-9]*txz /mnt")
		cmds:add("env ASSUME_ALWAYS_YES=true pkg -c /mnt add -f /" .. App.conf.product.name .. "-default-config*txz")
		cmds:add("rm -f /mnt/" .. App.conf.product.name .. "-default-config-serial-[0-9]*txz")
		if is_adi then
			cmds:add("echo -S115200 -h >> /mnt/boot.config")
		else
			cmds:add("echo -S115200 -D >> /mnt/boot.config")
			cmds:add("echo 'boot_multicons=\"YES\"' >> /mnt/boot/loader.conf")
		end
		cmds:add("echo 'boot_serial=\"YES\"' >> /mnt/boot/loader.conf")
		if is_adi then
			cmds:add("echo 'console=\"comconsole\"' >> /mnt/boot/loader.conf")
		else
			cmds:add("echo 'console=\"comconsole,vidconsole\"' >> /mnt/boot/loader.conf")
		end
		cmds:add("echo 'comconsole_speed=\"115200\"' >> /mnt/boot/loader.conf")
		if is_adi then
			cmds:add("echo 'comconsole_port=\"0x2F8\"' >> /mnt/boot/loader.conf")
			cmds:add("echo 'hint.uart.0.flags=\"0x00\"' >> /mnt/boot/loader.conf")
			cmds:add("echo 'hint.uart.1.flags=\"0x10\"' >> /mnt/boot/loader.conf")
			cmds:add("echo 'kern.ipc.nmbclusters=\"1000000\"' >> /mnt/boot/loader.conf.local")
		end

		cmds:execute()
	end

	return step:next()

    end
}
