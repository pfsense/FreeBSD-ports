#!/bin/sh
# pfBlockerNG Shell Function Script - By BBcan177@gmail.com - 04-12-14
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

DEBUG=0

now=$(/bin/date +%m/%d/%y' '%T)

# Application Locations
pathgrepcidr="/usr/local/bin/grepcidr"
pathaggregate="/usr/local/bin/iprange"
pathmwhois="/usr/local/bin/mwhois"
pathgeoip="/usr/local/bin/mmdblookup"
pathcurl="/usr/local/bin/curl"
pathjq="/usr/local/bin/jq"
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
pathgeoipdat="/usr/local/share/GeoIP/GeoLite2-Country.mmdb"
pfbsuppression=/var/db/pfblockerng/pfbsuppression.txt
pfbdnsblsuppression=/var/db/pfblockerng/pfbdnsblsuppression.txt
pfbalexa=/var/db/pfblockerng/pfbalexawhitelist.txt
masterfile=/var/db/pfblockerng/masterfile
mastercat=/var/db/pfblockerng/mastercat
geoiplog=/var/log/pfblockerng/geoip.log
errorlog=/var/log/pfblockerng/error.log
dnsbl_file=/var/unbound/pfb_dnsbl

# Folder Locations
etdir=/var/db/pfblockerng/ET
tmpxlsx=/tmp/xlsx/
dnsbl_tmp=/tmp/DNSBL_TMP/
pfbdb=/var/db/pfblockerng/
pfbdeny=/var/db/pfblockerng/deny/
pfborig=/var/db/pfblockerng/original/
pfbmatch=/var/db/pfblockerng/match/
pfbpermit=/var/db/pfblockerng/permit/
pfbnative=/var/db/pfblockerng/native/
pfsensealias=/var/db/aliastables/
pfbdomain=/var/db/pfblockerng/dnsbl/
pfbdomainorig=/var/db/pfblockerng/dnsblorig/

# Store 'Match' d-dedups in matchdedup.txt file
matchdedup=matchdedup_v4.txt

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
domainmaster=/tmp/pfbtemp9_$rvar
asntemp=/tmp/pfbtemp10_$rvar

dnsbl_tld_remove=/tmp/dnsbl_tld_remove

dnsbl_add=/tmp/dnsbl_add
dnsbl_add_zone=/tmp/dnsbl_add_zone
dnsbl_add_data=/tmp/dnsbl_add_data
dnsbl_remove=/tmp/dnsbl_remove
dnsbl_remove_zone=/tmp/dnsbl_remove_zone
dnsbl_remove_data=/tmp/dnsbl_remove_data

dnsbl_python_data=/var/unbound/pfb_py_data.txt
dnsbl_python_zone=/var/unbound/pfb_py_zone.txt
dnsbl_python_count=/var/unbound/pfb_py_count

ip_placeholder="$(/usr/local/sbin/read_xml_tag.sh string installedpackages/pfblockerngipsettings/config/ip_placeholder)"
if [ -z "${ip_placeholder}" ]; then
	ip_placeholder=127.1.7.7
fi
ip_placeholder2="$(echo ${ip_placeholder} | sed 's/\./\\\./g')"
ip_placeholder3="$(echo ${ip_placeholder} | cut -d '.' -f 1-3)"

USE_MFS_TMPVAR="$(/usr/bin/grep -c use_mfs_tmpvar /cf/conf/config.xml)"
DISK_NAME="$(/bin/df /var/db/rrd | /usr/bin/tail -1 | /usr/bin/awk '{print $1;}')"
DISK_TYPE="$(/usr/bin/basename ${DISK_NAME} | /usr/bin/cut -c1-2)"

if [ ! -d "${pfbdb}" ]; then mkdir "${pfbdb}"; fi
if [ ! -d "${pfsensealias}" ]; then mkdir "${pfsensealias}"; fi
if [ ! -d "${pfbmatch}" ]; then mkdir "${pfbmatch}"; fi
if [ ! -d "${etdir}" ]; then mkdir "${etdir}"; fi
if [ ! -d "${tmpxlsx}" ]; then mkdir "${tmpxlsx}"; fi

