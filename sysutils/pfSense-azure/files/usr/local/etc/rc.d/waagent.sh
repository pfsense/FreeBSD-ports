#! /bin/sh

# PROVIDE: waagent
# REQUIRE: DAEMON cleanvar sshd
# BEFORE: LOGIN
# KEYWORD: nojail

waagent_enable="YES"

. /etc/rc.subr
export PATH=$PATH:/usr/local/bin
name="waagent"
rcvar="waagent_enable"
command="/usr/local/sbin/${name}"
command_interpreter="/usr/local/bin/python2.7"
waagent_flags=" daemon &"

pidfile="/var/run/waagent.pid"

load_rc_config $name
run_rc_command "$1"

