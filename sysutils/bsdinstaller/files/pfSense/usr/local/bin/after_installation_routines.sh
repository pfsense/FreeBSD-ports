#!/bin/sh

globals_inc="/mnt/etc/inc/globals.inc"
if [ -f /mnt/etc/inc/globals_override.inc ]; then
	globals_inc="/mnt/etc/inc/globals_override.inc ${globals_inc}"
fi
product=$(cat ${globals_inc} | \
	grep product_name | \
	head -n 1 | \
	sed 's/^.*=>* *//; s/["\;,]*//g')

product=${product:-"pfSense"}

# Copy the current running systems config.xml to the target installation area.
touch /mnt/cf/conf/trigger_initial_wizard

# Updating boot loader
echo autoboot_delay=\"3\" >> /mnt/boot/loader.conf

# Set platform back to $product to prevent freesbie_1st from running
echo ${product} > /mnt/etc/platform

# Let parent script know that a install really happened
touch /tmp/install_complete

mkdir -p /mnt/var/installer_logs
cp /tmp/install.disklabel* /mnt/var/installer_logs
cp /tmp/installer.log /mnt/var/installer_logs
cp /tmp/install-session.sh /mnt/var/installer_logs
cp /tmp/new.fdisk /mnt/var/installer_logs

export ASSUME_ALWAYS_YES=true

# Remove bsdinstaller from target
/usr/sbin/pkg -c /mnt delete -f -q -y bsdinstaller

# Fix default-config package
/usr/sbin/pkg -c /mnt delete -f -q -y ${product}-default-config\*

cp -r /pkgs /mnt
if [ -f /mnt/boot.config ]; then
	/usr/sbin/pkg -c /mnt add -fq /pkgs/${product}-default-config-serial-[0-9]*.txz
else
	/usr/sbin/pkg -c /mnt add -fq /pkgs/${product}-default-config-[0-9]*.txz
fi
rm -rf /mnt/pkgs

# Copy config.xml to proper place, respecting pfi first
if [ -r /tmp/mnt/cf/conf/config.xml ]; then
	cp /tmp/mnt/cf/conf/config.xml /mnt/cf/conf/config.xml
	rm /mnt/cf/conf/trigger_initial_wizard
else
	cp /mnt/conf.default/config.xml /mnt/cf/conf/config.xml
fi

# If the platform is vmware, lets do some fixups.
if [ -f /var/IS_VMWARE ]; then
	echo "" >> /mnt/etc/sysctl.conf
	echo "kern.timecounter.hardware=i8254" >> /mnt/etc/sysctl.conf
	echo kern.hz="100" >> /mnt/boot/loader.conf
fi

# Fixup permissions on installed files
if [ -f /usr/local/share/${product}/base.mtree ]; then
	/usr/sbin/mtree -U -e -q -f /usr/local/share/${product}/base.mtree \
		-p /mnt/ > /mnt/cf/conf/mtree.log
fi

#Sync disks
/bin/sync
