#!/bin/sh

# Copy the current running systems config.xml to the target installation area.
touch /mnt/cf/conf/trigger_initial_wizard

# Updating boot loader
echo autoboot_delay=\"3\" >> /mnt/boot/loader.conf

# Set platform back to pfSense to prevent freesbie_1st from running
echo "pfSense" > /mnt/etc/platform

# Let parent script know that a install really happened
touch /tmp/install_complete

mkdir -p /mnt/var/installer_logs
cp /tmp/install.disklabel* /mnt/var/installer_logs
cp /tmp/installer.log /mnt/var/installer_logs
cp /tmp/install-session.sh /mnt/var/installer_logs
cp /tmp/new.fdisk /mnt/var/installer_logs

# Remove bsdinstaller from target
/usr/bin/env ASSUME_ALWAYS_YES=true /usr/sbin/pkg -c /mnt delete -f -q -y bsdinstaller

# Fix default-config package
/usr/bin/env ASSUME_ALWAYS_YES=true /usr/sbin/pkg -c /mnt delete -f -q -y pfSense-default-config\*
cp -r /pkgs /mnt
if [ -f /mnt/boot.config ]; then /usr/bin/env ASSUME_ALWAYS_YES=true /usr/sbin/pkg -c /mnt add -fq /pkgs/pfSense-default-config-serial-[0-9]*.txz; else /usr/bin/env ASSUME_ALWAYS_YES=true /usr/sbin/pkg -c /mnt add -fq /pkgs/pfSense-default-config-[0-9]*.txz; fi;
rm -rf /mnt/pkgs

# If the platform is vmware, lets do some fixups.
if [ -f /var/IS_VMWARE ]; then echo "" >> /mnt/etc/sysctl.conf; echo "kern.timecounter.hardware=i8254" >> /mnt/etc/sysctl.conf; echo kern.hz="100" >> /mnt/boot/loader.conf; fi;

# Fixup permissions on installed files
if [ -f /usr/local/share/pfSense/base.mtree ]; then /usr/sbin/mtree -U -e -q -f /usr/local/share/pfSense/base.mtree -p /mnt/ > /mnt/cf/conf/mtree.log; fi;

#Sync disks
/bin/sync
