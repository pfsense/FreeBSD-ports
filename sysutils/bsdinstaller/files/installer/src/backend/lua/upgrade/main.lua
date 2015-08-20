-- upgrade/main.lua
-- $Id: main.lua,v 1.5 2005/04/22 04:57:07 cpressey Exp $

Flow.new{
    id = "upgrade",
    name = _("Upgrade an Installed System"),
}:populate("."):run()
