--[[------------------------------------------------------------------------
curses.lua
support code for curses library
usage lua -lcurses ...

Author: Tiago Dionizio (tngd@mega.ist.utl.pt)
$Id: curses.lua,v 1.2 2005/07/31 02:46:32 cpressey Exp $
--]]------------------------------------------------------------------------

--[[ Documentation ---------------------------------------------------------



--]]------------------------------------------------------------------------

module "curses"

curses = require("lcurses")

return curses