if [ ! -f "${masterfile}" ]; then touch "${masterfile}"; fi
if [ ! -f "${mastercat}" ]; then touch "${mastercat}"; fi


# Remove temp files before exiting.
exitnow() {
	rm -f /tmp/pfbtemp*_"${rvar}"
	exit
}


# Function to restore IP aliastables and DNSBL database from archive on reboot. ( Ramdisk installations only )
aliastables() {
	if [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = 'md' ]; then
		if [ ! -d '/var/unbound' ]; then
			mkdir '/var/unbound'
			chown -f unbound:unbound /var/unbound
			chgrp -f unbound /var/unbound
		fi
		[ -f "${aliasarchive}" ] && cd / && /usr/bin/tar -Pxvf "${aliasarchive}"
	fi
}


# Function to write IP Placeholder IP to 'empty' final blocklist files.
emptyfiles() {
	emptyfiles="$(find ${pfbdeny}*.txt -size 0 2>/dev/null)"
	for i in ${emptyfiles}; do
		echo "${ip_placeholder}" > "${i}";
	done
}


# Function to remove lists from masterfiles and delete associated files.
remove() {
	echo; echo
	for i in ${cc}; do
		header="$(echo ${i%*,})"
		if [ ! -z "${header}" ]; then
			# Make sure that alias exists in masterfile before removal.
			query="${header} "
			masterchk="$(grep -m1 ${query} ${masterfile})"

			if [ ! -z "${masterchk}" ]; then
				# Grep header with a trailing space character
				grep "${header}[[:space:]]" "${masterfile}" > "${tempfile}"
				awk 'FNR==NR{a[$0];next}!($0 in a)' "${tempfile}" "${masterfile}" > "${tempfile2}"; mv -f "${tempfile2}" "${masterfile}"
			fi

			rm -f "${pfborig}${header}"*; rm -f "${pfbdeny}${header}"*; rm -f "${pfbmatch}${header}"*
			rm -f "${pfbpermit}${header}"*; rm -f "${pfbnative}${header}"*
			echo "The Following List has been REMOVED [ ${header} ]"
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
						echo " Suppression ${alias}: ${iptrim}.0/24 (Excluding: ${ip}/32)"
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
		log="Application [ iprange ] Not found. Cannot proceed."
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	if [ "${agg_folder}" == true ]; then
		# Use $3 folder path
		pfbfolder="${max}/"
	else
		pfbfolder="${pfbdeny}"
	fi

	counto="$(grep -c ^ ${pfbfolder}${alias}.txt)"
	"${pathaggregate}" "${pfbfolder}${alias}.txt" > "${tempfile}" && mv -f "${tempfile}" "${pfbfolder}${alias}.txt"

	countf="$(grep -c ^ ${pfbfolder}${alias}.txt)"
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

	emptyfiles # Call emptyfiles function
}



