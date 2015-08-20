-- $Id: PackageGraph.lua,v 1.43 2005/08/26 04:25:24 cpressey Exp $

--
-- Copyright (c)2005 Chris Pressey.  All rights reserved.
--
-- Redistribution and use in source and binary forms, with or without
-- modification, are permitted provided that the following conditions
-- are met:
--
-- 1. Redistributions of source code must retain the above copyright
--    notices, this list of conditions and the following disclaimer.
-- 2. Redistributions in binary form must reproduce the above copyright
--    notices, this list of conditions, and the following disclaimer in
--    the documentation and/or other materials provided with the
--    distribution.
-- 3. Neither the names of the copyright holders nor the names of their
--    contributors may be used to endorse or promote products derived
--    from this software without specific prior written permission. 
--
-- THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
-- ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES INCLUDING, BUT NOT
-- LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
-- FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE
-- COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
-- INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
-- BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
-- LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
-- CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
-- LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
-- ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
-- POSSIBILITY OF SUCH DAMAGE.
--

-- Package, Package.Set, Package.Graph, and Package.List classes.

module "package"

local App = require("app")
local POSIX = require("posix")
local Pty = require("pty")
local FileName = require("filename")
local CmdChain = require("cmdchain")

Package = {}

--
-- Global state
--

Package.cache = {}
setmetatable(Package.cache, { __mode = "v" })	-- Make the cache table weak

--
-- Global functions
--

--[[---------]]--
--[[ Package ]]--
--[[---------]]--

