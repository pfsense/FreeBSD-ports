-- configure/main.lua
-- $Id: main.lua,v 1.13 2005/07/08 21:24:09 cpressey Exp $

--
-- Flow drive for Configuration Flow.
--

Flow.new{
    id = "configure",
    name = _("Configure an Installed System"),
}:populate("."):run()
