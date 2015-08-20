#!/bin/sh
# pfBlockerNG IP Reputation Script - By BBcan177@gmail.com - 04-12-14
# Copyright (C) 2015 BBcan177@gmail.com
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

pfs_version=$(cat /etc/version | cut -c 1-3)

if [ "${pfs_version}" = "2.2" ]; then
	mtype=$(/usr/bin/uname -m)
	prefix="/usr/pbi/pfblockerng-${mtype}"
else
	prefix="/usr/local"
fi

now=$(/bin/date +%m/%d/%y' '%T)

# Application Locations
pathgrepcidr="${prefix}/bin/grepcidr"
pathgeoip="${prefix}/bin/geoiplookup"

pathtar=/usr/bin/tar
pathgunzip=/usr/bin/gunzip
pathpfctl=/sbin/pfctl

# Script Arguments
alias=$2
max=$3
dedup=$4
cc=$(echo $5 | sed 's/,/, /g')
ccwhite=$(echo $6 | tr '[A-Z]' '[a-z]')
ccblack=$(echo $7 | tr '[A-Z]' '[a-z]')
etblock=$(echo $8 | sed 's/,/, /g')
etmatch=$(echo $9 | sed 's/,/, /g')

# File Locations
aliasarchive="${prefix}/etc/aliastables.tar.bz2"
pathgeoipdat="${prefix}/share/GeoIP/GeoIP.dat"
pfbsuppression=/var/db/pfblockerng/pfbsuppression.txt
masterfile=/var/db/pfblockerng/masterfile
mastercat=/var/db/pfblockerng/mastercat
geoiplog=/var/log/pfblockerng/geoip.log
errorlog=/var/log/pfblockerng/error.log

# Folder Locations
etdir=/var/db/pfblockerng/ET
tmpxlsx=/tmp/xlsx/

pfbdbdir=/var/db/pfblockerng/
pfbdeny=/var/db/pfblockerng/deny/
pfborig=/var/db/pfblockerng/original/
pfbmatch=/var/db/pfblockerng/match/
pfbpermit=/var/db/pfblockerng/permit/
pfbnative=/var/db/pfblockerng/native/
pfsense_alias_dir=/var/db/aliastables/

# Store "Match" d-dedups in matchdedup.txt file
matchdedup=matchdedup.txt

tempfile=/tmp/pfbtempfile
tempfile2=/tmp/pfbtempfile2
dupfile=/tmp//pfbduptemp
dedupfile=/tmp/pfbdeduptemp
addfile=/tmp/pfBaddfile
syncfile=/tmp/pfbsyncfile
matchfile=/tmp/pfbmatchfile
tempmatchfile=/tmp/pfbtempmatchfile

PLATFORM=`cat /etc/platform`
USE_MFS_TMPVAR=`/usr/bin/grep -c use_mfs_tmpvar /cf/conf/config.xml`
DISK_NAME=`/bin/df /var/db/rrd | /usr/bin/tail -1 | /usr/bin/awk '{print $1;}'`
DISK_TYPE=`/usr/bin/basename ${DISK_NAME} | /usr/bin/cut -c1-2`

if [ "${PLATFORM}" != "pfSense" ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = "md" ]; then
	/usr/local/bin/php /etc/rc.conf_mount_rw >/dev/null 2>&1
	if [ ! -d $pfbdbdir ]; then mkdir $pfbdbdir; fi
	if [ ! -d $pfsense_alias_dir ]; then mkdir $pfsense_alias_dir; fi
fi

if [ ! -f $masterfile ]; then touch $masterfile; fi
if [ ! -f $mastercat ]; then touch $mastercat; fi
if [ ! -f $tempfile ]; then touch $tempfile; fi
if [ ! -f $tempfile2 ]; then touch $tempfile2; fi
if [ ! -f $dupfile ]; then touch $dupfile; fi
if [ ! -f $dedupfile ]; then touch $dedupfile; fi
if [ ! -f $addfile ]; then touch $addfile; fi
if [ ! -f $syncfile ]; then touch $syncfile; fi
if [ ! -f $matchfile ]; then touch $matchfile; fi
if [ ! -f $tempmatchfile ]; then touch $tempmatchfile; fi
if [ ! -d $pfbmatch ]; then mkdir $pfbmatch; fi
if [ ! -d $etdir ]; then mkdir $etdir; fi
if [ ! -d $tmpxlsx ]; then mkdir $tmpxlsx; fi


# Exit Function to set mount RO if required before Exiting
exitnow() {
	if [ "${PLATFORM}" != "pfSense" ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = "md" ]; then
		/usr/local/bin/php /etc/rc.conf_mount_ro >/dev/null 2>&1
	fi
	exit
}


##########
# Process to condense an IP range if a "Max" amount of IP addresses are found in a /24 range per Alias Group.
process24() {

if [ ! -x $pathgeoip ]; then 
	echo "Process24 - Application [ GeoIP ] Not found. Can't proceed."
	echo "Process24 - Application [ GeoIP ] Not found. Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

# Download MaxMind GeoIP.dat Binary on first Install.
if [ ! -f $pathgeoipdat ]; then
	echo "Downloading [ MaxMind GeoIP.dat ] [ $now ]" >> $geoiplog
	/usr/local/pkg/pfblockerng/geoipupdate.sh bu
fi
# Exit if GeoIP.dat is not found.
if [ ! -f $pathgeoipdat ]; then 
	echo "Process24 - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed."
	echo "Process24 - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed. [ $now ]" >>  $errorlog
	exitnow
fi

count=$(grep -c ^ $pfbdeny$alias".txt")
echo; echo "Original File Count [ $count ]"

grep -Ev "^(#|$)" $pfbdeny$alias".txt" | sort | uniq > $tempfile
> $dupfile; > $tempfile2; > $matchfile; > $tempmatchfile
data="$(cut -d '.' -f 1-3 $tempfile | awk -v max="$max" '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}')"
count=$(echo "$data" | grep -c ^); mcount=0; dcount=0; safe=0
if [ "$data" == "" ]; then count=0; fi
matchoutfile="match"$header".txt"
# Classify Repeat Offenders by Country Code
if [ -f $pathgeoipdat ]; then
	for ip in $data; do
		ccheck=$($pathgeoip -f $pathgeoipdat "$ip.1" | cut -c 24-25)
		case "$cc" in
			*$ccheck*)
				safe=$(($safe + 1))
				if [ "$ccwhite" == "match" -o "$ccblack" == "match" ]; then
					echo "$ip." >> $matchfile
				fi
				;;
			*)
				echo "$ip." >> $dupfile
				;;
		esac
	done
