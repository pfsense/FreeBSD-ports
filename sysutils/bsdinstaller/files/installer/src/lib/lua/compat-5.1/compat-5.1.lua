--
-- Compat-5.1
-- Copyright Kepler Project 2004-2005 (http://www.keplerproject.org/compat)
-- According to Lua 5.1
-- $Id: compat-5.1.lua,v 1.2 2005/07/30 02:21:34 cpressey Exp $
--

_COMPAT51 = "Compat-5.1 R4"

local LUA_DIRSEP = '/'
local LUA_OFSEP = '_'
local OLD_LUA_OFSEP = ''
local POF = 'luaopen_'

local assert, error, getfenv, ipairs, loadfile, loadlib, pairs, setfenv, setmetatable, type = assert, error, getfenv, ipairs, loadfile, loadlib, pairs, setfenv, setmetatable, type
local format, gfind, gsub = string.format, string.gfind, string.gsub

--
-- avoid overwriting the package table if it's already there
--
package = package or {}

package.path = LUA_PATH or os.getenv("LUA_PATH") or
             ("./?.lua;" ..
              "/usr/local/share/lua/5.0/?.lua;" ..
              "/usr/local/share/lua/5.0/?/?.lua;" ..
              "/usr/local/share/lua/5.0/?/init.lua" )
 
package.cpath = os.getenv("LUA_CPATH") or
             "./?.so;" ..
             "./l?.so;" ..
             "/usr/local/lib/lua/5.0/?.so;" ..
             "/usr/local/lib/lua/5.0/l?.so"

--
-- make sure require works with standard libraries
--
package.loaded = package.loaded or {}
package.loaded.string = string
package.loaded.math = math
package.loaded.io = io
package.loaded.os = os
package.loaded.table = table 
package.loaded.base = _G
package.loaded.coroutine = coroutine

--
-- avoid overwriting the package.preload table if it's already there
--
package.preload = package.preload or {}


--
-- auxiliar function to read "nested globals"
--
local function getfield (t, f)
  assert (type(f)=="string", "not a valid field name ("..tostring(f)..")")
  for w in gfind(f, "[%w_]+") do
    if not t then return nil end
    t = rawget(t, w)
  end
  return t
end


--
-- auxiliar function to write "nested globals"
--
local function setfield (t, f, v)
  for w in gfind(f, "([%w_]+)%.") do
    t[w] = t[w] or {} -- create table if absent
    t = t[w]            -- get the table
  end
  local w = gsub(f, "[%w_]+%.", "")   -- get last field name
  t[w] = v            -- do the assignment
end


--
-- looks for a file `name' in given path
--
local function search (path, name)
  for c in gfind(path, "[^;]+") do
    c = gsub(c, "%?", name)
    local f = io.open(c)
    if f then   -- file exist?
      f:close()
      return c
    end
  end
  return nil    -- file not found
end


--
-- check whether library is already loaded
--
local function loader_preload (name)
  assert (type(name) == "string", format (
    "bad argument #1 to `require' (string expected, got %s)", type(name)))
  if type(package.preload) ~= "table" then
    error ("`package.preload' must be a table")
  end
  return package.preload[name]
end


--
-- C library loader
--
local function loader_C (name)
  assert (type(name) == "string", format (
    "bad argument #1 to `require' (string expected, got %s)", type(name)))
  local fname = gsub (name, "%.", LUA_DIRSEP)
  fname = search (package.cpath, fname)
  if not fname then
    return false
  end
  local funcname = POF .. gsub (name, "%.", LUA_OFSEP)
  local f, err = loadlib (fname, funcname)
  if not f then
    funcname = POF .. gsub (name, "%.", OLD_LUA_OFSEP)
    f, err = loadlib (fname, funcname)
    if not f then
      error (format ("error loading package `%s' (%s)", name, err))
    end
  end
  return f
end


--
-- Lua library loader
--
local function loader_Lua (name)
  assert (type(name) == "string", format (
    "bad argument #1 to `require' (string expected, got %s)", type(name)))
  local path = LUA_PATH
  if not path then
    path = assert (package.path, "`package.path' must be a string")
  end
  local fname = gsub (name, "%.", LUA_DIRSEP)
  fname = search (path, fname)
  if not fname then
    return false
  end
  local f, err = loadfile (fname)
  if not f then
    error (format ("error loading package `%s' (%s)", name, err))
  end
  return f
end


-- create `loaders' table
package.loaders = package.loaders or { loader_preload, loader_C, loader_Lua, }


--
-- iterate over available loaders
--
local function load (name, loaders)
  -- iterate over available loaders
  assert (type (loaders) == "table", "`package.loaders' must be a table")
  for i, loader in ipairs (loaders) do
    local f = loader (name)
    if f then
      return f
    end
  end
  error (format ("package `%s' not found", name))
end


--
-- new require
--
function _G.require (name)
  assert (type(name) == "string", format (
    "bad argument #1 to `require' (string expected, got %s)", type(name)))
  local p = loaded[name] -- is it there?
  if p then
    return p
  end
  -- first mark it as loaded
  loaded[name] = true
  -- load and run init function
  local actual_arg = _G.arg
  _G.arg = { name }
  local res = load(name, loaders)(name)
  if res then 
    loaded[name] = res -- store result
  end
  _G.arg = actual_arg
  -- return value should be in loaded[name]
  return loaded[name]
end


--
-- new module function
--
function _G.module (name)
  local _G = getfenv(0)       -- simulate C function environment
  local ns = getfield(_G, name)         -- search for namespace
  if not ns then
    ns = {}                             -- create new namespace
    setfield(_G, name, ns)
  elseif type(ns) ~= "table" then
    error("name conflict for module `"..name.."'")
  end
  if not ns._NAME then
    ns._NAME = name
    ns._M = ns
    ns._PACKAGE = gsub(name, "[^.]*$", "")
  end
  setmetatable(ns, {__index = _G})
  loaded[name] = ns
  setfenv(2, ns)
  return ns
end


--
-- define functions' environments
--
local env = {
	loaded = package.loaded,
	loaders = package.loaders,
	package = package,
	_G = _G,
}
for i, f in ipairs { _G.module, _G.require, load, loader_preload, loader_C, loader_Lua, } do
  setfenv (f, env)
end
