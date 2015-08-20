--- lua/tp.lua	2005/07/26 21:06:14	1.1
+++ lua/tp.lua	2005/07/31 00:05:08	1.2
@@ -14,6 +14,8 @@ local socket = require("socket")
 local ltn12 = require("ltn12")
 module("socket.tp")
 
+tp = {}
+
 -----------------------------------------------------------------------------
 -- Program constants
 -----------------------------------------------------------------------------
@@ -108,7 +110,7 @@ function metat.__index:close()
 end
 
 -- connect with server and return c object
-function connect(host, port, timeout)
+function tp.connect(host, port, timeout)
     local c, e = socket.tcp()
     if not c then return nil, e end
     c:settimeout(timeout or TIMEOUT)
@@ -121,3 +123,5 @@ function connect(host, port, timeout)
 end
 
 --getmetatable(_M).__index = nil
+
+return tp
