#!/bin/sh

LUA_CPATH='/usr/local/lib/lua/5.0/?.so' exec /usr/local/bin/lua50 -l/usr/local/share/lua/5.0/compat-5.1.lua $*
