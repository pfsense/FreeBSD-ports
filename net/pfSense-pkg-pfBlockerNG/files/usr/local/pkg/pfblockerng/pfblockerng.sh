#!/bin/sh
# pfBlockerNG IP Reputation Script - By BBcan177@gmail.com - 04-12-14
# Copyright (c) 2015-2016 BBcan177@gmail.com
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

now=$(/bin/date +%m/%d/%y' '%T)

# Application Locations
pathgrepcidr="/usr/local/bin/grepcidr"
pathaggregate="/usr/local/bin/aggregate"
pathmwhois="/usr/local/bin/mwhois"
pathgeoip="/usr/local/bin/geoiplookup"
pathgunzip=/usr/bin/gunzip
pathhost=/usr/bin/host
pathtar=/usr/bin/tar
pathpfctl=/sbin/pfctl

# Script Arguments
alias="${2}"
max="${3}"
dedup="${4}"
cc="$(echo ${5} | sed 's/,/, /g')"
ccwhite="$(echo ${6} | tr '[A-Z]' '[a-z]')"
ccblack="$(echo ${7} | tr '[A-Z]' '[a-z]')"
etblock="$(echo ${8} | sed 's/,/, /g')"
etmatch="$(echo ${9} | sed 's/,/, /g')"

# File Locations
aliasarchive="/usr/local/etc/aliastables.tar.bz2"
pathgeoipdat="/usr/local/share/GeoIP/GeoIP.dat"
pfbsuppression=/var/db/pfblockerng/pfbsuppression.txt
pfbalexa=/var/db/pfblockerng/pfbalexawhitelist.txt
masterfile=/var/db/pfblockerng/masterfile
mastercat=/var/db/pfblockerng/mastercat
geoiplog=/var/log/pfblockerng/geoip.log
errorlog=/var/log/pfblockerng/error.log
domainmaster=/tmp/domainmaster

# Folder Locations
etdir=/var/db/pfblockerng/ET
tmpxlsx=/tmp/xlsx/

pfbdb=/var/db/pfblockerng/
pfbdeny=/var/db/pfblockerng/deny/
pfborig=/var/db/pfblockerng/original/
pfbmatch=/var/db/pfblockerng/match/
pfbpermit=/var/db/pfblockerng/permit/
pfbnative=/var/db/pfblockerng/native/
pfsensealias=/var/db/aliastables/
pfbdomain=/var/db/pfblockerng/dnsbl/

# Store 'Match' d-dedups in matchdedup.txt file
matchdedup=matchdedup.txt

# Randomize temporary variables
rvar="$(/usr/bin/jot -r 1 1000 100000)"

tempfile=/tmp/pfbtemp1_$rvar
tempfile2=/tmp/pfbtemp2_$rvar
dupfile=/tmp/pfbtemp3_$rvar
dedupfile=/tmp/pfbtemp4_$rvar
addfile=/tmp/pfbtemp5_$rvar
syncfile=/tmp/pfbtemp6_$rvar
matchfile=/tmp/pfbtemp7_$rvar
tempmatchfile=/tmp/pfbtemp8_$rvar

PLATFORM="$(cat /etc/platform)"
USE_MFS_TMPVAR="$(/usr/bin/grep -c use_mfs_tmpvar /cf/conf/config.xml)"
DISK_NAME="$(/bin/df /var/db/rrd | /usr/bin/tail -1 | /usr/bin/awk '{print $1;}')"
DISK_TYPE="$(/usr/bin/basename ${DISK_NAME} | /usr/bin/cut -c1-2)"

if [ "${PLATFORM}" != 'pfSense' ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = 'md' ]; then
	/etc/rc.conf_mount_rw > /dev/null 2>&1
fi

if [ ! -d "${pfbdb}" ]; then mkdir "${pfbdb}"; fi
if [ ! -d "${pfsensealias}" ]; then mkdir "${pfsensealias}"; fi
if [ ! -d "${pfbmatch}" ]; then mkdir "${pfbmatch}"; fi
if [ ! -d "${etdir}" ]; then mkdir "${etdir}"; fi
if [ ! -d "${tmpxlsx}" ]; then mkdir "${tmpxlsx}"; fi

if [ ! -f "${masterfile}" ]; then touch "${masterfile}"; fi
if [ ! -f "${mastercat}" ]; then touch "${mastercat}"; fi


# Exit function to set mount RO if required before exiting.
exitnow() {
	if [ "${PLATFORM}" != 'pfSense' ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = 'md' ]; then
		/etc/rc.conf_mount_ro > /dev/null 2>&1
	fi

	# Remove temp files
	rm -f /tmp/pfbtemp?_"${rvar}"
	exit
}


# Function to restore aliasables from archive on reboot. ( NanoBSD and Ramdisk installations only )
aliastables() {
	if [ "${PLATFORM}" != 'pfSense' ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = 'md' ]; then
		[ -f "${aliasarchive}" ] && cd "${pfsensealias}" && /usr/bin/tar -jxvf "${aliasarchive}"
	fi
}


# Function to write '1.1.1.1' to 'empty' final blocklist files.
emptyfiles() {
	emptyfiles="$(find ${pfbdeny}*.txt -size 0 2>/dev/null)"
	for i in ${emptyfiles}; do
		echo '1.1.1.1' > "${i}";
	done
}


