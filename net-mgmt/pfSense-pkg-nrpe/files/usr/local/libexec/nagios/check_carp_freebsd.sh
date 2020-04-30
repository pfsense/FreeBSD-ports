#!/bin/sh
# Nagios local plugin for checking CARP status on FreeBSD
# By: Stephane LAPIE <stephane.lapie@asahinet.com>
# 2017/10/18 : Handle multiple VHIDs on a single NIC
# 2017/10/02 : Initial release

cd `dirname $0`
DIR=`pwd`
SCRIPT=`basename $0`

usage()
{
	echo "Usage: $0 [-B|--backup|-M|--master] [NIC]"
	exit 3
}

# Test ifconfig function for debugging on another OS or with other ifconfig output
#ifconfig()
#{
#	TESTFILE=/tmp/testifconfig.txt
#	if [ "$1" = "-l" ] ; then
#		cat $TESTFILE | grep "^[a-z][a-z]*[0-9][0-9]*: flags=" | cut -d : -f 1
#	elif [ ! -z "$1" ] ; then
#		cat $TESTFILE | sed "/^$1: flags=/,/^[a-z][a-z]*[0-9][0-9]*: flags=/ !D" | grep carp
#	else
#		cat $TESTFILE
#	fi
#}

exit_nagios()
{
	case $1 in
		0)
			echo "OK - $2"
			;;
		1)
			echo "WARNING - $2"
			;;
		2)
			echo "CRITICAL - $2"
			;;
		*)
			echo "UNKNOWN - $2"
			;;
	esac
	exit $1
}

###############################################################################
# Check OS

OS=`uname -s`
if ! (echo "$OS" | grep -E "FreeBSD|OpenBSD" >/dev/null) ; then
	echo "UNKNOWN - OS '$OS' not supported by this script"
	exit 3
fi

###############################################################################
# Check if CARP is used

CARP_OUTPUT=`ifconfig | grep "carp:"`
if [ -z "$CARP_OUTPUT" ] ; then
	exit_nagios 3 "No NIC is CARP enabled"
fi

###############################################################################
# Parse arguments

OK_MODE=ADVSKEW
NG_MODE=""

GETOPT_TEMP=`getopt MBh "$@"`
eval set -- "$GETOPT_TEMP"

while true ; do
	case "$1" in
		-M|--master)
			OK_MODE=MASTER;
			NG_MODE=BACKUP;
			shift
			;;
		-B|--backup)
			OK_MODE=BACKUP;
			NG_MODE=MASTER;
			shift
			;;
		-h|--help)
			usage
			;;
		--) shift ; break ;;
		*) echo "Internal error!"; exit 3 ;;
	esac
done

###############################################################################
# Check NIC list

NIC_LIST=`ifconfig -l`
NICS="$@"

# If no NIC was specified only keep the CARP enabled ones
if [ -z "$NICS" ] ; then
	for nic in $NIC_LIST ; do
		if (ifconfig $nic | grep "carp:" >/dev/null) ; then
			NICS="$NICS $nic"
		fi
	done
fi

return_code=0 # Default to OK
return_msg="CARP Status :"
# Check status of each CARP enabled NIC
for nic in $NICS ; do
	# carp: MASTER vhid 232 advbase 1 advskew 0
	# carp: BACKUP vhid 232 advbase 1 advskew 100
	carp_output=`ifconfig $nic | grep "carp:" | tr -d '\t'`
	# 232
	carp_vhids=`echo "$carp_output" | sed 's/^carp: [^ ][^ ]* vhid \([0-9][0-9]*\) .*/\1/'`

	for carp_vhid in $carp_vhids ; do
		# MASTER
		# BACKUP
		carp_vhid_output=`echo "$carp_output" | grep "carp: [^ ][^ ]* vhid $carp_vhid "`
		carp_status=`echo "$carp_vhid_output" | sed 's/^carp: //;s/ .*//'`

		if [ -z "$carp_status" ] ; then # NIC was not CARP enabled ?
			return_msg="${return_msg} ${nic} is not CARP enabled"
			if [ "$return_code" -lt 3 ] ; then return_code=3 ; fi
		else
			# Handle the case of several VHIDs on the same NIC, by taking the first value
			# This hinges on the fact that a MASTER *MUST* have advskew == 0
			carp_advskew=`echo "$carp_vhid_output" | sed 's/.* advskew \([0-9][0-9]*\)/\1/' | head -1`
			# advskew 0 -> should be MASTER
			# advskew > 0 -> should be BACKUP

			return_msg="${return_msg} ${nic} (vhid $carp_vhid) is ${carp_status}"
			case $OK_MODE in
				MASTER|BACKUP)
					if [ "$carp_status" == "$OK_MODE" ] ; then
						return_msg="${return_msg} (OK)"
					elif [ "$carp_status" == "$NG_MODE" ] ; then
						return_msg="${return_msg} (CRITICAL)"
						if [ "$return_code" -lt 2 ] ; then return_code=2 ; fi
					else
						return_msg="${return_msg} (UNKNOWN)"
						if [ "$return_code" -lt 3 ] ; then return_code=3 ; fi
					fi
					;;
				ADVSKEW)
					if [ "$carp_advskew" -eq 0 ] ; then # advskew > 0, should be BACKUP
						if [ "$carp_status" = "MASTER" ] ; then
							return_msg="${return_msg} (OK)"
						elif [ "$carp_status" = "BACKUP" ] ; then
							return_msg="${return_msg} (CRITICAL)"
							if [ "$return_code" -lt 2 ] ; then return_code=2 ; fi
						else
							return_msg="${return_msg} (UNKNOWN)"
							if [ "$return_code" -lt 3 ] ; then return_code=3 ; fi
						fi
					else
						if [ "$carp_status" = "BACKUP" ] ; then
							return_msg="${return_msg} (OK)"
						elif [ "$carp_status" = "MASTER" ] ; then
							return_msg="${return_msg} (CRITICAL)"
							if [ "$return_code" -lt 2 ] ; then return_code=2 ; fi
						else
							return_msg="${return_msg} (UNKNOWN)"
							if [ "$return_code" -lt 3 ] ; then return_code=3 ; fi
						fi
					fi
					;;
			esac
		fi
	done
	return_msg="${return_msg};"
done

exit_nagios "$return_code" "$return_msg"