Package.new = function(tab)			-- Constructor.
	tab = tab or {}
	local method = {}			-- instance
	local name = assert(tab.name)
	local prereqs				-- cache

	--
	-- Retrieve the name of this package.
	--
	method.get_name = function(self)
		return name
	end

	--
	-- Determine whether this package is installed
	-- on the given TargetSystem.
	--
	method.is_installed_on = function(self, ts)
		local filename = App.expand(
		    "${root}${base}var/db/pkg/${name}",
		    {
			base = ts:get_base(),
			name = name
		    }
		)
		return FileName.is_dir(
		)
	end

	--
	-- Determine whether this package is archived
	-- on the given TargetSystem.
	--
	method.is_archived_on = function(ps, ts)
		local filename = App.expand(
		    "${root}${base}usr/ports/packages/All/${name}.${suffix}",
		    {
			base = ts:get_base(),
			name = name,
			suffix = App.conf.package_suffix
		    }
		)
		return FileName.is_file(filename)
	end

	--
	-- Return a Package.Set of Packages that are required by this Package.
	-- Note that this may return packages which may or may not already
	-- be installed on any given target system, and lists only direct
	-- requirements of the given package.
	--
	method.get_prerequisites = function(self, ts)
		local cmd, pat

		if prereqs then	-- cached
			return prereqs
		end

		if self:is_archived_on(ts) then
			cmd = App.expand(
			    "${root}${TAR} -O -z -x -f " ..
			      "${root}usr/ports/packages/All/${pkg_name}.${suffix} " ..
			      "+CONTENTS | ${root}${GREP} '^@pkgdep'", {
				base = ts:get_base(),
				pkg_name = self:get_name(),
				suffix = App.conf.package_suffix
			    }
			)
			pat = "^%@pkgdep%s*([^%s]+)"
		else
			cmd = App.expand(
			    "${root}${CHROOT} ${root}${base} /${PKG_INFO} -r ${pkg_name}", {
				base = ts:get_base(),
				pkg_name = self:get_name()
			    }
			)
			pat = "^Dependency:%s*([^%s]+)"
		end

		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil
		end
		local pkgs = Package.Set.new()
		local line = pty:readline()
		while line do
			--
			-- Only look at lines that begin with
			-- the established pattern.
			--
			local found, len, rpkg_name = string.find(line, pat)
			if found then
				pkgs:add(Package.new{ name = rpkg_name })
			end
			line = pty:readline()
		end

		pty:close()

		prereqs = pkgs
		return pkgs
	end

	--
	-- Return a Package.Set of Packages that require this Package.
	-- Note that this may return packages which may or may not
	-- already be installed on some given target sysem, and it
	-- lists only the direct dependencies of the given package.
	-- (That statement is probably not true in practice, but
	-- the idea is to not rely on it, so that we have greater
	-- freedom in implementing it.)
	--
	method.get_dependents = function(self, ts)
		local cmd = App.expand(
		    "${root}${CHROOT} ${root}${base} /${PKG_INFO} -R ${pkg_name}", {
			base = ts:get_base(),
			pkg_name = self:get_name()
		    }
		)
		local pty = Pty.Logged.open(cmd, App.log_string)
		if not pty then
			return nil
		end
		local pkgs = Package.Set.new()
		local seen_required_by = false
		local line = pty:readline()
		while line do
			--
			-- Only look at lines that follow the "Required by:" line.
			--
			if seen_required_by then
				found, len, rpkg_name =
				    string.find(line, "^%s*([^%s]+)")
				if found then
					pkgs:add(Package.new{ name = rpkg_name })
				end
			else
				if string.find(line, "^Required by:") then
					seen_required_by = true
				end
			end
			line = pty:readline()
		end
		pty:close()
		
		return pkgs
	end

	--
	-- CommandChain-creating methods.
	--
	
	--
	-- Create commands to install this package on a target system.
	--
	method.cmds_install = function(self, cmds, ts, tab)
		--
		-- Determine the real location of the temporary directory
		-- in which we can put package tarballs while installing.
		--
		local pkg_tmp

		if tab.tmp_dir then
			pkg_tmp = tab.tmp_dir
		else
			local tmp_dir = App.conf.dir.root ..
			    ts:get_base() .. "tmp"
			local link = POSIX.readlink(tmp_dir)
			if link ~= nil then
				pkg_tmp = FileName.remove_leading_slash(link)
			elseif FileName.is_dir(tmp_dir) then
				pkg_tmp = "tmp"
			end
			local real_tmp_dir = App.conf.dir.root ..
			    ts:get_base() .. pkg_tmp
			assert(FileName.is_dir(real_tmp_dir),
			    real_tmp_dir .. " does not exist!")
		end

		cmds:set_replacements{
		    base	= ts:get_base(),
		    pkg_tmp	= pkg_tmp,
		    pkg_suffix	= App.conf.package_suffix,
		    pkg_name	= self:get_name()
		}

		--
		-- Check to see if this package is installed, or just
		-- archived, on the given source TargetSystem.  If just
		-- archived, we can just copy it into the temporary
		-- directory.  If it's actually installed, we must
		-- re-create the package file first.
		--
		if self:is_archived_on(App.state.source) then
			cmds:add(
			    "${root}${CP} ${root}usr/ports/packages/All/${pkg_name}.${pkg_suffix} " .. 
			        "${root}${base}${pkg_tmp}/${pkg_name}.${pkg_suffix}"
			)
		else
			--
			-- We chroot here, even though it's kind of useless
			-- if the root dir is "/", to make sure we're in the
			-- space from which the package can be created.
			--
			cmds:add(
			    "${root}${CHROOT} ${root} /${PKG_CREATE} -b ${pkg_name} /${base}${pkg_tmp}/${pkg_name}.${pkg_suffix}"
			)
		end
		cmds:add({
			cmdline = "${root}${CHROOT} ${root}${base} " ..
				  "/${PKG_ADD} /${pkg_tmp}/${pkg_name}.${pkg_suffix}",
			tag = self,
			on_executed = tab.on_executed
		    },
		    "${root}${RM} ${root}${base}${pkg_tmp}/${pkg_name}.${pkg_suffix}"
		)
	end

	--
	-- Create commands to remove this package from a target system.
	--
	method.cmds_remove = function(self, cmds, ts, tab)
		cmds:add{
		    cmdline = "${root}${CHROOT} ${root}${base} /${PKG_DELETE} ${pkg_name}",
		    replacements = {
			base = ts:get_base(),
			pkg_name = self:get_name()
		    },
		    tag = self,
		    on_executed = tab.on_executed
		}
	end

	--
	-- 'Constructor'.
	--
	-- Note that Package objects lack individual state.  Creating a new
	-- Package object with the same name as an existing Package object
	-- returns another reference to the prior object.  Another way to
	-- this of this is that Package objects are 'name equivalent'.
	--
	-- This is implemented by cacheing the instances in a 'weak' table.
	-- ('weak' = their existence does not prevent garbage collection.)
	-- See "Programming in Lua", chapter 17.1, for more information.
	--

	if Package.cache[name] ~= nil then
		return Package.cache[name]
	else
		Package.cache[name] = method
		return method
	end
end