# Function to remove lists from masterfiles and delete associated files.
remove() {
	echo
	for i in ${cc}; do
		header="$(echo ${i%*,})"
		if [ ! -z "${header}" ]; then
			# Make sure that alias exists in masterfile before removal.
			masterchk="$(grep -m1 '${header}[[:space:]]' ${masterfile})"

			if [ ! -z "${masterchk}" ]; then
				# Grep header with a trailing space character
				grep "${header}[[:space:]]" "${masterfile}" > "${tempfile}"
				awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${masterfile}"
			fi

			rm -f "${pfborig}${header}"*; rm -f "${pfbdeny}${header}"*; rm -f "${pfbmatch}${header}"*;
			rm -f "${pfbpermit}${header}"*; rm -f "${pfbnative}${header}"*
			echo "The Following list has been REMOVED [ ${header} ]"
		fi
	done
	cut -d ' ' -f2 "${masterfile}" > "${mastercat}"

	# Delete masterfiles if they are empty
	if [ ! -s "${masterfile}" ]; then
		rm -f "${masterfile}"; rm -f "${mastercat}"
	fi
}


# Function to remove IPs if exists over 253 IPs in a range and replace with a single /24 block. (excl. '0' & '255')
process255() {
	> "${dedupfile}"
	data255="$(cut -d '.' -f 1-3 ${pfbdeny}${alias}.txt | awk '{a[$0]++}END{for(i in a){if(a[i] > 253){print i}}}')"

	if [ ! -z "${data255}" ]; then
		cp "${pfbdeny}${alias}.txt" "${tempfile}"

		for ip in ${data255}; do
			ii="$(echo ^${ip}. | sed 's/\./\\\./g')"
			grep "${ii}" "${tempfile}" >> "${dedupfile}"
		done

		awk 'FNR==NR{a[$0];next}!($0 in a)' "${dedupfile}" "${tempfile}" > "${pfbdeny}${alias}.txt"
		for ip in ${data255}; do echo "${ip}.0/24" >> "${pfbdeny}${alias}.txt"; done
	fi
}

# Process to remove suppressed entries.
suppress() {
	if [ ! -x "${pathgrepcidr}" ]; then
		log="Application [ grepcidr ] Not found. Cannot proceed."
		echo "${log}" | tee -a "${errorlog}"
		exitnow
	fi

	if [ -e "${pfbsuppression}" ] && [ -s "${pfbsuppression}" ]; then
		data="$(cat ${pfbsuppression} | sort | uniq)"

		if [ ! -z "${data}" ] && [ ! -z "${cc}" ]; then
			if [ "${cc}" == 'suppressheader' ]; then
				echo; echo '===[ Suppression Stats ]==================================='; echo
				printf "%-20s %-10s %-10s %-10s\n" 'List' 'Pre' 'Suppress' 'Master'
				echo '-----------------------------------------------------------'
				exitnow
			fi

			alias="$(echo ${cc%|*})"
			pfbfolder="$(echo ${cc#*|})"
			counter=0; > "${dupfile}"

			if [ ! -z "${alias}" ]; then
				countg="$(grep -c ^ ${pfbfolder}${alias}.txt)"
				cp "${pfbfolder}${alias}.txt" "${tempfile}"

				for ip in ${data}; do
					found=''; dcheck='';
					mask="$(echo ${ip##*/})"
					iptrim="$(echo "${ip%.*}")"
					ip="$(echo ${ip%%/*})"
					found="$(grep -m1 ${iptrim}.0/24 ${tempfile})"

					# If a suppression is '/32' and a blocklist has a full '/24' block, execute the following.
					if [ ! -z "${found}" ] && [ "${mask}" -eq 32 ]; then
						echo " Suppression ${alias}: ${iptrim}.0/24"
						octet4="$(echo ${ip##*.})"
						dcheck="$(grep ${iptrim}.0/24 ${dupfile})"

						if [ -z "${dcheck}" ]; then
							echo "${iptrim}.0/24" >> "${dupfile}"
							counter="$((counter + 1))"

							# Add individual IP addresses from range excluding suppressed IP
							for i in $(/usr/bin/jot 255); do
								if [ "${i}" != "${octet4}" ]; then
									echo "${iptrim}.${i}" >> "${tempfile}"
									counter="$((counter + 1))"
								fi
							done
						fi
					fi
				done

				if [ -s "${dupfile}" ]; then
					# Remove '/24' suppressed ranges
					awk 'FNR==NR{a[$0];next}!($0 in a)' "${dupfile}" "${tempfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${tempfile}"
				fi

				# Remove all other suppressions from list
				"${pathgrepcidr}" -vf "${pfbsuppression}" "${tempfile}" > "${pfbfolder}${alias}.txt"

				# Update masterfiles. Don't execute if duplication process is disabled
				if [ "${dedup}" == 'x' ]; then
					# Don't execute if alias doesn't exist in masterfile
					lcheck="$(grep -m1 ${alias} ${masterfile})"

					if [ ! -z "${lcheck}" ]; then
						# Replace masterfile with changes to list.
						grep "${alias}[[:space:]]" "${masterfile}" > "${tempfile}"
						awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"
						mv -f "${tempfile2}" "${masterfile}"
						sed -e 's/^/'$alias' /' "${pfbfolder}${alias}.txt" >> "${masterfile}"
						cut -d ' ' -f2 "${masterfile}" > "${mastercat}"
					fi
				fi

				countk="$(grep -c ^ ${masterfile})"
				countx="$(grep -c ^ ${pfbfolder}${alias}.txt)"
				counto="$((countx - counter))"
				printf "%-20s %-10s %-10s %-10s\n" "${alias}" "${countg}" "${counto}" "${countk}"
			fi
		fi
	fi
}


