#!/bin/sh
#
# blinkboot-upgrade.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
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

if [ -z "${1}" ]; then
	echo "Missing ROM path"
	exit 1
fi

rom_path="${1}"

if [ ! -f "${rom_path}" ]; then
	echo "Cannot find the BlinkBoot image.  exiting."
	exit 1
fi

image=$(basename ${rom_path})
version=$(echo ${image} | cut -d- -f2- | sed 's/-2Ct-uc/t-uc/' | tr -d '[.|\-|a-z|A-Z]')
cur_version=$(kenv -q smbios.bios.version 2>/dev/null | cut -d- -f2- | sed 's/-2Ct/t/' | tr -d '[.|a-z|A-Z]')
uc_cur_ver=$(sysctl -n dev.cordbuc.0.version 2> /dev/null)
cur_version=${cur_version}${uc_cur_ver}
product=$(kenv -q smbios.system.product 2>/dev/null)
base_dir=$(dirname $(realpath $0))

get_diskdevice() {
	sysctl -b kern.geom.conftxt |
	while read line
	do
		local _type=$(echo ${line} | awk '{printf $2}')
		local _dev=$(echo ${line} | awk '{printf $3}')
		if [ "${_type}" = "DISK" -a -n "$(echo ${1} | grep ${_dev})" ]; then
			echo -n ${_dev}
			return
		fi
	done
}

find_root_device() {
	FSTYPE=$(mount -p | awk '{ if ( $2 == "/") { print $3 }}')
	FSDEV=$(mount -p | awk '{ if ( $2 == "/") { print $1 }}')
	case "$FSTYPE" in
	ufs)
		rootdev=${FSDEV#/dev/}
		;;
	zfs)
		pool=${FSDEV%%/*}
		rootdev=$(zpool list -v $pool | awk 'END { print $1 }')
		;;
	*)
		echo "Don't know how to find the root filesystem type: $FSTYPE"
		exit 1
	esac
	if [ x"$rootdev" = x"${rootdev%/*}" ]; then
		# raw device
		rawdev="$rootdev"
	else
		rawdev=$(glabel status | awk 'index("'"$rootdev"'", $1) { print $3 }')
		if [ x"$rawdev" = x"" ]; then
			echo "Can't figure out device for: $rootdev"
			exit 1
		fi
	fi
	if [ x"diskid" = x"${rootdev%/*}" ]; then
		search=$rootdev
	else
		search=$rawdev
	fi
	diskdev=$(get_diskdevice ${search})
	if [ -z "${diskdev}" ]; then
		diskdev=${rootdev}
	fi
	echo -n ${diskdev}
}

check_efi_partition() {
	local _disk=${1}
	local _part=${2}
	sysctl kern.geom.conftxt | grep "${_disk}p${_part}" | awk '{ if ($11 == "efi" && $13 == "GPT") { print "ok" }}'
}

if [ "${product}" != "4100" -a "${product}" != "6100" ]; then
	echo "Unsupported device ${product}.  exiting."
	exit 1
fi
if [ "${cur_version}" -ge "${version}" ]; then
	echo "BlinkBoot is already at the latest version.  exiting."
	exit 0
fi

# Read and save the current DMI values
tmp_dir=$(mktemp -d 2>/dev/null)

if [ -z "${tmp_dir}" ] || [ ! -d "${tmp_dir}" ]; then
	echo "Error creating temporary directory"
	exit 1
fi

# Save current DMI data
( cd ${tmp_dir} && ${base_dir}/dmistore )

# Mount the EFI partition
DISK=$(find_root_device)
EFIPART=$(check_efi_partition ${DISK} 1)
if [ "${EFIPART}" == "ok" ]; then
	if ! mount -t msdosfs /dev/${DISK}p1 /mnt; then
		echo "Error mounting EFI partition"
		rm -rf ${tmp_dir}
		exit 1
	fi
fi
mkdir -p /mnt/efi/UpdateCapsule

# Copy the BlinkBoot image
if ! cp ${rom_path} /mnt/efi/UpdateCapsule/HARRISONVILLE.fd; then
	echo "Error copying BlinkBoot upgrade to EFI partition"
	rm -rf ${tmp_dir}
	umount /mnt
	exit 1
fi

# Copy the DMI data
if ! cp ${tmp_dir}/* /mnt/efi/UpdateCapsule; then
	echo "Error copying DMI data to EFI partition"
	rm -rf ${tmp_dir}
	umount /mnt
	exit 1
fi

rm -rf ${tmp_dir}
umount /mnt

# Set the update flag
echo -n "0100" | efivar -w -n 4b48429c-a888-418c-898d-a5f4faac567e-secureflashupdate

echo "System is ready for BlinkBoot upgrade.  Reboot it now!"
