#!/bin/sh
# script_AWS_ME.sh - By BBcan177@gmail.com - 03-20-2022
# Pre-Script to collect Amazon AWS Region (Middle East)
# Copyright (c) 2015-2022 BBcan177@gmail.com
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License Version 2 as
# published by the Free Software Foundation.  You may not use, modify or
# distribute this program under any other version of the GNU General
# Public License.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

# Randomize temporary variables
rvar="$(/usr/bin/jot -r 1 1000 100000)"

tempfile=/tmp/pfbtemp1_$rvar
alias="${1}"
prefix="${2}"

if [ "${prefix}" == '_v4' ]; then
	cat "${alias}" | jq -r '.prefixes[] | select(.region | startswith("me-")) .ip_prefix' | iprange > "${tempfile}"
else
	cat "${alias}" | jq -r '.ipv6_prefixes[] | select(.region | startswith("me-")) .ipv6_prefix' > "${tempfile}"
fi

if [ -s "${tempfile}" ]; then
	mv -f "${tempfile}" "${alias}"
else
	rm -f "${tempfile}"
	echo "Failed to process pre-script"
fi
exit
