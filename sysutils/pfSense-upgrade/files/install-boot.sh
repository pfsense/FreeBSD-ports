#!/bin/sh

#
# Installs/updates the necessary boot blocks for the desired boot environment
#
# Lightly tested.. Intended to be installed, but until it matures, it will just
# be a boot tool for regression testing.

# insert code here to guess what you have -- yikes!

# Minimum size of FAT filesystems, in KB.
fat32min=33292
fat16min=2100

PRODUCT="pfSense"
PRODUCT="${PRODUCT}+"

die() {
    echo "$*"
    exit 1
}

doit() {
    echo "$*"
    eval "$*"
}

#
# Translate glabel to dev node
#
glabel_dev()
{
	geom label status -s | awk -v _dev="${1##/dev/}" '
		BEGIN { _found=0; }
		{ _devs[$1]=$3; }
		END {
			if (_dev in _devs) {
				_found=1
				_dev=_devs[_dev]
			}
			print "/dev/" _dev
			if (!_found)
				exit 1
		}
	' 2>/dev/null
}

#
# Resolve glabel(s) of a device node
#
dev_glabels()
{
	glabel status -s | \
	while read -r _label _status _dev; do
		[ "${_dev}" = "${1##/dev/}" ] || continue
		echo "/dev/${_label}"
	done
}

#
# Get mount point for a device/glabel node
#
get_dev_mount() {
	local _devs="/dev/${1##/dev/}"

	# Append possible labels for the device
	_devs="${_devs} $(glabel_dev "${1}")"
	_devs="${_devs} $(dev_glabels "${1}")"

	# Scan mountpoints looking for one of _devs
	mount -p | \
	while read -r _mdev _mmp _mmore; do
		for _dev in $_devs; do
			[ "${_dev}" = "${_mdev}" ] || continue
			echo "${_mmp}"
			return
		done
	done
}

find_part() {
    dev=$1
    part=$2

    gpart show $dev | tail +2 | awk '$4 == "'$part'" { print $3; exit; }'
}

find_part_dev() {
	dev=$1
	part=$2

	gpart show -p $dev | tail +2 | awk '$4 == "'$part'" { print $3; exit; }'
}



get_uefi_bootname() {
    case ${TARGET:-$(uname -m)} in
        amd64) echo bootx64 ;;
        arm64) echo bootaa64 ;;
        i386) echo bootia32 ;;
        arm) echo bootarm ;;
        riscv) echo bootriscv64 ;;
        *) die "machine type $(uname -m) doesn't support UEFI" ;;
    esac
}

make_esp_file() {
    local file sizekb loader device stagedir fatbits efibootname

    file=$1
    sizekb=$2
    loader=$3

    if [ "$sizekb" -ge "$fat32min" ]; then
        fatbits=32
    elif [ "$sizekb" -ge "$fat16min" ]; then
        fatbits=16
    else
        fatbits=12
    fi

    stagedir=$(mktemp -d /tmp/stand-test.XXXXXX)
    mkdir -p "${stagedir}/EFI/BOOT"
    efibootname=$(get_uefi_bootname)
    cp "${loader}" "${stagedir}/EFI/BOOT/${efibootname}.efi"
    makefs -t msdos \
		-o fat_type=${fatbits} \
		-o sectors_per_cluster=1 \
		-o volume_label=EFISYS \
		-s ${sizekb}k \
		"${file}" "${stagedir}"

    rm -rf "${stagedir}"
}