# Function for DNSBL (De-Duplication, Whitelist, and Alexa Whitelist)
dnsbl_scrub() {

	counto="$(grep -c ^ ${pfbdomain}${alias}.bk)"
	alexa_enable="${max}"

	# Process De-Duplication
	sort "${pfbdomain}${alias}.bk" | uniq > "${pfbdomain}${alias}.bk2"
	countu="$(grep -c ^ ${pfbdomain}${alias}.bk2)"

	if [ -d "${pfbdomain}" ] && [ "$(ls -A ${pfbdomain}*.txt 2>/dev/null)" ]; then
		find "${pfbdomain}"*.txt ! -name "${alias}.txt" | xargs cat > "${domainmaster}"

		# Only execute awk command, if master domain file contains data.
		query_size="$(grep -c ^ ${domainmaster})"
		if [ "${query_size}" -gt 0 ]; then

			# Unbound blocking mode dedup
			if [ "${dedup}" == '' ]; then
				awk 'FNR==NR{a[$2];next}!($2 in a)' "${domainmaster}" "${pfbdomain}${alias}.bk2" > "${pfbdomain}${alias}.bk"

			# Unbound python blocking mode dedup
			else
				awk -F',' 'FNR==NR{a[$2];next}!($2 in a)' "${domainmaster}" "${pfbdomain}${alias}.bk2" > "${pfbdomain}${alias}.bk"
			fi
		fi

		rm -f "${domainmaster}"
	else
		mv -f "${pfbdomain}${alias}.bk2" "${pfbdomain}${alias}.bk"
	fi

	countf="$(grep -c ^ ${pfbdomain}${alias}.bk)"
	countd="$((countu - countf))"
	rm -f "${pfbdomain}${alias}.bk2"

	# Remove Whitelisted Domains and Sub-Domains, if configured
	if [ -s "${pfbdnsblsuppression}" ] && [ -s "${pfbdomain}${alias}.bk" ]; then
		grep -vF -f "${pfbdnsblsuppression}" "${pfbdomain}${alias}.bk" > "${pfbdomain}${alias}.bk2"
		countx="$(grep -c ^ ${pfbdomain}${alias}.bk2)"
		countw="$((countf - countx))"

		if [ "${countw}" -gt 0 ]; then
			if [ "${dedup}" == '' ]; then
				data="$(awk 'FNR==NR{a[$0];next}!($0 in a)' ${pfbdomain}${alias}.bk2 ${pfbdomain}${alias}.bk | \
					cut -d '"' -f2 | cut -d ' ' -f1 | sort | uniq | tr '\n' '|')"
			else
				data="$(awk 'FNR==NR{a[$0];next}!($0 in a)' ${pfbdomain}${alias}.bk2 ${pfbdomain}${alias}.bk | \
					cut -d ',' -f2 | sort | uniq | tr '\n' '|')"
			fi

			if [ -z "${data}" ]; then
				if [ "${dedup}" == '' ]; then
					data="$(cut -d '"' -f2 ${pfbdomain}${alias}.bk | cut -d ' ' -f1 | sort | uniq | tr '\n' '|')"
				else
					data="$(cut -d ',' -f2 ${pfbdomain}${alias}.bk | sort | uniq | tr '\n' '|')"
				fi
			fi

			echo "  Whitelist: ${data}"
			mv -f "${pfbdomain}${alias}.bk2" "${pfbdomain}${alias}.bk"
		fi
	else
		countw=0
	fi

	# Process TOP1M Whitelist
	if [ "${alexa_enable}" == "on" ] && [ -s "${pfbalexa}" ] && [ -s "${pfbdomain}${alias}.bk" ]; then
		countf="$(grep -c ^ ${pfbdomain}${alias}.bk)"
		grep -vF -f "${pfbalexa}" "${pfbdomain}${alias}.bk" > "${pfbdomain}${alias}.bk2"
		countx="$(grep -c ^ ${pfbdomain}${alias}.bk2)"
		counta="$((countf - countx))"

		if [ "${counta}" -gt 0 ]; then
			if [ "${dedup}" == '' ]; then
				data="$(awk 'FNR==NR{a[$0];next}!($0 in a)' ${pfbdomain}${alias}.bk2 ${pfbdomain}${alias}.bk | \
					cut -d '"' -f2 | cut -d ' ' -f1 | sort | uniq | tr '\n' '|')"
			else
				data="$(awk 'FNR==NR{a[$0];next}!($0 in a)' ${pfbdomain}${alias}.bk2 ${pfbdomain}${alias}.bk | \
					cut -d ',' -f2 | sort | uniq | tr '\n' '|')"
			fi

			if [ -z "${data}" ]; then
				if [ "${dedup}" == '' ]; then
					data="$(cut -d '"' -f2 ${pfbdomain}${alias}.bk | cut -d ' ' -f1 | sort | uniq | tr '\n' '|')"
				else
					data="$(cut -d ',' -f2 ${pfbdomain}${alias}.bk | sort | uniq | tr '\n' '|')"
				fi
			fi

			echo "  TOP1M Whitelist: ${data}"
			mv -f "${pfbdomain}${alias}.bk2" "${pfbdomain}${alias}.bk"
		fi
	else
		counta=0
	fi

	countf="$(grep -c ^ ${pfbdomain}${alias}.bk)"
	rm -f "${pfbdomain}${alias}.bk2"

	echo '  ----------------------------------------------------------------------'
	printf "%-10s %-10s %-10s %-10s %-10s %-10s %-10s\n" '  Orig.' 'Unique' '# Dups' '# White' '# TOP1M' 'Final'
	echo '  ----------------------------------------------------------------------'
	printf "%-10s %-10s %-10s %-10s %-10s %-10s %-10s\n" "  ${counto}" "${countu}" "${countd}" "${countw}" "${counta}" "${countf}"
	echo '  ----------------------------------------------------------------------'
}


