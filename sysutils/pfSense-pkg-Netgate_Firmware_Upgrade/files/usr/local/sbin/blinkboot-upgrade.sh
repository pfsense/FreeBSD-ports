#!/bin/sh
#
# blinkboot-upgrade.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
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

unset tmp_dir

_exit() {
	if [ -n "${tmp_dir}" -a -d "${tmp_dir}" ]; then
		rm -rf ${tmp_dir}
	fi
	umount /mnt >/dev/null 2>&1
}

image=$(basename ${rom_path})
version="${image%-uc-*}"
cur_version=$(kenv -q smbios.bios.version 2>/dev/null)
product=$(kenv -q smbios.system.product 2>/dev/null)
base_dir=$(dirname $(realpath $0))

if [ "${product}" != "6100" ]; then
	echo "Unsupported device ${product}.  exiting."
	exit 1
fi
if [ "${cur_version}" = "${version}" ]; then
	echo "BlinkBoot is already at the latest version.  exiting."
	exit 0
fi

# Figure out EFI partition
efi_part=$(efibootmgr -v | grep -A1 '^\+' 2>/dev/null | tail -n 1 \
    | sed 's/^[[:blank:]]*//; s/:.*//')

if [ -z "${efi_part}" ]; then
	efi_part="mmcsd0p1"
fi

if [ ! -e /dev/${efi_part} ]; then
	echo "EFI partition not found"
	exit 1
fi

trap _exit 1 2 15 EXIT

# Read and save the current DMI values
tmp_dir=$(mktemp -d 2>/dev/null)

if [ -z "${tmp_dir}" ] || [ ! -d "${tmp_dir}" ]; then
	echo "Error creating temporary directory"
	exit 1
fi

# Save current DMI data
( cd ${tmp_dir} && ${base_dir}/dmistore )

# Mount the EFI partition
if ! mount -t msdosfs /dev/${efi_part} /mnt; then
	echo "Error mounting EFI partition"
	exit 1
fi

# Check if /efi is present on filesystem
if [ ! -d "/mnt/efi" ]; then
	echo "EFI filesystem does not contain /efi"
	exit 1
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
