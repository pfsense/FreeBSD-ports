/*
 * $Id: lua_gettext.c,v 1.15 2005/09/01 20:08:35 cpressey Exp $
 */

#include "lua.h"
#include "lualib.h"
#include "lauxlib.h"

#if (__NetBSD__ || __linux__)
#include <libintl.h>
#include <locale.h>
#else
#include "libintl.h"
#endif

extern int _nl_msg_cat_cntr;

/*** Prototypes ***/

LUA_API int luaopen_lgettext(lua_State *);

/*** Globals ***/

const char *package = "";
const char *locale_dir = "";

/*** Methods ***/

static int
lua_gettext_init(lua_State *L __unused)
{
	setlocale(LC_ALL, "");
	bindtextdomain(package, locale_dir);
	textdomain(package);

	return(0);
}

static int
lua_gettext_set_package(lua_State *L)
{
	package = luaL_checkstring(L, 1);

	return(0);
}

static int
lua_gettext_set_locale_dir(lua_State *L)
{
	locale_dir = luaL_checkstring(L, 1);

	return(0);
}

static int
lua_gettext_translate(lua_State *L)
{
	lua_pushstring(L, gettext(luaL_checkstring(L, 1)));

	return(1);
}

static int
lua_gettext_notify_change(lua_State *L __unused)
{
	++_nl_msg_cat_cntr;

	return(0);
}

/**** Binding Tables ****/

const luaL_reg gettext_methods[] = {
	{"init",		lua_gettext_init },
	{"set_package",		lua_gettext_set_package },
	{"set_locale_dir",	lua_gettext_set_locale_dir },
	{"translate",		lua_gettext_translate },
	{"notify_change",	lua_gettext_notify_change },

	{0, 0}
};

/*** REGISTER ***/

LUA_API int
luaopen_lgettext(lua_State *L)
{
	luaL_openlib(L, "GetText", gettext_methods, 0);

	return(1);
}