make_esp_device() {
   local dev file mntpt fstype efibootname kbfree loadersize efibootfile
    local isboot1 existingbootentryloaderfile bootorder bootentry label

    # ESP device node
    dev=$1
    file=$2

    # See if we're using an existing (formatted) ESP
	if [ "$(fstyp "${dev}")" != "msdosfs" ]; then
        newfs_msdos -F 32 -c 1 -L EFISYS "${dev}" >/dev/null 2>&1
	fi

	mntpt="$(get_dev_mount "${dev}")"
	if [ -n "${mntpt}" ]; then
		umount "${mntpt}"
	fi

	mntpt="$(mktemp -d /tmp/stand-test.XXXXXX)"
	mount -t msdosfs "${dev}" "${mntpt}"
	if [ $? -ne 0 ]; then
		die "Failed to mount ${dev} as an msdosfs filesystem"
	fi

	echo "ESP ${dev} mounted on ${mntpt}"

    efibootname=$(get_uefi_bootname)
    kbfree=$(df -k "${mntpt}" | tail -1 | cut -w -f 4)
    loadersize=$(stat -f %z "${file}")
    loadersize=$((loadersize / 1024))

    # Check if /EFI/BOOT/BOOTxx.EFI is the FreeBSD boot1.efi
    # If it is, remove it to avoid leaving stale files around
    efibootfile="${mntpt}/efi/boot/${efibootname}.efi"
    if [ -f "${efibootfile}" ]; then
        isboot1=$(strings "${efibootfile}" | grep "FreeBSD EFI boot block")

        if [ -n "${isboot1}" ] && [ "$kbfree" -lt "${loadersize}" ]; then
            echo "Only ${kbfree}KB space remaining: removing old FreeBSD boot1.efi file /efi/boot/${efibootname}.efi"
            rm "${efibootfile}"
            rmdir "${mntpt}/efi/boot"
        else
            echo "${kbfree}KB space remaining on ESP: renaming old ${efibootname}.efi file /efi/boot/${efibootname}.efi /efi/boot/${efibootname}-old.efi"
            mv "${efibootfile}" "${mntpt}/efi/boot/${efibootname}-old.efi"
        fi
    fi

    if [ ! -f "${mntpt}/efi/freebsd/loader.efi" ] && [ "$kbfree" -lt "$loadersize" ]; then
        umount "${mntpt}"
		rmdir "${mntpt}"
        echo "Failed to update the EFI System Partition ${dev}"
        echo "Insufficient space remaining for ${file}"
        echo "Run e.g \"mount -t msdosfs ${dev} /mnt\" to inspect it for files that can be removed."
        die
    fi

    mkdir -p "${mntpt}/efi/freebsd"

    # Keep a copy of the existing loader.efi in case there's a problem with the new one
    if [ -f "${mntpt}/efi/freebsd/loader.efi" ] && [ "$kbfree" -gt "$((loadersize * 2))" ]; then
		echo "${kbfree}KB space remaining on ESP: renaming old loader.efi file /etc/freebsd/loader.efi /etc/freebsd/loader-old.efi"
        cp "${mntpt}/efi/freebsd/loader.efi" "${mntpt}/efi/freebsd/loader-old.efi"
    fi

    echo "Copying loader.efi to /EFI/freebsd on ESP"
    cp "${file}" "${mntpt}/efi/freebsd/loader.efi"

    # Make sure we can access efi variables
    efibootmgr > /dev/null
    if  [ $? -eq 0 ] && [ -n "${updatesystem}" ]; then
		label="${PRODUCT} (${dev##/dev/})"
        if ! efibootmgr | grep -q "${label}"; then
            echo "Creating UEFI boot entry for FreeBSD"
			efibootmgr --create --label "${label}" --loader "${mntpt}/efi/freebsd/loader.efi" > /dev/null
            if [ $? -ne 0 ]; then
                echo "Failed to create new boot entry"
            else
                # When creating new entries, efibootmgr doesn't mark them active, so we need to
                # do so. It doesn't make it easy to find which entry it just added, so rely on
                # the fact that it places the new entry first in BootOrder.
                bootorder=$(efivar --name 8be4df61-93ca-11d2-aa0d-00e098032b8c-BootOrder --print --no-name --hex | head -1)
                bootentry=$(echo "${bootorder}" | cut -w -f 3)$(echo "${bootorder}" | cut -w -f 2)
                echo "Marking UEFI boot entry ${bootentry} active"
                efibootmgr --activate -b "${bootentry}" > /dev/null
            fi
        else
            echo "Existing UEFI FreeBSD boot entry found: not creating a new one"
        fi
	fi

	# Configure for booting from removable media
	if [ ! -d "${mntpt}/efi/boot" ]; then
		mkdir -p "${mntpt}/efi/boot"
	fi

	echo "Copying ${efibootname}.efi to /efi/boot on ESP"
	cp "${file}" "${mntpt}/efi/boot/${efibootname}.efi"

	echo "Unmounting and cleaning up temporary mount point"
    umount "${mntpt}"
    rmdir "${mntpt}"

	mount -a

    echo "Finished updating ESP"
}

make_esp() {
    local file loaderfile

    file=$1
    loaderfile=$2

    if [ -f "$file" ]; then
        make_esp_file ${file} ${fat32min} ${loaderfile}
    else
        make_esp_device ${file} ${loaderfile}
    fi
}

make_esp_mbr() {
    dev=$1
    dst=$2

    part=$(find_part_dev $dev "!239")
    if [ -z "$part" ] ; then
		part=$(find_part_dev $dev "efi")
		if [ -z "$part" ] ; then
			echo -n "No ESP slice found..."
		[ -z "${AUTO}" ] && die "aborting."
			echo "skipping."
			return
	fi
    fi
    make_esp /dev/${part} ${dst}/boot/loader.efi
}

