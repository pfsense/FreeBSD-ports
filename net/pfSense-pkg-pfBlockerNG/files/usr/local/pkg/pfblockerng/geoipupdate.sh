#!/bin/sh
#
# pfBlockerNG MaxMind GeoLite GeoIP Updater Script - By BBcan177@gmail.com
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
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
#
# The GeoLite databases by MaxMind Inc., are distributed under the Creative Commons
# Attribution-ShareAlike 3.0 Unported License. The attribution requirement
# may be met by including the following in all advertising and documentation
# mentioning features of or use of this database.

pfs_version=$(cat /etc/version | cut -c 1-3)

# Application Locations
pathfetch=/usr/bin/fetch
pathtar=/usr/bin/tar
pathgunzip=/usr/bin/gunzip

# Folder Locations
pathdb=/var/db/pfblockerng
pathlog=/var/log/pfblockerng
if [ "${pfs_version}" = "2.2" ]; then
	mtype=$(/usr/bin/uname -m)
	pathshare=/usr/pbi/pfblockerng-$mtype/share/GeoIP
else
	pathshare=/usr/local/share/GeoIP
fi

# File Locations
errorlog=$pathlog/geoip.log
geoipdat=/GeoIP.dat
geoipdatv6=/GeoIPv6.dat

pathgeoipcc=$pathdb/country_continent.csv
pathgeoipcsv4=$pathdb/GeoIPCountryCSV.zip
pathgeoipcsvfinal4=$pathdb/GeoIPCountryWhois.csv
pathgeoipcsv6=$pathdb/GeoIPv6.csv.gz
pathgeoipcsvfinal6=$pathdb/GeoIPv6.csv

if [ ! -d $pathdb ]; then mkdir $pathdb; fi
if [ ! -d $pathlog ]; then mkdir $pathlog; fi

now=$(date)
echo; echo "$now - Updating pfBlockerNG - Country Database Files"
echo "pfBlockerNG uses GeoLite data created by MaxMind, available from http://www.maxmind.com"; echo

#Function to update MaxMind GeoIP Binary (For Reputation Process)
binaryupdate() {

# Download Part 1 - GeoLite IPv4 Binary Database

echo " ** Downloading MaxMind GeoLite IPv4 Binary Database (For Reputation/Alerts Processes) **"; echo
URL="http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz"
$pathfetch -v -o $pathshare$geoipdat.gz -T 20 $URL
if [ "$?" -eq "0" ]; then
	$pathgunzip -f $pathshare$geoipdat.gz
	echo; echo " ( MaxMind IPv4 GeoIP.dat has been updated )"; echo
	echo "Current Date/Timestamp:"
	/bin/ls -alh $pathshare$geoipdat
	echo
else
	echo; echo " => MaxMind IPv4 GeoIP.dat Update [ FAILED ]"; echo
	echo "MaxMind IPV4 Binary Update FAIL [ $now ]" >> $errorlog
fi

# Download Part 2 - GeoLite IPv6 Binary Database

echo; echo " ** Downloading MaxMind GeoLite IPv6 Binary Database (For Reputation/Alerts Processes) **"; echo
URL="http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz"
$pathfetch -v -o $pathshare$geoipdatv6.gz -T 20 $URL
if [ "$?" -eq "0" ]; then
	$pathgunzip -f $pathshare$geoipdatv6.gz
	echo; echo " ( MaxMind IPv6 GeoIPv6.dat has been updated )"; echo
	echo "Current Date/Timestamp:"
	/bin/ls -alh $pathshare$geoipdatv6
	echo
else
	echo; echo " => MaxMind IPv6 GeoIPv6.dat Update [ FAILED ]"; echo
	echo "MaxMind IPv6 Binary Update FAIL [ $now ]" >> $errorlog
fi
}


#Function to update MaxMind Country Code Files
csvupdate() {

# Download Part 1 - CSV IPv4 Database

echo; echo " ** Downloading MaxMind GeoLite IPv4 CSV Database **"; echo
URL="http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip"
$pathfetch -v -o $pathgeoipcsv4 -T 20 $URL
if [ "$?" -eq "0" ]; then
	$pathtar -zxvf $pathgeoipcsv4 -C $pathdb
	if [ "$?" -eq "0" ]; then
		echo; echo " ( MaxMind GeoIPCountryWhois has been updated )"; echo
		echo "Current Date/Timestamp:"
		/bin/ls -alh $pathgeoipcsvfinal4
		echo
	else
		echo; echo " => MaxMind IPv4 GeoIPCountryWhois [ FAILED ]"; echo
		echo "MaxMind CSV Database Update FAIL - Tar extract [ $now ]" >> $errorlog
	fi
else
	echo; echo " => MaxMind IPv4 CSV Download [ FAILED ]"; echo
	echo "MaxMind CSV Database Update FAIL [ $now ]" >> $errorlog
fi

# Download Part 2 - Country Definitions

echo; echo " ** Downloading MaxMind GeoLite Database Country Definition File **"; echo
URL="http://dev.maxmind.com/static/csv/codes/country_continent.csv"
$pathfetch -v -o $pathgeoipcc -T 20 $URL
if [ "$?" -eq "0" ]; then
	echo; echo " ( MaxMind ISO 3166 Country Codes has been updated. )"; echo
	echo "Current Date/Timestamp:"
	/bin/ls -alh $pathgeoipcc
	echo
else
	echo; echo " => MaxMind ISO 3166 Country Codes Update [ FAILED ]"; echo
	echo "MaxMind ISO 3166 Country Code Update FAIL [ $now ]" >> $errorlog
fi

# Download Part 3 - Country Definitions IPV6

echo " ** Downloading MaxMind GeoLite IPv6 CSV Database **"; echo
URL="http://geolite.maxmind.com/download/geoip/database/GeoIPv6.csv.gz"
$pathfetch -v -o $pathgeoipcsv6 -T 20 $URL
if [ "$?" -eq "0" ]; then
	$pathgunzip -f $pathgeoipcsv6
	echo; echo " ( MaxMind GeoIPv6.csv has been updated )"; echo
	echo "Current Date/Timestamp:"
	/bin/ls -alh $pathgeoipcsvfinal6
	echo
else
	echo; echo " => MaxMind GeoLite IPv6 Update [ FAILED ]"; echo
	echo "MaxMind GeoLite IPv6 Update FAIL [ $now ]" >> $errorlog
fi
}


# CALL APPROPRIATE PROCESSES using Script Argument $1
case $1 in
	bu)
		binaryupdate
		;;
	cu)
		csvupdate
		;;
	all)
		binaryupdate
		csvupdate
		;;
	*)
		exit
		;;
esac
exit