else
	echo; echo "MaxMind Binary Database Missing [ $pathgeoipdat ], skipping p24 Process"; echo
	echo "MaxMind Binary Database Missing [ $pathgeoipdat ], skipping p24 Process [ $now ]" >> $errorlog
fi
# Collect Match File Details
if [ -s "$matchfile" -a ! "$dedup" == "on" -a "$ccwhite" == "match" ]; then
	mon=$(sed -e 's/^/^/' -e 's/\./\\\./g' $matchfile)
	for ip in $mon; do
		grep $ip $tempfile >> $tempfile2
	done
	mcount=$(grep -c ^ $tempfile2)
	if [ "$ccwhite" == "match" ]; then
		sed 's/$/0\/24/' $matchfile >> $tempmatchfile
	sed 's/^/\!/' $tempfile2 >> $tempmatchfile
	fi
fi

# If no Matches found remove previous Matchoutfile if exists.
if [ ! -s "$tempmatchfile" -a -f $matchoutfile ]; then rm -r $matchoutfile; fi
# Move Match File to the Match Folder by Individual Blocklist Name
if [ -s "$tempmatchfile" ]; then mv -f $tempmatchfile $pfbmatch$matchoutfile; fi

# Find Repeat Offenders in each individual Blocklist Outfile
if [ -s "$dupfile" ]; then
	> $tempfile2
	dup=$(sed -e 's/^/^/' -e 's/\./\\\./g' $dupfile)
	for ip in $dup; do
		grep $ip $tempfile >> $tempfile2
	done
	dcount=$(grep -c ^ $tempfile2)
	if [ "$ccblack" == "block" ]; then
		awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile2 $tempfile > $pfbdeny$alias".txt"
		sed 's/$/0\/24/' $dupfile >> $pfbdeny$alias".txt"
	elif [ "$ccblack" == "match" ]; then
		sed 's/$/0\/24/' $dupfile >> $tempmatchfile
		sed 's/^/\!/' $tempfile2 >> $tempmatchfile
	else
		:
	fi
fi
if [ "$count" == "0" -a "$safe" == "0" ]; then echo; echo " Process /24 Stats [ $alias ] [ $now ] "; echo "------------------------------------------------"; fi
if [ "$count" == "0" ]; then echo "Found [ 0 ] IP range(s) over the threshold of [ $max ] p24 - CC Blacklist"; fi
if [ "$safe" == "0" ]; then echo "Found [ 0 ] IP range(s) over the threshold of [ $max ] p24 - CC Whitelist"; fi
 
if [ -s "$dupfile" -o -s "$matchfile" ]; then
echo
echo " Process /24 Stats [ $alias ] [ $now ]"
echo "--------------------------------------------------------"
echo "Found [ $count ] IP range(s) over the threshold of [ $max ] on the CC Blacklist"
echo "Found [ $safe ] IP range(s) over the threshold of [ $max ] on the CC Whitelist"
echo
echo "Found [ $dcount ] CC Blacklisted IP Address(es) are being set to [ $ccblack ]"
# Skip Match Process if dedup=yes as it will create duplicates
if [ "$dedup" == "on" ]; then mcount=Skipped; fi
echo "Found [ $mcount ] CC Whitelisted IP Address(es) are being set to [ $ccwhite ]"
if [ "$ccblack" == "block" ]; then
	echo; echo "Removed the following IP Ranges"
	cat $dupfile | tr '\n' '|'; echo
else
	echo "Skipped, CCBlack set to [ $ccblack ]"
fi
sort $pfbdeny$alias".txt" | uniq > $tempfile; mv -f $tempfile $pfbdeny$alias".txt"
echo "-------------------------------------------------------"
cocount=$(grep -cv "^1\.1\.1\.1" $pfbdeny$alias".txt")
echo "Post /24 Count   [ $cocount ]"; echo
fi
exitnow
}


##########
process255() {
# Remove IPs if exists over 255 IPs in a Range and replace with a single /24 Block
cp $pfbdeny$alias".txt" $tempfile; > $dedupfile

data255="$(cut -d '.' -f 1-3 $tempfile | awk '{a[$0]++}END{for(i in a){if(a[i] > 255){print i}}}')"
if [ ! -z "$data255" ]; then
	for ip in $data255; do
		ii=$(echo "^$ip" | sed 's/\./\\\./g')
		grep $ii $tempfile >> $dedupfile
	done
	awk 'FNR==NR{a[$0];next}!($0 in a)' $dedupfile $tempfile > $pfbdeny$alias".txt"
	for ip in $data255; do echo $ip"0/24" >> $pfbdeny$alias".txt"; done
fi
}