# Function to process TLD
domaintld() {
	# List of Feeds
	dnsbl_files="${cc}";

	> "${tempfile}"; > "${tempfile2}"; > "${dupfile}"

	if [ -s "${dnsbl_file}.raw" ]; then
		sort "${dnsbl_file}.raw" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_file}.raw"
		countto="$(grep -v '"transparent"\|\"static\"' ${dnsbl_file}.raw | grep -c ^)"
	else
		countto=0
	fi

	if [ -s "${dnsbl_tld_remove}" ]; then
		sort "${dnsbl_tld_remove}" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_tld_remove}"
		counttm="$(grep -c '^\.' ${dnsbl_tld_remove})"
	else
		counttm=0
	fi

	if [ "${DEBUG}" == 1 ] && [ -e "${dnsbl_file}.raw" ]; then
		cp "${dnsbl_file}.raw" "${dnsbl_file}.raw.orig"
	fi

	printf "."

	# 'Redirect zone'
	# Collect DNSBL TLD files (by smallest line count first) and merge
	dnsbl_tmp_files="$(grep -Hc ^ ${dnsbl_tmp}DNSBL_*.txt | sort -t : -k 2,2n | cut -d':' -f1)"
	if [ ! -z "${dnsbl_tmp_files}" ]; then
		for file in ${dnsbl_tmp_files}; do
			# For each file, place 'local-zone' before 'local-data'
			head -1 "${file}" >> "${dupfile}"
			tail -n +2 "${file}" | sort | uniq >> "${dupfile}"
		done

		# Remove redundant Domains (in 'redirect zone')
		if [ -s "${dnsbl_tld_remove}" ] && [ -s "${dupfile}" ]; then

			if [ "${DEBUG}" == 1 ]; then
				cp "${dupfile}" "${dnsbl_file}.dup"
			fi

			grep -vF -f "${dnsbl_tld_remove}" "${dupfile}" > "${tempfile}"
		else
			mv -f "${dupfile}" "${tempfile}"
		fi
	fi

	# 'Transparent zone'
	# Remove redundant Domains (in 'transparent zone')
	if [ -s "${dnsbl_tld_remove}.tsp" ] && [ -s "${dnsbl_file}.tsp" ]; then
		grep -vF -f "${dnsbl_tld_remove}.tsp" "${dnsbl_file}.tsp" | sort | uniq > "${tempfile2}"
	else
		echo "XXX"
		# XXXX to be confirmed!
		# mv -f "${dnsbl_tld_remove}.tsp" "${tempfile2}"

	fi

	# Merge all TLD files
	if [ -f "${tempfile}" ] || [ -f "${tempfile2}" ]; then
		> "${dnsbl_file}.raw"
		cat "${tempfile}" "${tempfile2}" >> "${dnsbl_file}.raw"
	fi

	if [ "${DEBUG}" == 1 ] && [ -e "${dnsbl_file}.raw" ]; then
		cp "${dnsbl_file}.raw" "${dnsbl_file}.raw.final"
	fi

	# Sort 'Transparent zone' remove file
	if [ -s "${dnsbl_tld_remove}.tsp" ]; then
		sort "${dnsbl_tld_remove}.tsp" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_tld_remove}.tsp"
	fi

	# Remove redundant Domains in DNSBL files
	# Need to re-process all Feeds for TLD (Remove any recently added TLD Domains)
	if [ -s "${dnsbl_tld_remove}.tsp" ]; then
		for i in ${dnsbl_files}; do
			alias="$(echo ${i%*,})"
			printf "."

			if [ "${DEBUG}" == 1 ] && [ -f "${pfbdomain}${alias}.txt" ]; then
				cp "${pfbdomain}${alias}.txt" "${pfbdomain}${alias}.xxx"
			fi

			# Remove redundant TLD Domains
			if [ -s "${pfbdomain}${alias}.txt" ]; then
				grep -vF -f "${dnsbl_tld_remove}.tsp" "${pfbdomain}${alias}.txt" > "${tempfile}"
				mv -f "${tempfile}" "${pfbdomain}${alias}.txt"
			fi
		done
	fi

	counttf="$(grep -v '"transparent"\|\"static\"' ${dnsbl_file}.raw | grep -c ^)"
	counttr="$((countto - counttf))"

	echo
	echo ' ----------------------------------------'
	printf "%-12s %-10s %-10s %-10s\n" ' Original' 'Matches' 'Removed' 'Final'
	echo ' ----------------------------------------'
	printf "%-12s %-10s %-10s %-10s\n" " ${countto}" "${counttm}" "${counttr}" "${counttf}"
	printf ' -----------------------------------------'
}