# Function to optimise CIDRs
cidr_aggregate() {
	if [ ! -x "${pathaggregate}" ]; then
		log="Application [ aggregate ] Not found. Cannot proceed."
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	if [ "${agg_folder}" = true ]; then
		# Use $3 folder path
		pfbfolder="${max}/"
	else
		pfbfolder="${pfbdeny}"
	fi

	counto="$(grep -c ^ ${pfbfolder}${alias}.txt)"
	retval="$(cat "${pfbfolder}${alias}.txt" | "${pathaggregate}" -t -p 32 -m 32 -o 32  2>&1 > ${tempfile})"
	sed 's/\/32//' "${tempfile}" > "${pfbfolder}${alias}.txt"
	countf="$(grep -c ^ ${pfbfolder}${alias}.txt)"

	# Report errors (First two lines are informational only)
	aggstring='aggregate: maximum prefix length permitted will be 32aggregate: prefix length of 32 bits will be used where none specified'
	retval2=$(echo "${retval}" | tr -d '\n\r' | sed "s/${aggstring}//g")
	if [ ! -z "${retval2}" ]; then
		echo "${retval2}"
	fi

	if [ "${counto}" != "${countf}" ]; then
		echo; echo '  Aggregation Stats:'
		echo '  ------------------'
		printf "%-10s %-10s \n" '  Original' 'Final'
		echo '  ------------------'
		printf "%-10s %-10s \n" "  ${counto}" "${countf}"
		echo '  ------------------'
	fi
}


# Function to remove duplicate entries in each list individually.
duplicate() {
	if [ ! -x "${pathgrepcidr}" ]; then
		log="Application [ grepcidr ] Not found. Cannot proceed."
		echo "${log}" | tee -a "${errorlog}"
		exitnow
	fi

	dupcheck=1
	# Check if masterfile is empty
	hcheck="$(grep -cv ^$ ${masterfile})"; if [ "${hcheck}" -eq 0 ]; then dupcheck=0; fi
	# Check if alias exists in masterfile
	lcheck="$(grep -m1 ${alias} ${masterfile})"; if [ -z "${lcheck}" ]; then dupcheck=0; fi
	# Check for single alias in masterfile
	aliaslist="$(cut -d ' ' -f1 ${masterfile} | sort | uniq)"; if [ "${alias}" == "${aliaslist}" ]; then hcheck=0; fi

	# Only execute if 'Alias' exists in masterfile
	if [ "${dupcheck}" -eq 1 ]; then
		# Grep alias with a trailing space character
		grep "${alias}[[:space:]]" "${masterfile}" > "${tempfile}"
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${masterfile}"
		cut -d ' ' -f2 "${masterfile}" > "${mastercat}"
	fi

	# Don't execute when only a single 'Alias' exists in masterfile
	if [ ! "${hcheck}" -eq 0 ]; then
		sort "${pfbdeny}${alias}.txt" | uniq > "${tempfile}"; mv -f "${tempfile}" "${pfbdeny}${alias}.txt"
		"${pathgrepcidr}" -vf "${mastercat}" "${pfbdeny}${alias}.txt" > "${tempfile}"; mv -f "${tempfile}" "${pfbdeny}${alias}.txt"
	fi

	sed -e 's/^/'$alias' /' "${pfbdeny}${alias}.txt" >> "${masterfile}"
	cut -d ' ' -f2 "${masterfile}" > "${mastercat}"

	counto="$(grep -cv '^#\|^$' ${pfborig}${alias}.orig)"
	countm="$(grep -c ${alias} ${masterfile})"
	countf="$(grep -c ^ ${pfbdeny}${alias}.txt)"

	if [ "${countm}" -eq "${countf}" ]; then
		sanity='Pass'
	else
		sanity=' ==> FAILED <== '
	fi

	echo '  ------------------------------'
	printf "%-10s %-10s %-10s\n" '  Original' 'Master' 'Final'
	echo '  ------------------------------'
	printf "%-10s %-10s %-10s %-10s\n" "  ${counto}" "${countm}" "${countf}" " [ ${sanity} ]"
	echo '  -----------------------------------------------------------------'
}


# Function to remove duplicate DNSBL domain names from feeds.
domainduplicate() {
	# Alexa Whitelist variables
	alexa_enable="${max}"

	counto="$(grep -c ^ ${pfbdomain}${alias}.bk)"
	if [ -d "${pfbdomain}" ] && [ "$(ls -A ${pfbdomain}*.txt 2>/dev/null)" ]; then
		sort "${pfbdomain}${alias}.bk" | uniq > "${pfbdomain}${alias}.bk2"
		countu="$(grep -c ^ ${pfbdomain}${alias}.bk2)"
		find "${pfbdomain}"*.txt ! -name "${alias}.txt" | xargs cat > "${domainmaster}"

		# Only execute awk command, if master domain file contains data.
		counta="$(grep -c ^ ${domainmaster})"
		if [ "${counta}" -gt 0 ]; then
			awk 'FNR==NR{a[$0];next}!($0 in a)' "${domainmaster}" "${pfbdomain}${alias}.bk2" > "${pfbdomain}${alias}.bk"
		fi

		rm -f "${domainmaster}"; rm -f "${pfbdomain}${alias}.bk2"
		countf="$(grep -c ^ ${pfbdomain}${alias}.bk)"
		countd="$((countu - countf))"
	else
		sort "${pfbdomain}${alias}.bk" | uniq > "${pfbdomain}${alias}.bk2" && mv -f "${pfbdomain}${alias}.bk2" "${pfbdomain}${alias}.bk"
		countf="$(grep -c ^ ${pfbdomain}${alias}.bk)"
		countd=0; countu="${counto}"
	fi

	if [ "${alexa_enable}" == 'on' ]; then
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${pfbalexa}" "${pfbdomain}${alias}.bk" > "${pfbdomain}${alias}.bk2"
		countw="$(grep -c ^ ${pfbdomain}${alias}.bk2)"
		counta="$((countf - countw))"

		if [ "${counta}" -gt 0 ]; then
			data="$(awk 'FNR==NR{a[$0];next}!($0 in a)' ${pfbdomain}${alias}.bk2 ${pfbdomain}${alias}.bk | \
				cut -d '"' -f2 | cut -d ' ' -f1 | sort | uniq | tr '\n' '|')"
			echo; echo; echo "  Alexa Whitelist: ${data}"
			mv -f "${pfbdomain}${alias}.bk2" "${pfbdomain}${alias}.bk"
			countf="$((countw))"
		else
			rm -f "${pfbdomain}${alias}.bk2"
		fi
	else
		counta='-'
	fi

	echo; echo '  ------------------------------------------------'
	printf "%-10s %-10s %-10s %-10s %-10s\n" '  Original' 'Unique' '# Dups' 'Alexa' 'Final'
	echo '  ------------------------------------------------'
	printf "%-10s %-10s %-10s %-10s %-10s\n" "  ${counto}" "${countu}" "${countd}" "${counta}" "${countf}"
	echo '  ------------------------------------------------'
}


