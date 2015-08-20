-- install/main.lua
-- $Id: main.lua,v 1.12 2005/07/08 20:18:20 cpressey Exp $

--
-- Flow driver for Install Flow.
--

Flow.new{
    id = "install",
    name = _("Install OS")
}:populate("."):run()