--[[-------------]]--
--[[ Package.Set ]]--
--[[-------------]]--

Package.Set = {}
Package.Set.new = function(tab)			-- Constructor.
	tab = tab or {}
	local method = {}			-- instance
	local e = tab.e or {}			-- pkg obj -> boolean

	--
	-- Add a given Package to this Package.Set.
	--
	method.add = function(self, pkg)
		e[pkg] = true
	end

	--
	-- Remove the given Package from this Package.Set.
	--
	method.remove = function(self, pkg)
		e[pkg] = nil
	end

	--
	-- Remove all Packages from this Package.Set.
	--
	method.clear = function(self)
		e = {}
	end

	--
	-- Create an identical (but independent) copy of this Package.Set.
	--
	method.copy = function(self)
		local new_set = Package.Set.new()
		local pkg, bool
		for pkg, bool in pairs(e) do
			if bool then
				new_set:add(pkg)
			end
		end
		return new_set
	end

	--
	-- Determine how many packages are in this Package.Set.
	--
	method.size = function(self)
		local i = 0
		local pkg, bool
		for pkg, bool in pairs(e) do
			i = i + 1
		end
		return i
	end

	--
	-- See if this Package.Set contains the given Package.
	--
	method.contains = function(self, pkg)
		if e[pkg] then
			return true
		else
			return false
		end
	end

	--
	-- Return an iterator which yields the next Package object in
	-- this Package.Set each time it is called (typically in a 'for'.)
	-- No particular ordering is defined for this iteration.
	--
	method.each_pkg = function(self)
		local pkg, bool
		local list = {}
		local i, n = 0, 0
		
		for pkg, bool in pairs(e) do
			if bool then
				table.insert(list, pkg)
			end
			n = n + 1
		end

		return function()
			if i <= n then
				i = i + 1
				return list[i]
			end
		end
	end

	--
	-- Add all the Packages in the given Package.Set to this Set.
	--
	method.take_union = function(self, from_ps)
		local pkg
		for pkg in from_ps:each_pkg() do
			self:add(pkg)
		end
	end

	--
	-- Remove from this Package.Set all the Packages present
	-- in the given Package.Set.
	--
	method.take_difference = function(self, from_ps)
		local pkg
		for pkg in from_ps:each_pkg() do
			self:remove(pkg)
		end
	end

	--
	-- Only keep packages which meet the given criterion.
	--
	method.filter = function(self, criterion)
		local pkg, bool
		for pkg, bool in pairs(e) do
			if not criterion(pkg) then
				self:remove(pkg)
			end
		end
	end

	--
	-- Populate this Package.Set with the Packages installed
	-- on the given TargetSystem.
	--
	method.enumerate_installed_on = function(self, ts)
		local dir_name = App.expand("${root}${base}var/db/pkg", {
			base = ts:get_base()
		    })
		local dir = POSIX.dir(dir_name)
		if dir then
			local i, filename
			for i, filename in ipairs(dir) do
				if filename ~= "." and filename ~= ".." then
					self:add(Package.new{ name = filename })
				end
			end
		else
			App.log_warn("Package.Set: No such dir: %s", dir_name)
		end
	end

	--
	-- Populate this Package.Set with the Packages archived in
	-- the given directory.
	--
	method.enumerate_archives_in = function(self, dir_name)
		local dir = POSIX.dir(dir_name)
		if dir then
			local i, filename
			for i, filename in ipairs(dir) do
				if filename ~= "." and filename ~= ".." then
					self:add(Package.new{
					    name = FileName.remove_extension(filename)
					})
				end
			end
		else
			App.log_warn("Package.Set: No such dir: %s", dir_name)
		end
	end

	--
	-- Populate this Package.Set with the Packages archived in
	-- the standard archive directory of the given TargetSystem.
	--
	method.enumerate_archived_on = function(self, ts)
		self:enumerate_archives_in(
		    App.expand("${root}${base}usr/ports/packages/All", {
			base = ts:get_base()
		    })
		)
	end

	--
	-- Populate this Package.Set with the Packages either installed on
	-- or archived on the given TargetSystem.
	--
	method.enumerate_present_on = function(self, ts)
		local included_pkgs = Package.Set.new()

		included_pkgs:enumerate_archived_on(App.state.source)
		self:enumerate_installed_on(ts)
		self:take_union(included_pkgs)
	end

	--
	-- Create a Package.Graph from this set of packages, using
	-- packages as the vertices and {prerequisites,dependents}
	-- as the edges.  The nature of the edges is specified by
	-- the callback function, which must take a Package object and
	-- return a Package.Set of related Packages.  e.g.
	--
	--   pg = ps:to_graph(function(pkg)
	--       return pkg:get_prerequisites()
	--   end)
	--
	-- The third argument, if true, causes a progress bar to be
	-- displayed while the transformation takes place.
	--
	method.to_graph = function(self, callback, use_prog_bar)
		local g = Package.Graph.new()
		local pkg, bool, pkgs, edge_pkg, pr, i, n

		local add_to_graph
		add_to_graph = function(pkg)
			if not g:contains(pkg) then
				g:add_vertex(pkg)
				local pkgs = callback(pkg)
				for edge_pkg in pkgs:each_pkg() do
					add_to_graph(edge_pkg)
					g:add_edge(pkg, edge_pkg)
				end
			end
		end

		n = 0
		if use_prog_bar then
			pr = App.ui:new_progress_bar{
			    title = _("Calculating package dependencies...")
			}
			pr:start()
			for pkg, bool in pairs(e) do
				n = n + 1
			end
		end

		i = 0
		for pkg, bool in pairs(e) do
			add_to_graph(pkg)
			if use_prog_bar then
				i = i + 1
				pr:set_amount((i * 100) / n)
				pr:update()
			end
		end
		
		if use_prog_bar then
			pr:stop()
		end

		return g
	end

	--
	-- Return a new Package.List which contains all the Packages
	-- in this Package.Set (in an arbitrary order.)
	--
	method.to_list = function(self)
		local list = Package.List.new()
		local pkg, bool

		for pkg, bool in pairs(e) do
			list:add(pkg)
		end

		return list
	end

	return method