# Function to convert Domains/ASs to its respective IP addresses
whoisconvert() {
	if [ ! -x "${pathmwhois}" ]; then
		log="Application [ mwhois ] Not found. Cannot proceed."
		echo "${log}" | tee -a "${errorlog}"
		exitnow
	fi

	vtype="${max}"
	custom_list="$(echo ${dedup} | tr ',' ' ')"
	rm -f "${pfborig}${alias}.orig"

	if [ "${vtype}" == '_v4' ]; then
		_type=A
		_route=route
		_opt=gAS
	else
		_type=AAAA
		_route=route6
		_opt=6AS
	fi

	for host in ${custom_list}; do
		# Determine if host is a Domain or an AS
		host_check="$(echo ${host} | grep '\.')"
		if [ ! -z "${host_check}" ]; then
			echo "### Domain: ${host} ###" >> "${pfborig}${alias}.orig"
			${pathhost} -t ${_type} ${host} | sed 's/^.* //' >> "${pfborig}${alias}.orig"
		else
			asn="$(echo ${host} | tr -d 'AaSs')"
			echo "### AS${asn}: ${host} ###" >> "${pfborig}${alias}.orig"
			"${pathmwhois}" -h whois.radb.net \!"${_opt}${asn}" | tail -n +2 | tr -d '\nC' | tr ' ' '\n' >> "${pfborig}${alias}.orig"
		fi

		echo >> "${pfborig}${alias}.orig"
	done
}


# Function to check for Reputation application dependencies.
reputation_depends() {
	if [ ! -x "${pathgeoip}" ]; then
		log="Application [ GeoIP ] Not found, cannot proceed. [ ${now} ]"
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	# Download MaxMind GeoIP.dat on first install.
	if [ ! -f "${pathgeoipdat}" ]; then
		echo "Downloading [ MaxMind GeoIP.dat ] [ ${now} ]" >> "${geoiplog}"
		/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php bu
	fi

	# Exit if GeoIP.dat is not found
	if [ ! -f "${pathgeoipdat}" ]; then
		log="Database GeoIP [ GeoIP.Dat ] not found. Reputation function terminated."
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	# Clear variables and tempfiles
	rm -f /tmp/pfbtemp?_"${rvar}"
	count=0; countb=0; countm=0; counts=0; countr=0
}


# Reputation function to condense an IP range if a 'Max' amount of IP addresses are found in a /24 range per individual list.
reputation_max() {
	sort "${pfbdeny}${alias}.txt" | uniq > "${tempfile}"
	data="$(cut -d '.' -f 1-3 ${tempfile} | awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}')"

	# Classify repeat offenders by Country code
	if [ ! -z "${data}" ]; then
		for ip in ${data}; do
			ccheck="$(${pathgeoip} -f ${pathgeoipdat} ${ip}.1 | cut -c 24-25)"
			case "${cc}" in
				*$ccheck*)
					countr="$((countr + 1))"
					if [ "${ccwhite}" == 'match' ] || [ "${ccblack}" == 'match' ]; then
						echo "${ip}." >> "${matchfile}"
					fi
					;;
				*)
					count="$((count + 1))"
					echo "${ip}." >> "${dupfile}"
					;;
			esac
		done
	else
		countr=0; count=0
	fi

	# Collect match file details
	if [ -s "${matchfile}" ] && [ "${dedup}" != 'on' ] && [ "${ccwhite}" == 'match' ]; then
		mon="$(sed -e 's/^/^/' -e 's/\./\\\./g' ${matchfile})"
		for ip in ${mon}; do
			grep "${ip}" "${tempfile}" >> "${tempfile2}"
		done
		counts="$(grep -c ^ ${tempfile2})"
		if [ "${ccwhite}" == 'match' ]; then
			sed 's/$/0\/24/' "${matchfile}" >> "${tempmatchfile}"
			sed 's/^/\!/' "${tempfile2}" >> "${tempmatchfile}"
		fi
	fi

	# If no matches found remove previous matchoutfile if exists.
	matchoutfile="match${header}.txt"
	if [ ! -s "${tempmatchfile}" ] && [ -f "${matchoutfile}" ]; then rm -r "${matchoutfile}"; fi
	# Move match file to the match folder by individual blocklist name
	if [ -s "${tempmatchfile}" ]; then mv -f "${tempmatchfile}" "${pfbmatch}${matchoutfile}"; fi

	# Find repeat offenders in each individual blocklist outfile
	if [ -s "${dupfile}" ]; then
		> "${tempfile2}"
		dup="$(sed -e 's/^/^/' -e 's/\./\\\./g' ${dupfile})"
		for ip in ${dup}; do
			grep "${ip}" "${tempfile}" >> "${tempfile2}"
		done
		countb="$(grep -c ^ ${tempfile2})"

		if [ "${ccblack}" == 'block' ]; then
			awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile2}" "${tempfile}" > "${pfbdeny}${alias}.txt"
			sed 's/$/0\/24/' "${dupfile}" >> "${pfbdeny}${alias}.txt"
		elif [ "${ccblack}" == 'match' ]; then
			sed 's/$/0\/24/' "${dupfile}" >> "${tempmatchfile}"
			sed 's/^/\!/' "${tempfile2}" >> "${tempmatchfile}"
		else
			:
		fi
	fi

	if [ "${count}" -gt 0 ]; then
		echo; echo "  Reputation (Max=${max}) - Range(s)"
		cat "${dupfile}" | tr '\n' '|'; echo
		sort "${pfbdeny}${alias}.txt" | uniq > "${tempfile}"; mv -f "${tempfile}" "${pfbdeny}${alias}.txt"
	fi

	if [ "${count}" -gt 0 ] || [ "${countr}" -gt 0 ]; then
		echo; echo '  Reputation -Max Stats'
		echo '  ------------------------------'
		printf "%-17s %-10s\n" '  Blacklisted' 'Match'
		printf "%-8s %-8s %-8s %-8s\n" '  Ranges' 'IPs' 'Ranges' 'IPs'
		echo '  ------------------------------'
		printf "%-8s %-8s %-8s %-8s\n" "  ${count}" "${countb}" "${countr}" "${counts}"
		echo
	fi
}