make_esp_gpt() {
    dev=$1
    dst=$2

    part=$(find_part_dev $dev "efi")
    if [ -z "$part" ] ; then
		echo -n "No ESP partition found..."
		[ -z "${AUTO}" ] && die "aborting."
		echo "skipping."
		return
    fi
    make_esp /dev/${part} ${dst}/boot/loader.efi
}

boot_nogeli_gpt_ufs_legacy() {
    dev=$1
    dst=$2

    idx=$(find_part $dev "freebsd-boot")
    if [ -z "$idx" ] ; then
		echo -n "No freebsd-boot partition found..."
		[ -z "${AUTO}" ] && die "aborting."
		echo "skipping."
		return
    fi
    doit gpart bootcode -b ${gpt0} -p ${gpt2} -i $idx $dev
}

boot_nogeli_gpt_ufs_uefi() {
    make_esp_gpt $1 $2
}

boot_nogeli_gpt_ufs_both() {
    boot_nogeli_gpt_ufs_legacy $1 $2 $3
    boot_nogeli_gpt_ufs_uefi $1 $2 $3
}

boot_nogeli_gpt_zfs_legacy() {
    dev=$1
    dst=$2

    idx=$(find_part $dev "freebsd-boot")
    if [ -z "$idx" ] ; then
		echo -n "No freebsd-boot partition found..."
		[ -z "${AUTO}" ] && die "aborting."
		echo "skipping."
		return
    fi
    doit gpart bootcode -b ${gpt0} -p ${gptzfs2} -i $idx $dev
}

boot_nogeli_gpt_zfs_uefi() {
    make_esp_gpt $1 $2
}

boot_nogeli_gpt_zfs_both() {
    boot_nogeli_gpt_zfs_legacy $1 $2 $3
    boot_nogeli_gpt_zfs_uefi $1 $2 $3
}

boot_nogeli_mbr_ufs_legacy() {
    dev=$1
    dst=$2

    doit gpart bootcode -b ${mbr0} ${dev}
    part=$(find_part_dev $dev "freebsd")
    if [ -z "$part" ] ; then
		echo -n "No freebsd slice found..."
		[ -z "${AUTO}" ] && die "aborting."
		echo "skipping."
		return
    fi
    doit gpart bootcode -b ${mbr2} ${part}
}

boot_nogeli_mbr_ufs_uefi() {
    make_esp_mbr $1 $2
}

boot_nogeli_mbr_ufs_both() {
    boot_nogeli_mbr_ufs_legacy $1 $2 $3
    boot_nogeli_mbr_ufs_uefi $1 $2 $3
}

dev_pool()
{
	zpool list -HPv | awk -v _dev="${1}" '
		BEGIN {
			_pool="";
			_prevdev=0;
		}
		$1 == _dev {
			if (_pool)
				print _pool;
			exit
		}
		$1 !~ /^\/.*$/ {
			if (!_pool || _prevdev) {
				_pool=$1;
				_prevdev=0;
			}
		}
		$1 ~ /^\/.*$/ {
			if (_pool)
				_prevdev=1
		}
	' 2>/dev/null
}

boot_nogeli_mbr_zfs_legacy() {
    dev=$1
    dst=$2

    # search to find the BSD slice
    part1=$(find_part_dev $dev "freebsd")
    if [ -z "$part1" ] ; then
	echo -n "No BSD slice found..."
	[ -z "${AUTO}" ] && die "aborting."
	echo "skipping."
	return
    fi

    # search to find bootpool vdev
    part2=$(find_part_dev ${part1} "freebsd-zfs")
    if [ -z "$part2" ] ; then
	echo -n "No freebsd-zfs slice found"
	[ -z "${AUTO}" ] && die "aborting."
	echo "skipping."
	return
    fi

    reimport=1
    if ! zpool status bootpool 1>/dev/null 2>&1; then
	echo "Importing bootpool"
	doit zpool import -f bootpool 1>/dev/null 2>&1
	unset reimport
    fi

    mbr_tmp="$(mktemp -q /tmp/mbr.XXXXX)"
    zfsboot_tmp="$(mktemp -q /tmp/zfsboot.XXXXX)"
    zfsboot1_tmp="$(mktemp -q /tmp/zfsboot1.XXXXX)"

    doit cp "${mbr0}" "${mbr_tmp}"
    doit cp "${zfsboot0}" "${zfsboot_tmp}"

    echo "Exporting bootpool"
    doit zpool export -f bootpool 1>/dev/null 2>&1

    # search to find the freebsd-zfs partition within the slice
    # Or just assume it is 'a' because it has to be since it fails otherwise
    doit gpart bootcode -b ${mbr_tmp} ${dev}
    dd if="${zfsboot_tmp}" of="${zfsboot1_tmp}" count=1
    doit gpart bootcode -b "${zfsboot1_tmp}" ${part1}	# Put boot1 into the start of part
    sysctl kern.geom.debugflags=0x10		# Put boot2 into ZFS boot slot
    doit dd if="${zfsboot_tmp}" of=/dev/${part2} skip=1 seek=1024
    sysctl kern.geom.debugflags=0x0

	if [ -n "${reimport}" ]; then
		echo "Reimporting bootpool"
		doit zpool import -f bootpool
	fi

	doit rm -f ${mbr_tmp} ${zfsboot_tmp} ${zfsboot1_tmp}
}

