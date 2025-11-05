#!/bin/sh
#
# sqpmon.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

LOG_PREFIX_PKG_SQUID="squid"
SQUID_ENABLED=$(/usr/local/sbin/read_xml_tag.sh string installedpackages/squid/config/enable_squid)
if [ "${SQUID_ENABLED}" != "on" ]; then
	echo "INFO [${LOG_PREFIX_PKG_SQUID}] Squid is disabled, exiting." | /usr/bin/logger -p daemon.info -i -t Squid_Alarm
	exit 0
fi

if [ `/bin/pgrep -f "sqpmon.sh" | /usr/bin/wc -l` -ge 1 ]; then
	exit 0
fi

set -e

LOOP_SLEEP=55

if [ -f /var/run/squid_alarm ]; then
	/bin/rm -f /var/run/squid_alarm
fi

# Sleep 5 seconds on startup not to mangle with existing boot scripts.
sleep 5

# Squid monitor 1.2
while [ /bin/true ]; do
	if [ ! -f /var/run/squid_alarm ]; then
		NUM_PROCS=`/bin/ps auxw | /usr/bin/grep "[s]quid -f" | /usr/bin/awk '{print $2}' | /usr/bin/wc -l | /usr/bin/awk '{ print $1 }'`
		if [ $NUM_PROCS -lt 1 ]; then
			# squid is down
			echo "INFO [${LOG_PREFIX_PKG_SQUID}] Squid has exited. Reconfiguring filter." | \
				/usr/bin/logger -p daemon.info -i -t Squid_Alarm
			echo "INFO [${LOG_PREFIX_PKG_SQUID}] Attempting restart..." | /usr/bin/logger -p daemon.info -i -t Squid_Alarm
			/usr/local/etc/rc.d/squid.sh start
			sleep 3
			echo "INFO [${LOG_PREFIX_PKG_SQUID}] Reconfiguring filter..." | /usr/bin/logger -p daemon.info -i -t Squid_Alarm
			/etc/rc.filter_configure
			touch /var/run/squid_alarm
		fi
	fi
	NUM_PROCS=`/bin/ps auxw | /usr/bin/grep "[s]quid -f" | /usr/bin/awk '{print $2}' | /usr/bin/wc -l | /usr/bin/awk '{ print $1 }'`
	if [ $NUM_PROCS -gt 0 ]; then
		if [ -f /var/run/squid_alarm ]; then
			echo "INFO [${LOG_PREFIX_PKG_SQUID}] Squid has resumed. Reconfiguring filter." | \
				/usr/bin/logger -p daemon.info -i -t Squid_Alarm
			/etc/rc.filter_configure
			/bin/rm -f /var/run/squid_alarm
		fi
	fi
	sleep $LOOP_SLEEP
done

if [ -f /var/run/squid_alarm ]; then
	/bin/rm -f /var/run/squid_alarm
fi