# Function to process TLD python
domaintldpy() {
	# List of Feeds
	dnsbl_files="${cc}";

	if [ -s "${dnsbl_python_data}.raw" ]; then
		sort "${dnsbl_python_data}.raw" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_python_data}.raw"
		countd="$(grep -c ^ ${dnsbl_python_data}.raw)"
	else
		countd=0
	fi

	if [ -s "${dnsbl_python_zone}.raw" ]; then
		sort "${dnsbl_python_zone}.raw" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_python_zone}.raw"
		countz="$(grep -c ^ ${dnsbl_python_zone}.raw)"
	else
		countz=0
	fi

	printf "."

	countto="$((countd + countz))"

	if [ -s "${dnsbl_tld_remove}" ]; then
		sort "${dnsbl_tld_remove}" | uniq > "${tempfile}" && mv -f "${tempfile}" "${dnsbl_tld_remove}"
		counttm="$(grep -c '^\.' ${dnsbl_tld_remove})"
	else
		counttm=0
	fi

	printf "."

	# Remove redundant Domains (in data)
	if [ -s "${dnsbl_tld_remove}" ] && [ -s "${dnsbl_python_data}.raw" ]; then
		grep -vF -f "${dnsbl_tld_remove}" "${dnsbl_python_data}.raw" > "${dnsbl_python_data}"
	elif [ -e "${dnsbl_python_data}.raw" ]; then
		mv "${dnsbl_python_data}.raw" "${dnsbl_python_data}"
	fi

	printf "."

	# Remove redundant Domains (in zone)
	if [ -s "${dnsbl_tld_remove}" ] && [ -s "${dnsbl_python_zone}.raw" ]; then
		grep -vF -f "${dnsbl_tld_remove}" "${dnsbl_python_zone}.raw" > "${dnsbl_python_zone}"
	elif [ -e "${dnsbl_python_zone}.raw" ]; then
		mv "${dnsbl_python_zone}.raw" "${dnsbl_python_zone}"
	fi

	counttf="$(cat ${dnsbl_python_data} ${dnsbl_python_zone} | grep -c ^)"
	counttr="$((countto - counttf))"

	echo
	echo ' ----------------------------------------'
	printf "%-12s %-10s %-10s %-10s\n" ' Original' 'Matches' 'Removed' 'Final'
	echo ' ----------------------------------------'
	printf "%-12s %-10s %-10s %-10s\n" " ${countto}" "${counttm}" "${counttr}" "${counttf}"
	printf ' -----------------------------------------'

	echo "${counttr}" > "${dnsbl_python_count}"
}


