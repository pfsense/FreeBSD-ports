-- $Id: Bitwise.lua,v 1.4 2005/07/23 19:26:26 cpressey Exp $
-- Package for (pure-Lua portable but extremely slow) bitwise arithmetic.

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

-- BEGIN lib/bitwise.lua --

module "bitwise"

--[[---------]]--
--[[ Bitwise ]]--
--[[---------]]--

local odd = function(x)
	return x ~= math.floor(x / 2) * 2
end

Bitwise = {}

Bitwise.bw_and = function(a, b)
	local c, pow = 0, 1
	while a > 0 or b > 0 do
		if odd(a) and odd(b) then
			c = c + pow
		end
		a = math.floor(a / 2)
		b = math.floor(b / 2)
		pow = pow * 2
	end
	return c
end

Bitwise.bw_or = function(a, b)
	local c, pow = 0, 1
	while a > 0 or b > 0 do
		if odd(a) or odd(b) then
			c = c + pow
		end
		a = math.floor(a / 2)
		b = math.floor(b / 2)
		pow = pow * 2
	end
	return c
end

return Bitwise

-- END of lib/bitwise.lua --