##########
continent() {

dupcheck=yes
# Check if Masterfile is Empty
hcheck=$(grep -c ^ $masterfile); if [ "$hcheck" -eq "0" ]; then dupcheck=no; fi
# Check if Alias exists in Masterfile
lcheck=$(grep -m 1 "$alias " $masterfile ); if [ "$lcheck" == "" ]; then dupcheck=no; fi

if [ "$dupcheck" == "yes" ]; then
	# Grep Alias with a trailing Space character
	grep "$alias[[:space:]]" $masterfile > $tempfile
	awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
	cut -d' ' -f2 $masterfile > $mastercat
fi

grep -Ev "^(#|$)" $pfbdeny$alias".txt" | sort | uniq > $tempfile

if [ ! "$hcheck" -eq "0" ]; then
	$pathgrepcidr -vf $mastercat $pfbdeny$alias".txt" > $tempfile; mv -f $tempfile $pfbdeny$alias".txt"
fi

sed -e 's/^/'$alias' /' $pfbdeny$alias".txt" >> $masterfile
cut -d' ' -f2 $masterfile > $mastercat

countg=$(grep -c ^ $pfborig$alias".orig")
countm=$(grep -c "$alias " $masterfile); counto=$(grep -c ^ $pfbdeny$alias".txt")
if [ "$countm" == "$counto" ]; then sanity="Passed"; else sanity=" ==> FAILED <== "; fi
echo "----------------------------------------------------------"
echo; echo " Post Duplication count [ $now ]"
echo "----------------------------------------------------------"
printf "%-10s %-10s %-10s %-30s\n" "Original" "Masterfile" "Outfile" "Sanity Check"
echo "----------------------------------------------------------"
printf "%-10s %-10s %-10s %-30s\n" "$countg" "$countm" "$counto" " [ $sanity ]"
echo "----------------------------------------------------------"
exitnow
}


##########
# Process to remove Suppressed Entries and RFC 1918 and Misc IPs on each downloaded Blocklist
suppress() {

if [ ! -x $pathgrepcidr ]; then
	echo "Application [ Grepcidr ] Not found. Can't proceed. [ $now ]"
	echo "Application [ Grepcidr ] Not found. Can't proceed. [ $now ]" >> errorlog
	exitnow
fi

if [ -e "$pfbsuppression" ] && [ -s "$pfbsuppression" ]; then
	# Find '/24' Blocked IPs that are single addresses in the Suppressed IP Address List.
	# These '/24' Are converted to single Addresses excluding the Suppressed IPs.
	data="$(cat $pfbsuppression)"
	if [ ! -z "$data" -a ! -z "$cc" ]; then
		# Loop thru each Updated List to remove Suppression and RFC1918 Addresses
		if [ "$cc" == "suppressheader" ]; then
			echo; echo "===[ Suppression Stats ]========================================"; echo
			printf "%-20s %-10s %-10s %-10s %-10s\n" "List" "Pre" "RFC1918" "Suppress" "Masterfile"
			echo "----------------------------------------------------------------"
			exitnow
		fi

		for i in $cc; do
			counter=0
			> $dupfile
			alias=$(echo "${i%|*}")
			pfbfolder=$(echo "${i#*|}")

			if [ ! "$alias" == "" ]; then
				# Count (PRE)	
				countg=$(grep -c ^ $pfbfolder$alias".txt")

				grep -Ev "^(192\.168|10\.|172\.1[6789]\.|172\.2[0-9]\.|172\.3[01]\.|#|$)" $pfbfolder$alias".txt" |
					sort | uniq > $tempfile	
				# Count (Post RFC1918)
				countm=$(grep -c ^ $tempfile)

				for ip in $data; do
					found=""; ddcheck="";
					iptrim=$(echo $ip | cut -d '.' -f 1-3)
					mask=$(echo $ip | cut -d"/" -f2)
					found=$(grep -m1 $iptrim".0/24" $tempfile)
					# If a Suppression is '/32' and a Blocklist has a full '/24' Block execute the following.
					if [ ! "$found" == "" -a "$mask" == "32" ]; then
						echo " Suppression $alias: $iptrim.0/24"
						octet4=$(echo $ip | cut -d '.' -f 4 | sed 's/\/.*//')
						dcheck=$(grep $iptrim".0/24" $dupfile)
						if [ "$dcheck" == "" ]; then
							echo $iptrim".0" >> $tempfile
							echo $iptrim".0/24" >> $dupfile
							counter=$(($counter + 1))
							# Add Individual IP addresses from Range excluding Suppressed IP
							for i in $(/usr/bin/jot 255); do
								if [ "$i" != "$octet4" ]; then
									echo $iptrim"."$i >> $tempfile
									counter=$(($counter + 1))
								fi
							done
						fi
					fi
				done
				if [ -s $dupfile ]; then
					# Remove '/24' Suppressed Ranges
					awk 'FNR==NR{a[$0];next}!($0 in a)' $dupfile $tempfile > $tempfile2; mv -f $tempfile2 $tempfile
				fi
				# Remove All other Suppressions from Lists
				$pathgrepcidr -vf $pfbsuppression $tempfile > $pfbfolder$alias".txt"
				# Update Masterfiles. Don't execute if Duplication Process is Disabled
				if [ "$dedup" == "x" ]; then
					# Dont execute if Alias doesnt exist in Masterfile
					lcheck=$(grep -m1 "$alias " $masterfile)
					if [ ! "$lcheck" == "" ]; then
						# Replace Masterfile with changes to List.
						grep "$alias[[:space:]]" $masterfile > $tempfile
						awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
						sed -e 's/^/'$alias' /' $pfbfolder$alias".txt" >> $masterfile
						cut -d' ' -f2 $masterfile > $mastercat
					fi
				fi
				countk=$(grep -c ^ $masterfile)
				countx=$(grep -c ^ $pfbfolder$alias".txt")
				counto=$(($countx - $counter))
				printf "%-20s %-10s %-10s %-10s %-10s\n" "$alias" "$countg" "$countm" "$counto" "$countk"
			fi
		done
	fi
else
	if [ "$cc" == "suppressheader" ]; then
		echo; echo "===[ Suppression Stats ]========================================"; echo
		printf "%-20s %-10s %-10s %-10s %-10s\n" "List" "Pre" "RFC1918" "Suppress" "Masterfile"
		echo "----------------------------------------------------------------"
		exitnow
	fi
	for i in $cc; do
		alias=$(echo "${i%|*}")
		pfbfolder=$(echo "${i#*|}")

		if [ ! "$alias" == "" ]; then
			countg=$(grep -c ^ $pfbfolder$alias".txt")
			grep -Ev "^(192\.168|10\.|172\.1[6789]\.|172\.2[0-9]\.|172\.3[01]\.|#|$)" $pfbfolder$alias".txt" |
				sort | uniq > $tempfile; mv -f $tempfile $pfbfolder$alias".txt"
			countx=$(grep -c ^ $pfbfolder$alias".txt")
			# Update Masterfiles. Don't execute if Duplication Process is Disabled or if No Suppression Changes Found
			if [ "$dedup" == "x" -a "$countg" != "$countx" ]; then
				# Dont execute if Alias doesnt exist in Masterfile
				lcheck=$(grep -m1 "$alias " $masterfile)
				if [ ! "$lcheck" == "" ]; then
					# Replace Masterfile with changes to List.
					grep "$alias[[:space:]]" $masterfile > $tempfile
					awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
					sed -e 's/^/'$alias' /' $pfbfolder$alias".txt" >> $masterfile
					cut -d' ' -f2 $masterfile > $mastercat
				fi
			fi
			countm=$(grep -c ^ $pfbfolder$alias".txt")
			counto=" - "
			countk=$(grep -c ^ $masterfile)
			printf "%-20s %-10s %-10s %-10s %-10s\n" "$alias" "$countg" "$countm" "$counto" "$countk"
		fi
	done
fi
exitnow
}


