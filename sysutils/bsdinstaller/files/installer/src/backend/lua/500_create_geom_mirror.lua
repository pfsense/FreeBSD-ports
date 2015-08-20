-- $Id: 500_create_geom_mirror.lua,v 1.1 2006/07/27 21:47:52 sullrich Exp $

--
-- Copyright (c)2005 Scott Ullrich.  All rights reserved.
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

-- BEGIN 500_create_geom_mirror.lua --

-- This module requires FreeBSD
if App.conf.os.name ~= "FreeBSD" then
       return
end

-- This module requires more than one disk
if App.state.storage:get_disk_count() < 2 then
	return
end

--
-- FreeBSD specific module GEOM/GMirror
--
-- If more than two disks are detected, ask if the user wishes
-- to setup a GEOM/GMirror volume.  The volume will then appear
-- in the future select disk step.
--
return {
	   id = "setup_gmirror",
	   name = _("Setup GEOM Mirror"),
	   req_state = { "storage" },
	   effect = function(step)

       -- Ask if user wants a GEOM mirror to be created
       local response = App.ui:present{
           name = _("GEOM Mirror"),
           short_desc = _("Would you like to setup a GEOM mirror? "),
	           actions = {
	               {
	                   id = "ok",
	                   name = _("Yes, setup a GEOM mirror")
	               },
	               {
	                   id = "cancel",
	                   accelerator = "ESC",
	                   name = _("No thanks")
	               }
           }
       }

       if response.action_id ~= "ok" then
           return Menu.CONTINUE
       end

       local disk1
       local disk2

       local dd = StorageUI.select_disk({
           sd = App.state.storage,
           short_desc = _(
               "Select the primary disk %s ",
               App.conf.product.name),
           cancel_desc = _("Cancel")
       })
       disk1 = dd:get_name()

       local dd = StorageUI.select_disk({
           sd = App.state.storage,
           short_desc = _(
               "Select the disk on which the mirror of %s ",
               App.conf.product.name),
           cancel_desc = _("Cancel")
       })
       disk2 = dd:get_name()

       -- Make sure disk 1 was selected
       if not disk1 then
           return Menu.CONTINUE
       end

       -- Make sure disk 2 was selected
       if not disk2 then
           return Menu.CONTINUE
       end

       if disk1 == disk2 then
	       App.ui:inform(_(
	           "You need two unique disks to create a GEOM MIRROR.")
	       )
	       return Menu.CONTINUE
       end

       local cmds = CmdChain.new()
       -- XXX: switch to a while loop and allow user to add more than 2 disks
	   cmds:add{
    	   cmdline = "${root}sbin/gmirror label -v -b split ${OS}Mirror ${disk1} ${disk2}",
    	   replacements = {
	            OS = App.conf.product.name,
	            disk1 = disk1,
	            disk2 = disk2
	          }
	   }

       -- Finally execute the commands to create the gmirror
       if cmds:execute() then
           App.ui:inform(_(
               "The GEOM mirror has been created with no errors.  " ..
               "The mirror disk will now appear in the select disk step.")
           )
           -- Survey disks again, they have changed.
           App.state.storage:survey()
       else
           App.ui:inform(_(
               "The GEOM mirror was NOT created due to errors.")
           )
       end

       return Menu.CONTINUE

   end

}