boot_nogeli_mbr_zfs_uefi() {
    make_esp_mbr $1 $2
}

boot_nogeli_mbr_zfs_both() {
    boot_nogeli_mbr_zfs_legacy $1 $2 $3
    boot_nogeli_mbr_zfs_uefi $1 $2 $3
}

boot_geli_gpt_ufs_legacy() {
    boot_nogeli_gpt_ufs_legacy $1 $2 $3
}

boot_geli_gpt_ufs_uefi() {
    boot_nogeli_gpt_ufs_uefi $1 $2 $3
}

boot_geli_gpt_ufs_both() {
    boot_nogeli_gpt_ufs_both $1 $2 $3
}

boot_geli_gpt_zfs_legacy() {
    boot_nogeli_gpt_zfs_legacy $1 $2 $3
}

boot_geli_gpt_zfs_uefi() {
    boot_nogeli_gpt_zfs_uefi $1 $2 $3
}

boot_geli_gpt_zfs_both() {
    boot_nogeli_gpt_zfs_both $1 $2 $3
}

# GELI+MBR is not a valid configuration
boot_geli_mbr_ufs_legacy() {
    exit 1
}

boot_geli_mbr_ufs_uefi() {
    exit 1
}

boot_geli_mbr_ufs_both() {
    exit 1
}

boot_geli_mbr_zfs_legacy() {
    exit 1
}

boot_geli_mbr_zfs_uefi() {
    exit 1
}

boot_geli_mbr_zfs_both() {
    exit 1
}

usage() {
	printf 'Usage: %s [-h] [-b bios] [-d destdir] -f fs [-g geli] [-o optargs] -s scheme <bootdev>\n' "$0"
	printf 'Options:\n'
	printf ' bootdev       Device to install the boot code on\n'
	printf ' -b bios       Bios type: legacy, uefi, auto or both\n'
	printf ' -d destdir    Destination filesystem root\n'
	printf ' -f fs         Filesystem type: ufs or zfs\n'
	printf ' -g geli       geli, yes or no\n'
	printf ' -h            This help/usage text\n'
	printf ' -u            Run commands such as efibootmgr to update the\n'
	printf '               currently running system\n'
	printf ' -o optargs    Optional arguments\n'
	printf ' -s scheme     mbr or gpt\n'
	exit 0
}

# Note: we really don't support geli boot in this script yet.
geli=nogeli

# Default bios to "both"
bios=both

while getopts "b:d:f:g:ho:s:u" opt; do
    case "$opt" in
	b)
	    bios=${OPTARG}
		if [ "${bios}" = "auto" ]; then
			AUTO=1
			bios="both"
		fi
	    ;;
	d)
	    srcroot=${OPTARG}
	    ;;
	f)
	    fs=${OPTARG}
	    ;;
	g)
	    case ${OPTARG} in
		[Yy][Ee][Ss]|geli) geli=geli ;;
		*) geli=nogeli ;;
	    esac
	    ;;
	u)
	    updatesystem=1
	    ;;
	o)
	    opts=${OPTARG}
	    ;;
	s)
	    scheme=${OPTARG}
	    ;;

	?|h)
        usage
        ;;
    esac
done

if [ -n "${scheme}" ] && [ -n "${fs}" ] && [ -n "${bios}" ]; then
    shift $((OPTIND-1))
    dev=$1
fi

# For gpt, we need to install pmbr as the primary boot loader
# it knows about
gpt0=${srcroot}/boot/pmbr
gpt2=${srcroot}/boot/gptboot
gptzfs2=${srcroot}/boot/gptzfsboot

# For MBR, we have lots of choices, but select mbr, boot0 has issues with UEFI
mbr0=${srcroot}/boot/mbr
mbr2=${srcroot}/boot/boot
zfsboot0=${srcroot}/boot/zfsboot

# sanity check here

# Check if we've been given arguments. If not, this script is probably being
# sourced, so we shouldn't run anything.
if [ -n "${dev}" ]; then
	eval boot_${geli}_${scheme}_${fs}_${bios} $dev $srcroot $opts || echo "Invalid configuration: ${geli}-${scheme}-${fs}-${bios}"
fi
