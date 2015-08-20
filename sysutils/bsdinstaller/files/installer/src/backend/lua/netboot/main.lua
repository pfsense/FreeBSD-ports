-- netboot/main.lua
-- $Id: main.lua,v 1.6 2005/04/22 04:57:07 cpressey Exp $

Flow.new{
    id = "netboot",
    name = _("Set Up NetBoot Server")
}:populate("."):run()