# Reputation function 'dMax' utilizing MaxMind GeoIP Country code.
reputation_dmax() {
	echo; echo '===[ Reputation - dMax ]======================================'
	echo; echo "  Querying for repeat offenders ( dMax=${max} ) [ ${now} ]"
	data="$(find ${pfbdeny}*.txt ! -name pfB*.txt ! -name *_v6.txt -type f | xargs cut -d '.' -f 1-3 | \
		awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v '^1\.1\.1')"

	# Classify repeat offenders by Country code
	if [ ! -z "${data}" ]; then
		echo '  Classifying repeat offenders by GeoIP'
		for ip in ${data}; do
			ccheck="$(${pathgeoip} -f ${pathgeoipdat} ${ip}.1 | cut -c 24-25)"
			case "${cc}" in
				*$ccheck*)
					countr="$((countr + 1))"
					if [ "${ccwhite}" == 'match' ] || [ "${ccblack}" == 'match' ]; then
						echo "${ip}." >> "${matchfile}"
					fi
					;;
				*)
					count="$((count + 1))"
					echo "${ip}." >> "${dupfile}"
					;;
			esac
		done
	else
		countr=0; count=0
	fi

	if [ "${ccwhite}" == 'match' ] && [ -s "${matchfile}" ]; then
		echo '  Processing [ Match ] IPs'
		match="$(sed -e 's/^/^/' -e 's/\./\\\./g' ${matchfile})"

		for mfile in ${match}; do
			grep "${mfile}" "${pfbdeny}"*.txt >> "${tempfile}"
		done

		sed 's/$/0\/24/' "${matchfile}" >> "${tempmatchfile}"
		sed -e 's/.*://' -e 's/^/\!/' "${tempfile}" >> "${tempmatchfile}"
		mv -f "${tempmatchfile}" "${pfbmatch}${matchdedup}"
		countm="$(grep -c ^ ${tempfile})"
		counts="$((countm + counts))"
	fi

	# Find repeat offenders in each individual blocklist outfile
	if [ "${count}" -gt 0 ]; then
		echo '  Processing [ Block ] IPs'
		dup="$(cat ${dupfile})"

		for ip in ${dup}; do
			runonce=0; ii="$(echo ^${ip} | sed 's/\./\\\./g')"
			list="$(find ${pfbdeny}*.txt ! -name pfB*.txt ! -name *_v6.txt -type f | xargs grep -al ${ii})"

			for blfile in ${list}; do
				header="$(echo ${blfile##*/} | cut -d '.' -f1)"
				grep "${ii}" "${blfile}" > "${tempfile}"

				if [ "${ccblack}" == 'block' ]; then
					awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${blfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${blfile}"
					if [ "${runonce}" -eq 0 ]; then
						echo "${ip}0/24" >> "${blfile}"
						echo "${header}" "${ip}" >> "${dedupfile}"
						echo "${header}" "${ip}0/24" >> "${addfile}"
						runonce=1
					else
						echo "${header}" "${ip}" >> "${dedupfile}"
					fi
				else
					if [ "${runonce}" -eq 0 ]; then
						matchoutfile="match${header}.txt"
						echo "${ip}0/24" >> "${pfbmatch}${matchoutfile}"
						sed 's/^/\!/' "${tempfile}" >> "${pfbmatch}${matchoutfile}"
						countm="$(grep -c ^ ${pfbmatch}${matchoutfile})"
						counts="$((countm + counts))"
						runonce=1
					fi
				fi
			done
		done

		# Remove repeat offenders in masterfiles
		echo '  Removing   [ Block ] IPs'
		> "${tempfile}"; > "${tempfile2}"
		sed 's/\./\\\./g' "${dedupfile}" > "${tempfile2}"
		while IFS=' ' read -r ips; do grep "${ips}" "${masterfile}" >> "${tempfile}"; done < "${tempfile2}"
		countb="$(grep -c ^ ${tempfile})"
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${masterfile}"
		cat "${addfile}" >> "${masterfile}"
		cut -d ' ' -f2 "${masterfile}" > "${mastercat}"

		echo; echo '  Removed the following IP ranges:'
		sed -e 's/^.* //' -e 's/0\/24//' "${addfile}" | tr '\n' '|'; echo
	fi

	if [ "${count}" -gt 0 ] || [ "${countr}" -gt 0 ]; then
		echo; echo '  Reputation - dMax Stats'
		echo '  ------------------------------'
		printf "%-17s %-10s\n" '  Blacklisted' 'Match'
		printf "%-8s %-8s %-8s %-8s\n" '  Ranges' 'IPs' 'Ranges' 'IPs'
		echo '  ------------------------------'
		printf "%-8s %-8s %-8s %-8s\n" "  ${count}" "${countb}" "${countr}" "${counts}"

		emptyfiles # Call emptyfiles function
	else
		echo '  Reputation -dMax ( None )'
	fi
}