##########
# Process to remove Duplicate Entries on each downloaded Blocklist Individually
duplicate() {

if [ ! -x $pathgrepcidr ]; then
	echo "Application [ Grepcidr ] Not found. Can't proceed. [ $now ]"
	echo "Application [ Grepcidr ] Not found. Can't proceed. [ $now ]" >> errorlog
	exitnow
fi

dupcheck=yes
# Check if Masterfile is Empty
hcheck=$(grep -cv "^$" $masterfile); if [ "$hcheck" -eq "0" ]; then dupcheck=no; fi
# Check if Alias exists in Masterfile
lcheck=$(grep -m1 "$alias " $masterfile); if [ "$lcheck" == "" ]; then dupcheck=no; fi

if [ "$dupcheck" == "yes" ]; then
	# Grep Alias with a trailing Space character
	grep "$alias[[:space:]]" $masterfile > $tempfile
	awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
	cut -d' ' -f2 $masterfile > $mastercat
fi

grep -Ev "^(#|$)" $pfbdeny$alias".txt" | sort | uniq > $tempfile; mv -f $tempfile $pfbdeny$alias".txt"

if [ ! "$hcheck" -eq "0" ]; then
	$pathgrepcidr -vf $mastercat $pfbdeny$alias".txt" > $tempfile; mv -f $tempfile $pfbdeny$alias".txt"
fi

sed -e 's/^/'$alias' /' $pfbdeny$alias".txt" >> $masterfile
cut -d' ' -f2 $masterfile > $mastercat

countg=$(grep -c ^ $pfborig$alias".orig")
countm=$(grep -c "$alias " $masterfile); counto=$(grep -c ^ $pfbdeny$alias".txt")
if [ "$countm" == "$counto" ]; then sanity="Passed"; else sanity=" ==> FAILED <== "; fi
echo "----------------------------------------------------------"
printf "%-10s %-10s %-10s %-30s\n" "Original" "Masterfile" "Outfile" " [ Post Duplication count ]"
echo "----------------------------------------------------------"
printf "%-10s %-10s %-10s %-30s\n" "$countg" "$countm" "$counto" " [ $sanity ]"
echo "----------------------------------------------------------"
exitnow
}


##########
# De-Duplication utilizing MaxMind GeoIP Country Code Whitelisting ("dmax" variable)
deduplication() {

if [ ! -x $pathgeoip ]; then
	echo "d-duplication - Application [ GeoIP ] Not found. Can't proceed."
	echo "d-duplication - Application [ GeoIP ] Not found. Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

# Download MaxMind GeoIP.dat on first Install.
if [ ! -f $pathgeoipdat ]; then 
	echo "Downloading [ MaxMind GeoIP.dat ] [ $now ]" >> $geoiplog
	/usr/local/pkg/pfblockerng/geoipupdate.sh bu
fi

# Exit if GeoIP.dat is not found
if [ ! -f $pathgeoipdat ]; then
	echo "d-duplication - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed."
	echo "d-duplication - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

> $tempfile; > $tempfile2; > $dupfile; > $addfile; > $dedupfile; > $matchfile; > $tempmatchfile; count=0; dcount=0; mcount=0; mmcount=0
echo; echo "Querying for Repeat Offenders"
data="$(find $pfbdeny ! -name "pfB*.txt" ! -name "*_v6.txt" -type f | cut -d '.' -f 1-3 $pfbdeny*.txt |
	awk -v max="$max" '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v "^1\.1\.1")"
count=$(echo "$data" | grep -c ^)
if [ "$data" == "" ]; then count=0; fi
safe=0
# Classify Repeat Offenders by Country Code
if [ -f $pathgeoipdat ]; then
	echo "Classifying Repeat Offenders by GeoIP"
	for ip in $data; do
		ccheck=$($pathgeoip -f $pathgeoipdat "$ip.1" | cut -c 24-25)
		case "$cc" in
			*$ccheck*)
				safe=$(($safe + 1))
				if [ "$ccwhite" == "match" -o "$ccblack" == "match" ]; then
					echo "$ip." >> $matchfile
				fi
				;;
			*)
				echo "$ip." >> $dupfile
				;;
		esac
	done