end

--[[---------------]]--
--[[ Package.Graph ]]--
--[[---------------]]--

Package.Graph = {}
Package.Graph.new = function(tab)		-- Constructor.
	tab = tab or {}
	local method = {}			-- instance
	local v = {}				-- v[p] = {p, p, p, p, ...}
						-- where p's are Package objs

	--
	-- Add a given Package, as a vertex, to this Package.Graph.
	--
	method.add_vertex = function(pg, pkg)
		v[pkg] = {}
	end

	--
	-- Remove a given Package vertex from this Package.Graph.
	-- Note that this automatically removes all edges that
	-- were outgoing from that vertex.
	--
	method.remove_vertex = function(pg, pkg)
		v[pkg] = nil
	end

	--
	-- Adds an edge from v_pkg to e_pkg to this Package.Graph.
	-- Note that this requires that both v_pkg (the source of
	-- the edge) and e_pkg (the destination of the edge) both
	-- already exist as vertices in this Package.Graph.
	--
	method.add_edge = function(pg, v_pkg, e_pkg)
		assert(v[v_pkg], "Cannot add edge to non-existent vertex")
		assert(v[e_pkg], "Edge cannot lead to non-existent vertex")
		table.insert(v[v_pkg], e_pkg)
	end

	--
	-- Determine how many Package vertices are in this Package.Graph.
	--
	method.size = function(pg)
		local i = 0
		local pkg, bool
		for pkg, bool in pairs(v) do
			i = i + 1
		end
		return i
	end

	--
	-- Return an iterator which yields the next Package object in
	-- this Package.Graph each time it is called (typically in a 'for'.)
	-- No particular ordering is defined for this iteration.
	--
	method.each_pkg = function(pg)
		local pkg, edge_list
		local list = {}
		local i, n = 0, 0
		
		for pkg, edge_list in pairs(v) do
			table.insert(list, pkg)
			n = n + 1
		end

		return function()
			if i <= n then
				i = i + 1
				return list[i]
			end
		end
	end

	--
	-- See if this Package.Graph contains the given Package, as a vertex.
	--
	method.contains = function(pg, pkg)
		if v[pkg] ~= nil then
			return true
		else
			return false
		end
	end

	--
	-- Perform a topological sort of the Package.Graph by
	-- doing a depth-first search and adding each vertex
	-- to a list once it is finished being visted.
	--
	method.topological_sort = function(pg)
		local pkg, edge_list
		local pkg_list = Package.List.new()

		local visited = {}
		local visit
		visit = function(pkg)
			local i, e_pkg
			assert(v[pkg], "Inconsistent package graph!")
			visited[pkg] = true
			for i, e_pkg in ipairs(v[pkg]) do
				if not visited[e_pkg] then
					visit(e_pkg)
				end
			end
			pkg_list:add(pkg)
		end

		for pkg, edge_list in pairs(v) do
			if not visited[pkg] then
				visit(pkg)
			end
		end

		return pkg_list
	end

	--
	-- Return a new Package.Set that represents this Package.Graph
	-- without any relationships.
	--
	method.to_set = function(pg)
		local set = Package.Set.new()
		local pkg

		for pkg in pg:each_pkg() do
			set:add(pkg)
		end

		return set
	end

	return method
