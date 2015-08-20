-- $Id: main.lua,v 1.6 2005/04/22 04:57:07 cpressey Exp $

Menu.new{
    id = "configure",
    name = _("Select Component to Configure"),
    short_desc = _("Choose one of the following things to configure."),
}:populate("."):loop()