else
	echo; echo "MaxMind Binary Database Missing [ $pathgeoipdat ], skipping d-dedup Process"; echo
	echo "MaxMind Binary Database Missing [ $pathgeoipdat ], skipping d-dedup Process [ $now ]" >> $errorlog
fi
if [ -s "$matchfile" -a "$ccwhite" == "match" ]; then
	echo "Processing [ Match ] IPs"
	match=$(sed -e 's/^/^/' -e 's/\./\\\./g' $matchfile)
	for mfile in $match; do
		grep $mfile $pfbdeny*.txt >> $tempfile
	done
	sed 's/$/0\/24/' $matchfile >> $tempmatchfile
	sed -e 's/.*://' -e 's/^/\!/' $tempfile >> $tempmatchfile
	mv -f $tempmatchfile $pfbmatch$matchdedup
	mcount=$(grep -c ^ $tempfile)
	mmcount=$(($mcount + $mmcount))
fi
# Find Repeat Offenders in each individual Blocklist Outfile
if [ -s "$dupfile" ]; then
	echo "Processing [ Block ] IPs"
	dup=$(cat $dupfile)
	for ip in $dup; do
		pcount=1; ii=$(echo "^$ip" | sed 's/\./\\\./g')
		list=$(find $pfbdeny ! -name "pfB*.txt" ! -name "*_v6.txt" -type f | xargs grep -al $ii)
		for blfile in $list; do
			header=$(echo "${blfile##*/}" | cut -d '.' -f1)
			grep $ii $blfile > $tempfile
			if [ "$ccblack" == "block" ]; then
				awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $blfile > $tempfile2; mv -f $tempfile2 $blfile
				if [ "$pcount" -eq "1" ]; then
					echo $ip"0/24" >> $blfile
					echo $header" "$ip >> $dedupfile
					echo $header" "$ip"0/24" >> $addfile
					pcount=2
				else
					echo $header" "$ip >> $dedupfile
				fi
			else	
				if [ "$pcount" -eq "1" ]; then
					matchoutfile="match"$header".txt"
					echo $ip"0/24" >> $pfbmatch$matchoutfile
					sed 's/^/\!/' $tempfile >> $pfbmatch$matchoutfile
					mcount=$(grep -c ^ $pfbmatch$matchoutfile)
					mmcount=$(($mcount + $mmcount))
					pcount=2
				fi
			fi
		done
	done
	# Remove Repeat Offenders in Masterfiles
	if [ -s "$dedupfile" ]; then
		echo "Removing   [ Block ] IPs"
		> $tempfile; > $tempfile2
		sed 's/\./\\\./g' $dedupfile > $tempfile2
		while IFS=' ' read -r ips; do grep "$ips" $masterfile >> $tempfile; done < $tempfile2
		dcount=$(grep -c ^ $tempfile)
		awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
		cat $addfile >> $masterfile
		cut -d' ' -f2 $masterfile > $mastercat
	fi
fi

echo; echo "d-Duplication Process  [ $now ]"; echo "------------------------------------------------"
echo; echo "Found [ $count ] IP range(s) over the threshold of dmax= [ $max ]"
echo "Found [ $safe ] IP range(s) classified as Whitelisted"
echo; echo "Found [ $dcount ] CC Blacklisted IP Address(es) are being set to [ $ccblack ]"
echo "Found [ $mmcount ] CC Whitelisted IP Address(es) are being set to [ $ccwhite ]"; echo
if [ -s "$addfile" ]; then
        echo; echo "Removed the following IP Ranges"
        sed -e 's/^.* //' -e 's/0\/24//' $addfile | tr '\n' '|'; echo
fi
count=$(grep -c ^ $masterfile)
echo " [ Post d-Deduplication count ]  [ $count ]"; echo

# Write "1.1.1.1" to empty Final Blocklist Files
emptyfiles=$(find $pfbdeny -size 0)
for i in $emptyfiles; do echo "1.1.1.1" > $i; done
exitnow
}


##########
# Process to perform a final De-Duplication on all of the BlockLists (Excluding Country Whitelist) ("pmax" variable).
pdeduplication(){

if [ ! -x $pathgeoip ]; then
	echo "p-duplication - Application [ GeoIP ] Not found. Can't proceed."
	echo "p-duplication - Application [ GeoIP ] Not found. Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

# Download MaxMind GeoIP.dat on first Install.
if [ ! -f $pathgeoipdat ]; then
	echo "Downloading [ MaxMind GeoIP.dat ] [ $now ]" >> $geoiplog
	/usr/local/pkg/pfblockerng/geoipupdate.sh bu
fi
# Exit if GeoIP.dat is not found.
if [ ! -f $pathgeoipdat ]; then
	echo "p-duplication - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed."
	echo "p-duplication - Database GeoIP [ GeoIP.Dat ] not found. Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

> $tempfile; > $tempfile2; > $dupfile; > $addfile; > $dedupfile; count=0; dcount=0
echo; echo "====================================================================="
echo; echo "Querying for Repeat Offenders"
data="$(find $pfbdeny ! -name "pfB*.txt" ! -name "*_v6.txt" -type f | cut -d '.' -f 1-3 $pfbdeny*.txt |
	awk -v max="$max" '{a[$0]++}END{for(i in a){if(a[i] > max){print i}}}' | grep -v "^1\.1\.1")"