# Function to compare previous and current DNSBL Unbound conf file, and create Add/Remove files for unbound-control cmds
dnsbl_livesync() {

	if [ "${DEBUG}" == 1 ]; then
		if [ -e "${dnsbl_file}.conf" ]; then
			cp "${dnsbl_file}.conf" "${dnsbl_file}.bkr"
		fi
		if [ -e "${dnsbl_file}.raw" ]; then
			cp "${dnsbl_file}.raw" "${dnsbl_file}.bkraw"
		fi
	fi

	rm -f "${dnsbl_add}"*
	rm -f "${dnsbl_remove}"*

	> "${dnsbl_add}"
	> "${dnsbl_add_zone}"
	> "${dnsbl_add_data}"
	> "${dnsbl_remove}"
	> "${dnsbl_remove_zone}"
	> "${dnsbl_remove_data}"

	if [ -s "${dnsbl_file}.conf" ] && [ -s "${dnsbl_file}.raw" ]; then
		# Collect all changes to DNSBL (add/remove)
		printf "."
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${dnsbl_file}.conf" "${dnsbl_file}.raw" > "${dnsbl_add}"
		printf "."
		awk 'FNR==NR{a[$0];next}!($0 in a)' "${dnsbl_file}.raw" "${dnsbl_file}.conf" > "${dnsbl_remove}"
		printf "."
	elif [ -s "${dnsbl_file}.raw" ]; then
		printf "."
		cp "${dnsbl_file}.raw" "${dnsbl_add}"

		# Add a file marker to instruct Unbound to do a reload
		> "${dnsbl_file}.reload"
	else
		printf "."
		# Add a file marker to instruct Unbound to do a reload
		> "${dnsbl_file}.reload"
	fi

	# Example Unbound sinkhole lines:
	# local-zone: "example.com" redirect local-data: "example.com 60 IN A 10.10.10.1"
	# local-data: "example.com 60 IN A 10.10.10.1"
	# local-zone: "com" "transparent"
	# local-zone: "ru" "static"

	# Read 'Remove' file and format local-zone/local-data removal files
	if [ -s "${dnsbl_remove}" ]; then

		# Collect any local-zone removals
		grep "local-zone:" "${dnsbl_remove}" | cut -d '"' -f2 > "${dnsbl_remove_zone}"

		# Collect any local-data removals
		grep "^local-data:" "${dnsbl_remove}" | cut -d ' ' -f2 | tr -d '"' > "${dnsbl_remove_data}"
	fi

	# Read 'Add' file and format local-zone/local-data addition files
	if [ -s "${dnsbl_add}" ]; then

		# Collect local-zone additions
		grep '"transparent"\|\"static\"' "${dnsbl_add}" | cut -d '"' -f2,4 | tr '"', ' ' > "${dnsbl_add_zone}"
		grep "^local-zone:" "${dnsbl_add}" | cut -d ' ' -f2-3 | tr -d '"' >> "${dnsbl_add_zone}"

		# Collect local-data additions
		grep -v '"transparent"\|\"static\"' "${dnsbl_add}" | grep "^local-zone:" | cut -d ' ' -f5-9 | tr -d '"' >> "${dnsbl_add_data}"
		grep "^local-data:" "${dnsbl_add}" | awk '{gsub (/" local/,"\nlocal")}1' | cut -d ' ' -f2-6 | tr -d '"' >> "${dnsbl_add_data}"

		# Create 'transparent' TLD zone for any local-data
		if [ -s "${dnsbl_add_data}" ]; then
			cat "${dnsbl_add_data}" | cut -d ' ' -f1 | rev | cut -d '.' -f1 | rev | sed 's/$/ transparent/' >> "${dnsbl_add_zone}"
		fi
	fi

	if [ -e "${dnsbl_file}.raw" ]; then
		mv -f "${dnsbl_file}.raw" "${dnsbl_file}.conf"
	fi
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
	multiple="$(echo ${dedup} | tr -cd , | wc -c | tr -d ' ')"

	if [ "${vtype}" == '_v4' ]; then
		_type=A
		_bgp_type=4
		_ip_type='\.'
	else
		_type=AAAA
		_bgp_type=6
		_ip_type=':'
	fi

	# Backup previous orig file
	if [ -e "${pfborig}${alias}.orig" ]; then
		mv "${pfborig}${alias}.orig" "${pfborig}${alias}.bk"
	fi

	echo
	found=false

	for host in ${custom_list}; do
		# Determine if host is a Domain or an AS
		host_check="$(echo ${host} | grep '\.')"
		if [ ! -z "${host_check}" ]; then
			found=true
			printf "  Collecting host IP: ${host}"
			echo "### Domain: ${host} ###" >> "${pfborig}${alias}.orig"
			"${pathhost}" -t ${_type} ${host} | sed 's/^.* //' >> "${pfborig}${alias}.orig"
			echo "... completed"
		else
			asn="$(echo ${host} | tr -d 'AaSs')"
			printf "  Downloading ASN: ${asn}"

			ua="pfSense/pfBlockerNG cURL download agent-"
			guid="$(/usr/sbin/gnid)"
			ua_final="${ua}${guid}"

			bgp_url="https://api.bgpview.io/asn/${asn}/prefixes"
			unavailable=''
			for i in 1 2 3 4 5; do
				printf "."
				"${pathcurl}" -H "${ua_final}" -sS1 "${bgp_url}" > "${asntemp}"

				if [ -e "${asntemp}" ] && [ -s "${asntemp}" ]; then
					printf "."
					unavailable="$(grep 'Service Temporarily Unavailable' ${asntemp})"
					if [ -z "${unavailable}" ]; then
						found=true
						echo ". completed"
						echo "### AS${asn}: ${host} ###" >> "${pfborig}${alias}.orig"
						cat "${asntemp}" | "${pathjq}" -r ".data.ipv${_bgp_type}_prefixes[].prefix" >> "${pfborig}${alias}.orig"
						break
					else
						sleep_val="$((i * 2))"
						sleep "${sleep_val}"
					fi
				fi
			done

			if [ ! -z "${unavailable}" ]; then
				echo ". Failed to download ASN"
				touch "${pfborig}${alias}.fail"
			fi

			if [ "${multiple}" -gt 0 ]; then
				sleep 1
			fi
		fi
	done

	# Restore previous orig file
	if [ "${found}" == false ]; then
		if [ -e "${pfborig}${alias}.bk" ]; then
			mv "${pfborig}${alias}.bk" "${pfborig}${alias}.orig"
		else
			echo > "${pfborig}${alias}.orig"
		fi
	else
		if [ -e "${pfborig}${alias}.bk" ]; then
			rm -f "${pfborig}${alias}.bk"
		fi
	fi
}


