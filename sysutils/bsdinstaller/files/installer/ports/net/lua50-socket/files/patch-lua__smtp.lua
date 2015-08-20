--- lua/smtp.lua	2005/07/26 21:06:14	1.1
+++ lua/smtp.lua	2005/07/31 00:05:08	1.2
@@ -14,11 +14,13 @@ local string = require("string")
 local math = require("math")
 local os = require("os")
 local socket = require("socket")
-local tp = require("socket.tp")
+local tp = require("tp")
 local ltn12 = require("ltn12")
 local mime = require("mime")
 module("socket.smtp")
 
+smtp = {}
+
 -----------------------------------------------------------------------------
 -- Program constants
 -----------------------------------------------------------------------------
@@ -222,7 +224,7 @@ local function adjust_headers(mesgt)
     mesgt.headers = lower
 end
 
-function message(mesgt)
+function smtp.message(mesgt)
     adjust_headers(mesgt)
     -- create and return message source
     local co = coroutine.create(function() send_message(mesgt) end)
@@ -236,7 +238,7 @@ end
 ---------------------------------------------------------------------------
 -- High level SMTP API
 -----------------------------------------------------------------------------
-send = socket.protect(function(mailt)
+smtp.send = socket.protect(function(mailt)
     local s = open(mailt.server, mailt.port)
     local ext = s:greet(mailt.domain)
     s:auth(mailt.user, mailt.password, ext)
@@ -246,3 +248,5 @@ send = socket.protect(function(mailt)
 end)
 
 --getmetatable(_M).__index = nil
+
+return smtp