count=$(echo "$data" | grep -c ^)
if [ "$data" == "" ]; then count=0; fi
# Find Repeat Offenders in each individual Blocklist Outfile
echo "Processing [ Block ] IPs"
for ip in $data; do
	pcount=1; ii=$(echo "^$ip." | sed 's/\./\\\./g')
	list=$(find $pfbdeny ! -name "pfB*.txt" ! -name "*_v6.txt" -type f | xargs grep -al $ii)
	for blfile in $list; do
		header=$(echo "${blfile##*/}" | cut -d '.' -f1)
		grep $ii $blfile > $tempfile
		awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $blfile > $tempfile2; mv -f $tempfile2 $blfile
		if [ "$pcount" -eq "1" ]; then
			echo $ip".0/24" >> $blfile
			echo $header" $ip." >> $dedupfile
			echo $header" "$ip".0/24" >> $addfile
			pcount=2
		else
			echo $header" $ip." >> $dedupfile
		fi
	done
done
# Remove Repeat Offenders in Masterfile
if [ -s "$dedupfile" ]; then
	echo "Removing   [ Block ] IPs"
	> $tempfile; > $tempfile2
	sed 's/\./\\\./g' $dedupfile > $tempfile2
	while IFS=' ' read -r ips; do grep "$ips" $masterfile >> $tempfile; done < $tempfile2
	dcount=$(grep -c ^ $tempfile)
	awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
	cat $addfile >> $masterfile
	cut -d' ' -f2 $masterfile > $mastercat
fi

echo; echo "p-Duplication Process  [ $now ]"; echo "------------------------------------------------"
echo "Found [ $dcount ] IP Address(es) are being set to [ block ]"
if [ -s "$addfile" ]; then
	echo; echo "Removed the following IP Ranges"
	sed -e 's/^.* //' -e 's/0\/24//' $addfile | tr '\n' '|'; echo
fi
count=$(grep -c ^ $masterfile)
echo; echo " [ Post p-Deduplication count ]  [ $count ]"

# Write "1.1.1.1" to empty Final Blocklist Files
emptyfiles=$(find $pfbdeny -size 0)
for i in $emptyfiles; do echo "1.1.1.1" > $i; done
exitnow
}


##########
# Process to Split ET Pro IPREP into Category Files and Compile selected Blocked categories into Outfile
processet() {

if [ ! -x $pathgunzip ]; then
	echo "Application [ Gunzip ] Not found, Can't proceed."
	echo "Application [ Gunzip ] Not found, Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

if [ -s $pfborig$alias".gz" ]; then
	evar="ET_*"
	# Remove Previous ET IPRep Files
	[ -d $etdir ] && [ "$(ls -A $etdir)" ] && rm -r $etdir/$evar
	> $tempfile; > $tempfile2

	$pathgunzip -c $pfborig$alias".gz" > $pfborig$alias".raw"

	# ET CSV Format (IP, Category, Score)
	while IFS="," read a b c; do
		# Some ET Categories are not in use (For Future Use)
		case "$b" in
			1)  echo $a >> $etdir/ET_Cnc;;
			2)  echo $a >> $etdir/ET_Bot;;
			3)  echo $a >> $etdir/ET_Spam;;
			4)  echo $a >> $etdir/ET_Drop;;
			5)  echo $a >> $etdir/ET_Spywarecnc;;
			6)  echo $a >> $etdir/ET_Onlinegaming;;
			7)  echo $a >> $etdir/ET_Drivebysrc;;
			8)  echo $a >> $etdir/ET_Cat8;;
			9)  echo $a >> $etdir/ET_Chatserver;;
			10) echo $a >> $etdir/ET_Tornode;;
			11) echo $a >> $etdir/ET_Cat11;;
			12) echo $a >> $etdir/ET_Cat12;;
			13) echo $a >> $etdir/ET_Compromised;;
			14) echo $a >> $etdir/ET_Cat14;;
			15) echo $a >> $etdir/ET_P2P;;
			16) echo $a >> $etdir/ET_Proxy;;
			17) echo $a >> $etdir/ET_Ipcheck;;
			18) echo $a >> $etdir/ET_Cat18;;
			19) echo $a >> $etdir/ET_Utility;;
			20) echo $a >> $etdir/ET_DDos;;
			21) echo $a >> $etdir/ET_Scanner;;
			22) echo $a >> $etdir/ET_Cat22;;
			23) echo $a >> $etdir/ET_Brute;;
			24) echo $a >> $etdir/ET_Fakeav;;
			25) echo $a >> $etdir/ET_Dyndns;;
			26) echo $a >> $etdir/ET_Undesireable;;
			27) echo $a >> $etdir/ET_Abusedtld;;
			28) echo $a >> $etdir/ET_Selfsignedssl;;
			29) echo $a >> $etdir/ET_Blackhole;;
			30) echo $a >> $etdir/ET_RAS;;
			31) echo $a >> $etdir/ET_P2Pcnc;;
			32) echo $a >> $etdir/ET_Sharedhosting;;
			33) echo $a >> $etdir/ET_Parking;;
			34) echo $a >> $etdir/ET_VPN;;
			35) echo $a >> $etdir/ET_Exesource;;
			36) echo $a >> $etdir/ET_Cat36;;
			37) echo $a >> $etdir/ET_Mobilecnc;;
			38) echo $a >> $etdir/ET_Mobilespyware;;
			39) echo $a >> $etdir/ET_Skypenode;;
			40) echo $a >> $etdir/ET_Bitcoin;;
			41) echo $a >> $etdir/ET_DDosattack;;
			*)  echo $a >> $etdir/ET_Unknown;;
		esac
	done <"$pfborig$alias.raw"
	data=$(ls $etdir)
	echo; echo "Compiling ET IP IQRisk REP Lists based upon User Selected Categories"
	printf "%-10s %-25s\n" "  Action" "Category"
	echo "-------------------------------------------"

	for list in $data; do
		case "$etblock" in
			*$list*)
				printf "%-10s %-25s\n" "  Block: " "$list"
				cat $etdir/$list >> $tempfile
				;;
		esac
		case "$etmatch" in
			*$list*)
				printf "%-10s %-25s\n" "  Match: " "$list"
				cat $etdir/$list >> $tempfile2
				;;
		esac
	done
	echo "-------------------------------------------"

	if [ -f $tempfile ]; then mv -f $tempfile $pfborig$alias".orig"; fi
	if [ "$etmatch" != "x" ]; then mv -f $tempfile2 $pfbmatch/ETMatch.txt; fi
	cicount=$(cat $etdir/$evar | grep -cv '^#\|^$'); cocount=$(grep -cv "^1\.1\.1\.1" $pfborig$alias".orig")
	echo; echo "ET Folder count [ $cicount ]  Outfile count [ $cocount ]"
