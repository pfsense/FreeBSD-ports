-- $Id: client.lua,v 1.2 2005/07/31 03:58:31 cpressey Exp $
-- Demo Lua transceiver client.

local Transceiver = require("transceiver")

local client = Transceiver.new{ role = "client" } -- timeout = 0.10

print("Client: connecting")

client:connect()

print("Client: connected!")
print("Client: sending 1000 terms")

for i = 1, 1000 do
	client:send { msgtype = "announce", number = i }
end

client:recv_loop(
    function(data, err)
	if not err then
		if type(data) == "table" and data.msgtype == "reply" then
			print("Client: got reply", data.number)
			if data.number == 1000 then
				return false
			end
		else
			print("Client: *** unknown term")
		end
		return true
	elseif err == "timeout" then
		print("Client: timed out")
		return true
	elseif err == "closed" then
		print("Client: server closed connection")
		return false
	else
		print("Client: ERROR: " .. err)
		return false
	end
    end
)

print("Client: exiting")