end


--[[--------------]]--
--[[ Package.List ]]--
--[[--------------]]--

Package.List = {}
Package.List.new = function(tab)		-- Constructor.
	tab = tab or {}
	local method = {}			-- instance
	local list = tab.list or {}		-- integer index -> pkg obj

	--
	-- Add the given package to the end of this list.
	--
	method.add = function(self, pkg)
		table.insert(list, pkg)
	end

	--
	-- Remove the given package from this Package.List.
	--
	method.remove = function(self, pkg)
		local i, p
		for i, p in ipairs(list) do
			if p == pkg then
				table.remove(list, i)
				return true
			end
		end
		return false
	end

	--
	-- Remove all packages from this Package.List.
	--
	method.clear = function(self)
		list = {}
	end

	--
	-- Create and return an identical copy of this Package.List.
	--
	method.copy = function(self)
		local new_list = Package.List.new()
		local i, pkg
		for i, pkg in ipairs(list) do
			new_list:add(pkg)
		end
		return new_list
	end

	--
	-- Determine how many packages are in this Package.List.
	--
	method.size = function(self)
		return table.getn(list)
	end

	--
	-- Check if the given package is on this Package.List.
	--
	method.contains = function(self, pkg)
		local i, p
		for i, p in ipairs(list) do
			if p == pkg then
				return true
			end
		end
		return false
	end

	--
	-- Remove from this Package.List all the Packages present
	-- in the given Package.Set.
	--
	method.take_difference = function(self, ps)
		local i = 1
		while i < table.getn(list) do
			if ps:contains(list[i]) then
				table.remove(list, i)
			else
				i = i + 1
			end
		end
	end

	--
	-- Return an iterator which yields the next Package object in
	-- this Package.List each time it is called (typically in a 'for'.)
	--
	method.each_pkg = function(self)
		local i, n = 0, table.getn(list)

		return function()
			if i <= n then
				i = i + 1
				return list[i]
			end
		end
	end

	--
	-- Return a new Package.Set that represents this Package.List
	-- without any relationships.
	--
	method.to_set = function(self)
		local set = Package.Set.new()
		local pkg

		for pkg in self:each_pkg() do
			set:add(pkg)
		end

		return set
	end

	--
	-- Sort this Package.List sorted according to the given criterion
	-- (or lexically by name, if no criterion is given.)
	--
	method.sort = function(ps, criterion)
		criterion = criterion or function(a, b)
			return a:get_name() < b:get_name()
		end
		table.sort(list, criterion)
	end

	--
	-- Methods for Package.Lists which construct command-chains.
	--

	--
	-- This function assumes you've already set up the list in a way
	-- that makes sense:
	-- * all required packages are listed;
	-- * packages are listed in the proper dependency order;
	-- * already-installed packages are not listed.
	--
	-- Note that the command chain that this method creates has the
	-- side-effect of *removing the package entries from the given
	-- Package.List* as each install command is successfully
	-- executed.  This allows the program to check how many of the
	-- packages were successfully installed (and how many were not.)
	--
	method.cmds_install_all = function(self, cmds, ts, tab)
		local pkg
		tab = tab or {}
		tab.on_executed = function(cmd, result, output)
		        if result == 0 then
				self:remove(cmd.tag)
		        end
		end

		for pkg in self:each_pkg() do
			pkg:cmds_install(cmds, ts, tab)
		end
	end

	--
	-- Remove a set of packages from a target system.
	--
	method.cmds_remove_all = function(self, cmds, ts, tab)
		local pkg
		tab = tab or {}
		tab.on_executed = function(cmd, result, output)
			if result == 0 then
				self:remove(cmd.tag)
			end
		end

		for pkg in self:each_pkg() do
			pkg:cmds_remove(cmds, ts, tab)
		end
	end

	return method
end

return Package