else
	echo; echo "No ET .GZ File Found!"
fi
exitnow
}

# Process to extract IP addresses from XLSX Files
processxlsx() {

if [ ! -x $pathtar ]; then
	echo "Application [ TAR ] Not found, Can't proceed."
	echo "Application [ TAR ] Not found, Can't proceed. [ $now ]" >> $errorlog
	exitnow
fi

if [ -s $pfborig$alias".zip" ]; then

	$pathtar -xf $pfborig$alias".zip" -C $tmpxlsx
	$pathtar -xOf $tmpxlsx*.[xX][lL][sS][xX] xl/sharedStrings.xml |
		grep -aoEw "(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)" | sort | uniq > $pfborig$alias".orig"
	rm -r $tmpxlsx*

	cocount=$(grep -cv "^1\.1\.1\.1" $pfborig$alias".orig")
	echo; echo "Download file count [ ZIP file ]  Outfile count [ $cocount ]"
else
	echo "XLSX Download File Missing"
	echo " [ $alias ] XLSX Download File Missing [ $now ]" >> $errorlog
fi
exitnow
}

closingprocess() {

# Write "1.1.1.1" to empty Final Blocklist Files
emptyfiles=$(find $pfbdeny -size 0)
for i in $emptyfiles; do echo "1.1.1.1" > $i; done

if [ -d "$pfborig" ] && [ "$(ls -A $pfborig)" ]; then
	fcount=$(find $pfborig*.orig | xargs cat | grep -cv '^#\|^$')
else
	fcount=0
fi

if [ "$alias" == "on" ]; then
	sort -o $masterfile $masterfile
	sort -t . -k 1,1n -k 2,2n -k 3,3n -k 4,4n $mastercat > $tempfile; mv -f $tempfile $mastercat

	echo; echo "===[ FINAL Processing ]====================================="; echo
	echo "   [ Original count   ]  [ $fcount ]"
	count=$(grep -c ^ $masterfile)
	echo; echo "   [ Processed Count  ]  [ $count ]"; echo

	s1=$(grep -cv "1\.1\.1\.1" $masterfile)
	s2=$(find $pfbdeny ! -name "*_v6.txt" -type f | xargs cat | grep -cv "^1\.1\.1\.1")
	s3=$(sort $mastercat | uniq -d | tail -30)
	s4=$(find $pfbdeny ! -name "*_v6.txt" -type f | xargs cat | sort | uniq -d | tail -30 | grep -v "^1\.1\.1\.1")

	if [ -d "$pfbpermit" ] && [ "$(ls -A $pfbpermit)" ]; then
		echo; echo "===[ Permit List IP Counts ]========================="; echo
		wc -l $pfbpermit* | sort -n -r
	fi
	if [ -d "$pfbmatch" ] && [ "$(ls -A $pfbmatch)" ]; then
		echo; echo "===[ Match List IP Counts ]=========================="; echo
		wc -l $pfbmatch* | sort -n -r
	fi
	if [ -d "$pfbdeny" ] && [ "$(ls -A $pfbdeny)" ]; then
		echo; echo "===[ Deny List IP Counts ]==========================="; echo
		wc -l $pfbdeny* | sort -n -r
	fi
	if [ -d "$pfbnative" ] && [ "$(ls -A $pfbnative)" ]; then
		echo; echo "===[ Native List IP Counts ] ==================================="; echo
		wc -l $pfbnative* | sort -n -r
	fi
	if [ -d "$pfbdeny" ] && [ "$(ls -A $pfbdeny)" ]; then
		emptylists=$(grep "1\.1\.1\.1" $pfbdeny* | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:1.1.1.1/ /')
		if [ ! -z "$emptylists" ]; then 
			echo; echo "====================[ Empty Lists w/1.1.1.1 ]=================="; echo
			for list in $emptylists; do
				echo $list
			done
		fi
	fi
	if [ -d "$pfborig" ] && [ "$(ls -A $pfborig)" ]; then
		echo; echo "====================[ Last Updated List Summary ]=============="; echo
		ls -lahtr $pfborig* | sed -e 's/\/.*\// /' -e 's/.orig//' | awk -v OFS='\t' '{print $6" "$7,$8,$9}'
	fi
	echo "==============================================================="; echo
	echo "Sanity Check (Not Including IPv6)  ** These two Counts should Match! **"
	echo "------------"
	echo "Masterfile Count    [ $s1 ]"
	echo "Deny folder Count   [ $s2 ]"; echo
	echo "Duplication Sanity Check (Pass=No IPs reported)"
	echo "------------------------"
	echo "Masterfile/Deny Folder Uniq check"
	if [ ! -z "$s3" ]; then echo $s3; fi
	echo "Deny Folder/Masterfile Uniq check"
	if [ ! -z "$s4" ]; then echo $s4; fi
	echo; echo "Sync Check (Pass=No IPs reported)"
	echo "----------"
else
	echo; echo "===[ FINAL Processing ]============================================="; echo
	echo "   [ Original count   ]  [ $fcount ]"
	if [ -d "$pfbpermit" ] && [ "$(ls -A $pfbpermit)" ]; then
		echo; echo "===[ Permit List IP Counts ]========================="; echo
		wc -l $pfbpermit* | sort -n -r
	fi
	if [ -d "$pfbmatch" ] && [ "$(ls -A $pfbmatch)" ]; then
		echo; echo "===[ Match List IP Counts ]=========================="; echo
		wc -l $pfbmatch* | sort -n -r
	fi
	if [ -d "$pfbdeny" ] && [ "$(ls -A $pfbdeny)" ]; then
		echo; echo "===[ Deny List IP Counts ]==========================="; echo
		wc -l $pfbdeny* | sort -n -r
	fi
	if [ -d "$pfbnative" ] && [ "$(ls -A $pfbnative)" ]; then
		echo; echo "===[ Native List IP Counts ] ==================================="; echo
		wc -l $pfbnative* | sort -n -r
	fi
	if [ -d "$pfbdeny" ] && [ "$(ls -A $pfbdeny)" ]; then
		emptylists=$(grep "1\.1\.1\.1" $pfbdeny* | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:1.1.1.1/ /')
		if [ ! -z "$emptylists" ]; then
			echo; echo "====================[ Empty Lists w/1.1.1.1 ]=================="; echo
			for list in $emptylists; do
				echo $list
			done
		fi
	fi
	if [ -d "$pfborig" ] && [ "$(ls -A $pfborig)" ]; then
		echo; echo "====================[ Last Updated List Summary ]=============="; echo
		ls -lahtr $pfborig* | sed -e 's/\/.*\// /' -e 's/.orig//' | awk -v OFS='\t' '{print $6" "$7,$8,$9}'
		echo "==============================================================="
	fi
fi

echo; echo "IPv4 Alias Table IP Total"; echo "-----------------------------"
find $pfsense_alias_dir ! -name "*_v6.txt" -type f | xargs cat | grep -c ^

echo; echo "IPv6 Alias Table IP Total"; echo "-----------------------------"
find $pfsense_alias_dir -name "*_v6.txt" -type f | xargs cat | grep -c ^

echo; echo "Alias Table IP Counts"; echo "-----------------------------"
wc -l $pfsense_alias_dir*.txt | sort -n -r

echo; echo "pfSense Table Stats"; echo "-------------------"
$pathpfctl -s memory | grep "table-entries"
pfctlcount=$($pathpfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}')
echo "Table Usage Count       " $pfctlcount
exitnow
}

remove() {
# Remove Lists from Masterfiles and Delete Associated Files
echo
for i in $cc; do
	header=$(echo "${i%*,}")
	if [ ! "$header" == "" ]; then
		# Make sure that Alias Exists in Masterfile before removal.
		masterchk=$(grep -m1 "$header[[:space:]]" $masterfile)
		if [ ! -z "$masterchk" ]; then
			# Grep Header with a Trailing Space character
			grep "$header[[:space:]]" $masterfile > $tempfile
			awk 'FNR==NR{a[$0];next}!($0 in a)' $tempfile $masterfile > $tempfile2; mv -f $tempfile2 $masterfile
			cut -d' ' -f2 $masterfile > $mastercat
		fi
		rm -rf $pfborig$header*; rm -rf $pfbdeny$header*; rm -rf $pfbmatch$header*; rm -rf $pfbpermit$header*; rm -rf $pfbnative$header*
		echo "The Following list has been REMOVED [ $header ]"
	fi
	echo
done

# Delete Masterfiles if they are empty
emptychk=$(find $masterfile -size 0)
if [ ! "$emptychk" == "" ]; then
	rm -r $masterfile; rm -r $mastercat
fi
exitnow
}

# Process to restore aliasables from archive on reboot ( NanoBSD and Ramdisk Installations only )
aliastables() {
	if [ "${PLATFORM}" != "pfSense" ] || [ ${USE_MFS_TMPVAR} -gt 0 ] || [ "${DISK_TYPE}" = "md" ]; then
		[ -f $aliasarchive ] && cd $pfsense_alias_dir && /usr/bin/tar -jxvf $aliasarchive
	fi
	exitnow
}


##########
# CALL APPROPRIATE PROCESSES using Script Argument $1
case $1 in
	continent)
		continent
		;;
	duplicate)
		process255
		duplicate
		;;
	suppress)
		suppress
		;;
	p24)
		process24
		;;
	dedup)
		deduplication
		;;
	pdup)
		pdeduplication
		;;
	et)
		processet
		;;
	xlsx)
		processxlsx
		;;
	closing)
		closingprocess
		;;
	remove)
		remove
		;;
	aliastables)
		aliastables
		;;
	*)
		exitnow
		;;
esac
exitnow