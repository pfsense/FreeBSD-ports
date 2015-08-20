-- pit/main.lua
-- $Id: main.lua,v 1.10 2005/04/22 04:57:07 cpressey Exp $
-- Flow for "pre-install tasks"

Flow.new{
    id = "pre_install_tasks",
    name = _("Pre-Install Tasks")
}:populate("."):run()
