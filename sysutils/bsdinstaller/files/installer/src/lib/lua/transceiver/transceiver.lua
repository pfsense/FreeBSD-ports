-- $Id: transceiver.lua,v 1.5 2005/08/02 18:12:06 cpressey Exp $
-- Lua interprocess data transceiver module.

--
-- Copyright (c)2005 Chris Pressey.  All rights reserved.
--
-- Redistribution and use in source and binary forms, with or without
-- modification, are permitted provided that the following conditions
-- are met:
--
-- 1. Redistributions of source code must retain the above copyright
--    notices, this list of conditions and the following disclaimer.
-- 2. Redistributions in binary form must reproduce the above copyright
--    notices, this list of conditions, and the following disclaimer in
--    the documentation and/or other materials provided with the
--    distribution.
-- 3. Neither the names of the copyright holders nor the names of their
--    contributors may be used to endorse or promote products derived
--    from this software without specific prior written permission. 
--
-- THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
-- ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES INCLUDING, BUT NOT
-- LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
-- FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE
-- COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
-- INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
-- BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
-- LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
-- CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
-- LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
-- ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
-- POSSIBILITY OF SUCH DAMAGE.
--

module "transceiver"

local Socket = require("socket")

Transceiver = {}
Transceiver.new = function(tab)
	local host = tab.host or "127.0.0.1"
	local port = tonumber(tab.port) or 9999
	local role = tab.role
	local sock = nil
	local timeout = tab.timeout or 1
	local tx = {}

	--
	-- Private functions.
	--

	--
	-- Take an (almost-)arbitrary Lua datum and roll it into a string.
	--
	local roll
	roll = function(x)
		local t = type(x)

		if t == "boolean" then
			if x then
				return "BT"
			else
				return "BF"
			end
		elseif t == "number" then
			return "N" .. tostring(x) .. " "
		elseif t == "string" then
			return "S" .. string.len(x) .. " " .. x
		elseif t == "table" then
			local k, v, nent, acc, size

			nent = 0
			for k, v in x do
				nent = nent + 1
			end
			size = table.getn(x)

			if nent > 0 and size == 0 then
				-- It's a dictionary.
				acc = "D" .. tostring(nent) .. " "
				for k, v in x do
					assert(type(k) == "string",
					       "Dictionary cannot contain non-string keys")
					acc = acc .. roll(k) .. roll(v)
				end
			else
				-- It's a list.
				acc = "L" .. tostring(size) .. " "
				for k, v in x do
					assert(type(k) == "number",
					       "List cannot contain non-numeric keys")
					acc = acc .. roll(v)
				end
			end
			
			return acc
		else
			error("Datum cannot contain a `" .. t .. "' type")
		end
	end

	--
	-- We're a bit paranoid about unbounded lengths, otherwise
	-- we'd just use Lua's built-in capturing & conversion...
	--
	local get_number = function(s, pos)
		local c
		local acc = 0

		c = string.sub(s, pos, pos)
		while string.find(c, "%d") do
			acc = acc * 10 + tonumber(c)
			pos = pos + 1
			c = string.sub(s, pos, pos)
		end

		return acc, pos
	end

	--
	-- Returns the thing unrolled, plus the new position in the string.
	--
	local unroll_string
	unroll_string = function(s, pos)
		local t
		pos = pos or 1

		t = string.sub(s, pos, pos)
		if t == "B" then
			pos = pos + 1
			if string.sub(s, pos, pos) == "T" then
				return true, pos + 1
			elseif string.sub(s, pos, pos) == "F" then
				return false, pos + 1
			else
				error("Badly-formed rolled Boolean")
			end
		elseif t == "N" then
			local num, pos = get_number(s, pos + 1)
			return num, pos + 1
		elseif t == "S" then
			local len, pos = get_number(s, pos + 1)
			return string.sub(s, pos + 1, pos + len), pos + len + 1
		elseif t == "L" then
			local len, pos = get_number(s, pos + 1)
			local list = {}
			local d
			pos = pos + 1
			while len > 0 do
				d, pos = unroll_string(s, pos)
				table.insert(list, d)
				len = len - 1
			end
			return list, pos
		elseif t == "D" then
			local nent, pos = get_number(s, pos + 1)
			local dict = {}
			local k, v
			pos = pos + 1
			while nent > 0 do
				k, pos = unroll_string(s, pos)
				v, pos = unroll_string(s, pos)
				dict[k] = v
				nent = nent - 1
			end
			return dict, pos
		else
			error("Bad rolled type indicator `" .. t .. "'")
		end
	end

	--
	-- Take a string representing a Lua datum and unroll it.
	--
	local unroll = function(x)
		local x, pos = unroll_string(x)
		return x
	end

	--
	-- Public methods.
	--

	--
	-- Send a datum to the other side.
	--
	tx.send = function(tx, data)
		local str = roll(data)
		assert(sock:send(str .. "\n"), "Could not send data on socket")
	end

	--
	-- Receive a single datum from the other side.
	--
	tx.recv = function(tx)
		local data, err = sock:receive()
		if not data then
			return nil, err
		else
			return unroll(data)
		end
	end

	--
	-- Go into a loop, receiving data from the other side.  Each time
	-- a datum is received or an error occurs, the given callback
	-- function is called with the data as the first argument (or,
	-- if an error occurs, nil as the first argument, and the error
	-- as the second argument.)  Specific error messages are:
	--   "closed" - the other side closed the connection
	--   "timeout" - the connection timed out
	-- The callback may return either true to indicate that the loop
	-- must continue, or false to indicate that the loop must terminate.
	--
	tx.recv_loop = function(tx, callback)
		local data, err, cont

		cont = true
		while cont do
			data, err = sock:receive()
			if not err then
				data = unroll(data)
			end			
			cont = callback(data, err)
		end
	end

	--
	-- Go into a loop, trying to connect to the other side.
	-- Callback arguments and return value are similar to those of
	-- recv_loop, except that instead of data, a success flag
	-- is passed.
	--
	tx.connect_loop = function(tx, callback)
		local success, err, cont

		cont = true
		while cont do
			success, err = tx:connect()
			cont = callback(success, err)
		end
	end

	--
	-- Set up the connect method, which is different depending
	-- on whether this end of the transceiver is a client or server.
	--
	-- The connect method returns either true, if a connection was
	-- successfully established, or nil followed by an error message.
	--
	if role == "server" then
		tx.connect = function(tx)
			if sock then
				return true -- already connected
			end
			local lsock, err = Socket.bind(host, port)
			if not lsock then
				return nil, err
			end
			sock, err = lsock:accept()
			if not sock then
				return nil, err
			end
			sock:settimeout(timeout)
			return true
		end
	elseif role == "client" then
		tx.connect = function(tx)
			if sock then
				return true -- already connected
			end
			local err
			sock, err = Socket.connect(host, port)
			if not sock then
				return nil, err
			end
			sock:settimeout(timeout)
			return true
		end
	else
		error("Role must be either client or server")
	end

	tx.disconnect = function(tx)
		if sock then
			sock:close()
			sock = nil
		end
	end

	tx.is_connected = function(tx)
		return sock ~= nil
	end

	return tx
end

return Transceiver