# Function to check for Reputation application dependencies.
reputation_depends() {
	if [ ! -x "${pathgeoip}" ]; then
		log="Application [ mmdblookup ] Not found, cannot proceed. [ ${now} ]"
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	# Download MaxMind GeoLite2-Country.mmdb on first install.
	if [ ! -f "${pathgeoipdat}" ]; then
		echo "Downloading [ MaxMind GeoLite2-Country.mmdb ] [ ${now} ]" >> "${geoiplog}"
		/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php bu
	fi

	# Exit if GeoLite2-Country.mmdb is not found
	if [ ! -f "${pathgeoipdat}" ]; then
		log="Database GeoIP [ GeoLite2-Country.mmdb ] not found. Reputation function terminated."
		echo "${log}" | tee -a "${errorlog}"
		return
	fi

	# Clear variables and tempfiles
	exitnow
	count=0; countb=0; countm=0; counts=0; countr=0
}


# Reputation function to condense an IP range if a 'Max' amount of IP addresses are found in a /24 range per individual list.
reputation_max() {
	sort "${pfbdeny}${alias}.txt" | uniq > "${tempfile}"
	data="$(cut -d '.' -f 1-3 ${tempfile} | awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}')"

	# Classify repeat offenders by Country code
	if [ ! -z "${data}" ]; then
		for ip in ${data}; do
			ccheck="$(${pathgeoip} -f ${pathgeoipdat} -i ${ip}.1 country iso_code 2>&1 | grep -v 'Could\|Got\|^$' | cut -d '"' -f2)"
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
		awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v ^${ip_placeholder3}$)"

	# Classify repeat offenders by Country code
	if [ ! -z "${data}" ]; then
		echo '  Classifying repeat offenders by GeoIP'
		for ip in ${data}; do
			ccheck="$(${pathgeoip} -f ${pathgeoipdat} -i ${ip}.1 country iso_code 2>&1 | grep -v 'Could\|Got\|^$' | cut -d '"' -f2)"
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
		awk -v max=${max} '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v ^${ip_placeholder3}$)"

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

		category=1
		etcat='ET_Cnc ET_Bot ET_Spam ET_Drop ET_Spywarecnc ET_Onlinegaming ET_Drivebysrc ET_Cat8 ET_Chatserver ET_Tornode
			ET_Cat11 ET_Cat12 ET_Compromised ET_Cat14 ET_P2P ET_Proxy ET_Ipcheck ET_Cat18 ET_Utility ET_DDostarget
			ET_Scanner ET_Cat22 ET_Brute ET_Fakeav ET_Dyndns ET_Undesireable ET_Abusedtld ET_Selfsignedssl ET_Blackhole ET_RAS
			ET_P2Pcnc ET_Cat32 ET_Parking ET_VPN ET_Exesource ET_Cat36 ET_Mobilecnc ET_Mobilespyware ET_Skypenode
			ET_Bitcoin ET_DDosattack'

		for file in ${etcat}; do

			case "${category}" in

				8|11|12|14|18|22|32|36)
					# Some ET categories are not in use (For future use)
					;;
				*)
					grep ",${category}," "${pfborig}${alias}.orig" | cut -d',' -f1 > "${etdir}/${file}.txt"
					;;
			esac
			category="$((category + 1))"
		done

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
		counto="$(cat ${etdir}/ET_* | grep -cv '^#\|^$')"; countf="$(grep -cv ^${ip_placeholder2}$ ${pfborig}${alias}.orig)"
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

		countf="$(grep -cv ^${ip_placeholder2}$ ${pfborig}${alias}.orig)"
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

		s1="$(grep -cv ^${ip_placeholder2}$ ${masterfile})"
		s2="$(find ${pfbdeny}*.txt ! -name *_v6.txt -type f 2>/dev/null | xargs cat | grep -cv ^${ip_placeholder2}$)"
		s3="$(sort ${mastercat} | uniq -d | tail -30)"
		s4="$(find ${pfbdeny}*.txt ! -name *_v6.txt -type f 2>/dev/null | xargs cat | sort | uniq -d | tail -30 | grep -v ^${ip_placeholder2}$)"
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
		emptylists="$(grep ^${ip_placeholder2}$ ${pfbdeny}*.txt | cut -d ':' -f1 | sed -e 's/^.*[a-zA-Z]\///')"
		if [ ! -z "${emptylists}" ]; then
			echo; echo "====================[ Empty Lists w/${ip_placeholder} ]=================="; echo
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
		echo; echo '====================[ IPv4/6 Last Updated List Summary ]=============='; echo
		ls -lahtr "${pfborig}"*.orig | sed -e 's/\/.*\// /' -e 's/.orig//' | awk -v OFS='\t' '{print $6" "$7,$8,$9}'
	fi
	if [ -d "${pfbdomainorig}" ] && [ "$(ls -A ${pfbdomainorig})" ]; then
		echo; echo '====================[ DNSBL Last Updated List Summary ]=============='; echo
		ls -lahtr "${pfbdomainorig}"*.orig | sed -e 's/\/.*\// /' -e 's/.orig//' | awk -v OFS='\t' '{print $6" "$7,$8,$9}'
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
	dnsbl_scrub)
		dnsbl_scrub
		;;
	domaintld)
		domaintld
		;;
	domaintldpy)
		domaintldpy
		;;
	dnsbl_livesync)
		dnsbl_livesync
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
