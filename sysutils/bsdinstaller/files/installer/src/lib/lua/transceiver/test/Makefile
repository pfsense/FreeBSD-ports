# $Id: Makefile,v 1.1 2005/07/31 03:58:31 cpressey Exp $
# Makefile for Lua tranceiver.  (Basically only for the tests)

LUA=		../../../../platform/lua

test_server:
	env LD_LIBRARY_PATH='$(LUA)/lib' \
	    LUA_PATH='../../?/?.lua;../../luasocket/lua/?.lua' \
	    LUA_CPATH='../../luasocket/?.so' \
	    $(LUA)/bin/lua -lcompat-5.1 server.lua

test_client:
	env LD_LIBRARY_PATH='$(LUA)/lib' \
	    LUA_PATH='../../?/?.lua;../../luasocket/lua/?.lua' \
	    LUA_CPATH='../../luasocket/?.so' \
	    $(LUA)/bin/lua -lcompat-5.1 client.lua