# Reputation function 'pMax'. (No Country code exclusions)
reputation_pmax(){
	echo; echo; echo '===[ Reputation - pMax ]======================================'
	echo; echo "  Querying for repeat offenders ( pMax=${max} ) [ ${now} ]"
	data="$(find ${pfbdeny}*.txt ! -name pfB*.txt ! -name *_v6.txt -type f | xargs cut -d '.' -f 1-3 |
		awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v '^1\.1\.1')"

	if [ ! -z "${data}" ]; then
		# Find repeat offenders in each individual blocklist outfile
		echo '  Processing [ Block ] IPs'
		count=0

		for ip in ${data}; do
			count="$((count + 1))"
			runonce=0; ii="$(echo ^${ip}. | sed 's/\./\\\./g')"
			list="$(find ${pfbdeny}*.txt ! -name pfB*.txt ! -name *_v6.txt -type f | xargs grep -al ${ii})"

			for blfile in ${list}; do
				header="$(echo ${blfile##*/} | cut -d '.' -f1)"
				grep "${ii}" "${blfile}" > "${tempfile}"
				awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${blfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${blfile}"

				if [ "${runonce}" -eq 0 ]; then
					echo "${ip}.0/24" >> "${blfile}"
					echo "${header}" "${ip}." >> "${dedupfile}"
					echo "${header}" "${ip}.0/24" >> "${addfile}"
					runonce=1
				else
					echo "${header}" "${ip}." >> "${dedupfile}"
				fi
			done
		done

		# Remove repeat offenders in masterfile
		echo '  Removing   [ Block ] IPs'
		> "${tempfile}"; > "${tempfile2}"
		sed 's/\./\\\./g' "${dedupfile}" > "${tempfile2}"
		while IFS=' ' read -r ips; do grep "${ips}" "${masterfile}" >> "${tempfile}"; done < "${tempfile2}"
		countb="$(grep -c ^ ${tempfile})"
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${masterfile}"
		cat "${addfile}" >> "${masterfile}"
		cut -d ' ' -f2 "${masterfile}" > "${mastercat}"

		echo; echo '  Removed the following IP ranges:'
		sed -e 's/^.* //' -e 's/0\/24//' "${addfile}" | tr '\n' '|'; echo

		echo; echo '  Reputation - pMax Stats'
		echo '  ----------------'
		printf "%-8s %-8s\n" '  Ranges' 'IPs'
		echo '  ----------------'
		printf "%-8s %-8s\n" "  ${count}" "${countb}"

		emptyfiles # Call emptyfiles function
	else
		echo '  Reputation -pMax ( None )'
	fi
}


