-- $Id: server.lua,v 1.2 2005/07/31 03:58:31 cpressey Exp $
-- Demo Lua transceiver server.

local Transceiver = require("transceiver")

local server = Transceiver.new{ role="server" } -- timeout = 5

print("Server: listening")

server:connect()

print("Server: connected")

server:recv_loop(
    function(data, err)
	if not err then
		if type(data) == "table" and data.msgtype == "announce" then
			print("Server: got", data.number)
			server:send { msgtype = "reply", number = data.number }
		else
			print("Server: *** unknown term")
		end
		return true
	elseif err == "timeout" then
		print("Server: timed out")
		return true
	elseif err == "closed" then
		print("Server: client closed connection")
		return false
	else
		print("Server: ERROR: " .. err)
		return false
	end
    end
)

print("Server: exiting")
