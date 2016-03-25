-- 960 register

local get_version = function()
        for line in io.lines("/etc/version") do
                pfversion = line
        end
	return pfversion 
end

local get_bios_serial = function()
 	
        os.execute ("/bin/kenv smbios.system.serial >>/tmp/serial")
	-- local tmpfile = io.open("/tmp/serial", "r")
        for line in io.lines("/tmp/serial") do
                serialnum = line
        end
	if serialnum == "123456789" then
		serialnum=""
	end
	return serialnum
end	

local get_wan_mac = function()
 	
	if  App.state.netgate_model == "APU" then
        	os.execute ("ifconfig re0 | awk '/ether/ {print $2}'| sed 's/://g' >>/tmp/wan_mac") 
	elseif App.state.netgate_model == "XG-1540" then
        	os.execute ("ifconfig ix0 | awk '/ether/ {print $2}'| sed 's/://g' >>/tmp/wan_mac") 
	else
        	os.execute ("ifconfig igb0 | awk '/ether/ {print $2}'| sed 's/://g' >>/tmp/wan_mac") 
	end
        for line in io.lines("/tmp/wan_mac") do
                mac = line
        end
	if mac == nil then
		mac="000000000000"
	end
	return mac
end	

local get_wlan_mac = function()
 	
        os.execute ("ifconfig ath0 | awk '/ether/ {print $2}'| sed 's/://g' >>/tmp/wlan_mac") 
        for line in io.lines("/tmp/wlan_mac") do
                wlan_mac = line
        end
	if wlan_mac == nil then
		wlan_mac="000000000000"
	end
	return wlan_mac
end	

return {   -- whole script
    id = "Serial_reg",
    name = _("Serial Registration Step"),
    -- req_state = {"storage"},
    effect = function(step)

local response = App.ui:present({
            id = "serial_register",
            name = _("Register Serial Number"),
            short_desc = _( "Enter the serial number " ),
            long_desc = _(
               "Help for Form",
                App.conf.product.name
            ), 
            
         fields = {
		   {
		       id = "product",
		       name = _("Model"),
		       short_desc = _("Selected Model")
		   },
                   {
                       id = "serial",
                       name = _("Serial"),
                       short_desc = _("Enter the system serial")
                   },
                   {
                       id = "order",
                       name = _("Order Number"),
                       short_desc = _("Enter the order number")
                   },
                   {
                       id = "print",
                       name = _("print sticker"),
                       short_desc = _("Enter 0 to print, 1 to skip")
                   },
                   {
                       id = "print_route",
                       name = _("print route"),
                       short_desc = _("Enter printer routing number")
                   },
                   {
                       id = "coupon",
                       name = _("coupon"),
                       short_desc = _("Register serial number for support coupon")
                   },
                   {
                       id = "builder",
                       name = _("builder"),
                       short_desc = _("Builder Initials")
                   },
                   {
                       id = "batch",
                       name = _("batch"),
                       short_desc = _("Batch Print 1/0")
                   },
		},
                datasets = {
                    {
                        serial = get_bios_serial(),
                        order = "",
			product = App.state.netgate_model,
			print = "",
			print_route = "",
			coupon = "0",
			builder = "",
			batch = ""
                    }
                },
                actions = {
                    {
                        id = "OK",
                        name = _("OK")
                    },
                    {
                        id = "cancel",
                        name = _("Cancel")
                    }
                }, 
        } ) 
	-- end Apu.ui.present
    if response.action_id == "OK" then 
		local wan_mac_addr = get_wan_mac()
		local wlan_mac_addr = get_wlan_mac()
		local release_ver = get_version()
		local product = response.datasets[1].product
		local serial = response.datasets[1].serial
		local order = response.datasets[1].order
		local print = response.datasets[1].print
		local print_route = response.datasets[1].print_route
		local coupon = response.datasets[1].coupon
		local batch = response.datasets[1].batch
		local builder = response.datasets[1].builder
	
		postreq = "model=" .. product .. "&serial=" .. serial .. 
		"&order=" .. order .. "&release=" .. release_ver .. "&wan_mac=" ..  
		wan_mac_addr .. "&wlan_mac=" .. wlan_mac_addr .. 
		"&print=" .. print .. "&print_route=" .. print_route ..
		"&coupon=" .. coupon .. "&builder=" .. builder .. "&batch=" .. batch ..
		"&submit=Submit\n"
		postdata = io.output("/tmp/postdata")
		io.write("POST http://factory-logger.pfmechanics.com/log.php HTTP/1.0\n")
		io.write("Content-Type: application/x-www-form-urlencoded\n") 
		io.write("Content-Length: ".. string.len(postreq) .. "\n\n")
		io.write(postreq)
		io.close(postdata)
		local cmds = CmdChain.new()
		cmds:add("cat /tmp/postdata")
		cmds:add( "/usr/bin/nc </tmp/postdata -w 5 factory-logger.pfmechanics.com 80 >/tmp/postresult") 
		if cmds:execute() then
			App.ui:inform(_( "Serial Logged" ))
		else
			App.ui:inform(_( "Serial Logging FAILED")) 
		end
            
       return step:next()
    else
       return 

    end
  end
}