# Function to split ET Pro IPREP into category files and compile selected blocked categories into outfile.
processet() {
	if [ -s "${pfborig}${alias}.orig" ]; then
		# Remove previous ET IPRep files
		[ -d "${etdir}" ] && [ "$(ls -A ${etdir})" ] && rm -r "${etdir}/ET_"*
		> "${tempfile}"; > "${tempfile2}"

		# ET CSV format (IP, Category, Score)
		echo; echo; echo 'Compiling ET IPREP IQRisk based upon user selected categories'
		while IFS=',' read i j k; do
			# Some ET categories are not in use (For future use)
			case "${j}" in
				1)  echo "${i}" >> "${etdir}/ET_Cnc.txt";;
				2)  echo "${i}" >> "${etdir}/ET_Bot.txt";;
				3)  echo "${i}" >> "${etdir}/ET_Spam.txt";;
				4)  echo "${i}" >> "${etdir}/ET_Drop.txt";;
				5)  echo "${i}" >> "${etdir}/ET_Spywarecnc.txt";;
				6)  echo "${i}" >> "${etdir}/ET_Onlinegaming.txt";;
				7)  echo "${i}" >> "${etdir}/ET_Drivebysrc.txt";;
				8)  echo "${i}" >> "${etdir}/ET_Cat8.txt";;
				9)  echo "${i}" >> "${etdir}/ET_Chatserver.txt";;
				10) echo "${i}" >> "${etdir}/ET_Tornode.txt";;
				11) echo "${i}" >> "${etdir}/ET_Cat11.txt";;
				12) echo "${i}" >> "${etdir}/ET_Cat12.txt";;
				13) echo "${i}" >> "${etdir}/ET_Compromised.txt";;
				14) echo "${i}" >> "${etdir}/ET_Cat14.txt";;
				15) echo "${i}" >> "${etdir}/ET_P2P.txt";;
				16) echo "${i}" >> "${etdir}/ET_Proxy.txt";;
				17) echo "${i}" >> "${etdir}/ET_Ipcheck.txt";;
				18) echo "${i}" >> "$[etdir}/ET_Cat18.txt";;
				19) echo "${i}" >> "${etdir}/ET_Utility.txt";;
				20) echo "${i}" >> "${etdir}/ET_DDos.txt";;
				21) echo "${i}" >> "${etdir}/ET_Scanner.txt";;
				22) echo "${i}" >> "${etdir}/ET_Cat22.txt";;
				23) echo "${i}" >> "${etdir}/ET_Brute.txt";;
				24) echo "${i}" >> "${etdir}/ET_Fakeav.txt";;
				25) echo "${i}" >> "${etdir}/ET_Dyndns.txt";;
				26) echo "${i}" >> "${etdir}/ET_Undesireable.txt";;
				27) echo "${i}" >> "${etdir}/ET_Abusedtld.txt";;
				28) echo "${i}" >> "${etdir}/ET_Selfsignedssl.txt";;
				29) echo "${i}" >> "${etdir}/ET_Blackhole.txt";;
				30) echo "${i}" >> "${etdir}/ET_RAS.txt";;
				31) echo "${i}" >> "${etdir}/ET_P2Pcnc.txt";;
				32) echo "${i}" >> "${etdir}/ET_Sharedhosting.txt";;
				33) echo "${i}" >> "${etdir}/ET_Parking.txt";;
				34) echo "${i}" >> "${etdir}/ET_VPN.txt";;
				35) echo "${i}" >> "${etdir}/ET_Exesource.txt";;
				36) echo "${i}" >> "${etdir}/ET_Cat36.txt";;
				37) echo "${i}" >> "${etdir}/ET_Mobilecnc.txt";;
				38) echo "${i}" >> "${etdir}/ET_Mobilespyware.txt";;
				39) echo "${i}" >> "${etdir}/ET_Skypenode.txt";;
				40) echo "${i}" >> "${etdir}/ET_Bitcoin.txt";;
				41) echo "${i}" >> "${etdir}/ET_DDosattack.txt";;
				*)  echo "${i}" >> "${etdir}/ET_Unknown.txt";;
			esac
		done < "${pfborig}${alias}.orig"
		data="$(ls ${etdir} | sed 's/\.txt//')"
		printf "%-10s %-25s\n" '  Action' 'Category'
		echo '-------------------------------------------'

		for list in ${data}; do
			case "${etblock}" in
				*$list*)
					printf "%-10s %-25s\n" '  Block: ' "${list}"
					cat "${etdir}/${list}.txt" >> "${tempfile}"
					;;
			esac
			case "${etmatch}" in
				*$list*)
					printf "%-10s %-25s\n" '  Match: ' "${list}"
					cat "${etdir}/${list}.txt" >> "${tempfile2}"
					;;
			esac
		done
		echo '-------------------------------------------'

		if [ -f "${tempfile}" ]; then mv -f "${tempfile}" "${pfborig}${alias}.orig"; fi
		if [ "${etmatch}" != 'x' ]; then mv -f "${tempfile2}" "${pfbmatch}/ETMatch.txt"; fi
		counto="$(cat ${etdir}/ET_* | grep -cv '^#\|^$')"; countf="$(grep -cv '^1\.1\.1\.1$' ${pfborig}${alias}.orig)"
		echo; echo "All ET Folder count [ ${counto} ]  Final count [ ${countf} ]"
	else
		echo; echo 'No ET .orig File Found!'
	fi
}


# Function to extract IP addresses from XLSX files.
processxlsx() {
	if [ ! -x "${pathtar}" ]; then
		log='Application [ TAR ] Not found, cannot proceed.'
		echo "${log}" | tee -a "${errorlog}"
		exitnow
	fi

	if [ -s "${pfborig}${alias}.raw" ]; then
		"${pathtar}" -xf "${pfborig}${alias}.raw" -C "${tmpxlsx}"
		"${pathtar}" -xOf "${tmpxlsx}"*.[xX][lL][sS][xX] "xl/sharedStrings.xml" | \
			grep -aoEw "(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)" | sort | uniq > "${pfborig}${alias}.orig"
		rm -r "${tmpxlsx}"*

		countf="$(grep -cv '^1\.1\.1\.1$' ${pfborig}${alias}.orig)"
		echo; echo "Final count [ ${countf} ]"
	else
		echo 'XLSX download file missing'
		echo " [ ${alias} ] XLSX download file missing [ ${now} ]" >> "${errorlog}"
	fi
}


