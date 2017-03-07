service_utils.inc:start_service calls <rcfile> start and then calls <startcmd>.
service_utils.inc:stop_service calls <rcfile> stop and then calls <stopcmd>.
<startcmd> and <stopcmd> both call vhosts_dirty(FALSE) to clear the dirty subsystem flag.

pkg remove calls /etc/rc.packages <name> DEINSTALL before remove and POST-DEINSTALL after remove.
<custom_php_deinstall_command> only runs in POST-DEINSTALL and config file is gone by then.
Must use <custom_php_pre_deinstall_command>.

vhosts-http hard coded in service_utils.inc:get_service_status().

service_utils.inc does not load <include_file> before calling commands set 
in <service> so custom methods can't be called.  If sync_package() is called
first, <include_file> would be loaded.
 