# Function to report final pfBlockerNG statistics.
closingprocess() {
	counto=0
	echo; echo '===[ FINAL Processing ]====================================='; echo
	if [ -d "${pfborig}" ] && [ "$(ls -A ${pfborig})" ]; then
		counto="$(find ${pfborig}*.orig 2>/dev/null | xargs cat | grep -cv '^#\|^$')"
	fi

	# Execute when 'de-duplication' is enabled
	if [ "${alias}" == 'on' ]; then
		sort -o "${masterfile}" "${masterfile}"
		sort -t . -k 1,1n -k 2,2n -k 3,3n -k 4,4n "${mastercat}" > "${tempfile}"; mv -f "${tempfile}" "${mastercat}"

		echo "   [ Original IP count   ]  [ ${counto} ]"
		countm="$(grep -c ^ ${masterfile})"
		echo; echo "   [ Final IP Count  ]  [ ${countm} ]"; echo

		s1="$(grep -cv '1\.1\.1\.1$' ${masterfile})"
		s2="$(find ${pfbdeny}*.txt ! -name *_v6.txt -type f 2>/dev/null | xargs cat | grep -cv '^1\.1\.1\.1$')"
		s3="$(sort ${mastercat} | uniq -d | tail -30)"
		s4="$(find ${pfbdeny}*.txt ! -name *_v6.txt -type f 2>/dev/null | xargs cat | sort | uniq -d | tail -30 | grep -v '^1\.1\.1\.1$')"
	else
		echo "   [ Original IP count   ]  [ ${counto} ]"
	fi

	if [ -d "${pfbpermit}" ] && [ "$(ls -A ${pfbpermit})" ]; then
		echo; echo '===[ Permit List IP Counts ]========================='; echo
		wc -l "${pfbpermit}"*.txt 2>/dev/null | sort -n -r
	fi
	if [ -d "${pfbmatch}" ] && [ "$(ls -A ${pfbmatch})" ]; then
		echo; echo '===[ Match List IP Counts ]=========================='; echo
		wc -l "${pfbmatch}"*.txt 2>/dev/null | sort -n -r
	fi
	if [ -d "${pfbdeny}" ] && [ "$(ls -A ${pfbdeny})" ]; then
		echo; echo '===[ Deny List IP Counts ]==========================='; echo
		wc -l "${pfbdeny}"*.txt 2>/dev/null | sort -n -r
	fi
	if [ -d "${pfbnative}" ] && [ "$(ls -A ${pfbnative})" ]; then
		echo; echo '===[ Native List IP Counts ] ==================================='; echo
		wc -l "${pfbnative}"*.txt 2>/dev/null | sort -n -r
	fi
	if [ -d "${pfbdeny}" ] && [ "$(ls -A ${pfbdeny})" ]; then
		emptylists="$(grep '^1\.1\.1\.1$' ${pfbdeny}*.txt | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:1.1.1.1/ /')"
		if [ ! -z "${emptylists}" ]; then
			echo; echo '====================[ Empty Lists w/1.1.1.1 ]=================='; echo
			for list in ${emptylists}; do
				echo "${list}"
			done
		fi
	fi
	if [ -d "${pfbdomain}" ] && [ "$(ls -A ${pfbdomain})" ]; then
		echo; echo '===[ DNSBL Domain/IP Counts ] ==================================='; echo
		wc -l "${pfbdomain}"* 2>/dev/null | sort -n -r
	fi
	if [ -d "${pfborig}" ] && [ "$(ls -A ${pfborig})" ]; then
		echo; echo '====================[ Last Updated List Summary ]=============='; echo
		ls -lahtr "${pfborig}"*.orig | sed -e 's/\/.*\// /' -e 's/.orig//' | awk -v OFS='\t' '{print $6" "$7,$8,$9}'
	fi

	# Execute when 'de-duplication' is enabled
	if [ "${alias}" == 'on' ]; then
		echo '==============================================================='; echo
		if [ "${s1} == ${s2}" ]; then
			echo 'Database Sanity check [  PASSED  ]'
		else
			echo 'Database Sanity check [  FAILED  ] ** These two counts should match! **'
			echo '------------'
			echo "Masterfile Count    [ ${s1} ]"
			echo "Deny folder Count   [ ${s2} ]"; echo
			echo 'Duplication sanity check (Pass=No IPs reported)'
		fi
		echo '------------------------'
		echo 'Masterfile/Deny folder uniq check'
		if [ ! -z "${s3}" ]; then echo "${s3}"; fi
		echo 'Deny folder/Masterfile uniq check'
		if [ ! -z "${s4}" ]; then echo "${s4}"; fi
		echo; echo 'Sync check (Pass=No IPs reported)'
		echo '----------'
	fi

	echo; echo 'IPv4 alias tables IP count'; echo '-----------------------------'
	find "${pfsensealias}"pfB_*.txt ! -name "*_v6.txt" -type f 2>/dev/null | xargs cat | grep -c ^

	echo; echo 'IPv6 alias tables IP count'; echo '-----------------------------'
	find "${pfsensealias}"pfB_*.txt -name "*_v6.txt" -type f 2>/dev/null | xargs cat | grep -c ^

	echo; echo 'Alias table IP Counts'; echo '-----------------------------'
	wc -l "${pfsensealias}"pfB_*.txt 2>/dev/null | sort -n -r

	echo; echo 'pfSense Table Stats'; echo '-------------------'
	"${pathpfctl}" -s memory | grep 'table-entries'
	pfctlcount="$(${pathpfctl} -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}')"
	echo "Table Usage Count         ${pfctlcount}"
}

# Call appropriate processes using script argument $1.
case "${1}" in
	_*)
		if [ "$(echo ${1} | grep -c '_255')" -gt 0 ]; then process255; fi
		if [ "$(echo ${1} | grep -c '_agg')" -gt 0 ]; then cidr_aggregate; fi
		if [ "$(echo ${1} | grep -c '_rep')" -gt 0 ]; then reputation_depends; reputation_max; fi
		if [ "$(echo ${1} | grep -c '_dup')" -gt 0 ]; then duplicate; fi
		;;
	continent)
		duplicate
		;;
	domainduplicate)
		domainduplicate
		;;
	cidr_aggregate)
		agg_folder=true
		cidr_aggregate
		;;
	whoisconvert)
		whoisconvert
		;;
	suppress)
		suppress
		;;
	dmax)
		reputation_depends
		reputation_dmax
		;;
	pmax)
		reputation_depends
		reputation_pmax
		;;
	et)
		processet
		;;
	xlsx)
		processxlsx
		;;
	remove)
		remove
		;;
	aliastables)
		aliastables
		;;
	closing)
		emptyfiles
		closingprocess
		;;
	*)
		;;
esac
exitnow
