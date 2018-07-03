<?php

/*
 * snmptt.ini.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Create snmptt.ini.php 
$snmpttini =<<<EOF
#
# SNMPTT v1.4 Configuration File
#
# Linux / Unix
#

[General]
# Name of this system for \$H variable.  If blank, system name will be the computer's
# hostname via Sys::Hostname.
snmptt_system_name = {$snmptt_config['snmptt_system_name']} 

# Set to either 'standalone' or 'daemon'
# standalone: snmptt called from snmptrapd.conf
# daemon: snmptrapd.conf calls snmptthandler
# Ignored by Windows.  See documentation
mode = {$snmptt_config['mode']} 

# Set to 1 to allow multiple trap definitions to be executed for the same trap.
# Set to 0 to have it stop after the first match.
# This option should normally be set to 1.  See the section 'SNMPTT.CONF Configuration 
# file Notes' in the SNMPTT documentation for more information.
# Note: Wildcard matches are only matched if there are NO exact matches.  This takes
# 	into consideration the NODES list.  Therefore, if there is a matching trap, but
#	the NODES list prevents it from being considered a match, the wildcard entry will
#	only be used if there are no other exact matches.
multiple_event = {$snmptt_config['multiple_event']}

# SNMPTRAPD passes the IP address of device sending the trap, and the IP address of the
# actual SNMP agent.  These addresses could differ if the trap was sent on behalf of another
# device (relay, proxy etc).
# If DNS is enabled, the agent IP address is converted to a host name using a DNS lookup
# (which includes the local hosts file, depending on how the OS is configured).  This name
# will be used for: NODES entry matches, hostname field in logged traps (file / database), 
# and the \$A variable.  Host names on the NODES line will be resolved and the IP address 
# will then be used for comparing.
# Set to 0 to disable DNS resolution
# Set to 1 to enable DNS resolution
dns_enable = {$snmptt_config['dns_enable']}

# Set to 0 to enable the use of FQDN (Fully Qualified Domain Names).  If a host name is
# passed to SNMPTT that contains a domain name, it will not be altered in any way by
# SNMPTT.  This also affects resolve_value_ip_addresses.
# Set to 1 to have SNMPTT strip the domain name from the host name passed to it.  For 
# example, server01.domain.com would be changed to server01
# Set to 2 to have SNMPTT strip the domain name from the host name passed to it
# based on the list of domains in strip_domain_list
strip_domain = {$snmptt_config['strip_domain']}

# List of domain names that should be stripped when strip_domain is set to 2.
# List can contain one or more domains.  For example, if the FQDN of a host is
# server01.city.domain.com and the list contains domain.com, the 'host' will be
# set as server01.city.
strip_domain_list = <<END
{$strip_domain_list}
END

# Configures how IP addresses contained in the VALUE of the variable bindings are handled.
# This only applies to the values for \$n, \$+n, \$-n, \$vn, \$+*, \$-*.
# Set to 0 to disable resolving ip address to host names
# Set to 1 to enable resolving ip address to host names
# Note: net_snmp_perl_enable *must* be enabled.  The strip_domain settings influence the
# format of the resolved host name.  DNS must be enabled (dns_enable)
resolve_value_ip_addresses = {$snmptt_config['resolve_value_ip_addresses']}

# Set to 1 to enable the use of the Perl module from the UCD-SNMP / NET-SNMP package.
# This is required for \$v variable substitution to work, and also for some other options
# that are enabled in this .ini file.
# Set to 0 to disable the use of the Perl module from the UCD-SNMP / NET-SNMP package.
# Note: Enabling this with stand-alone mode can cause SNMPTT to run very slowly due to
#       the loading of the MIBS at startup.
net_snmp_perl_enable = {$snmptt_config['net_snmp_perl_enable']}

# Set to 1 to enable caching of OID and ENUM translations when net_snmp_perl_enable is 
# enabled.  Enabling this should result in faster translations.
# Set to 0 to disable caching.
# Note: Restart SNMPTT after updating the MIB files for Net-SNMP, otherwise the cache may
# contain inaccurate data.  Defaults to 1.
net_snmp_perl_cache_enable = {$snmptt_config['net_snmp_perl_cache_enable']}

# This sets the best_guess parameter used by the UCD-SNMP / NET-SNMP Perl module for 
# translating symbolic nams to OIDs and vice versa.
# For UCD-SNMP, and Net-SNMP 5.0.8 and previous versions, set this value to 0.
# For Net-SNMP 5.0.9, or any Net-SNMP with patch 722075 applied, set this value to 2.
# A value of 2 is equivalent to -IR on Net-SNMP command line utilities.
# UCD-SNMP and Net-SNMP 5.0.8 and previous may not be able to translate certain formats of
# symbolic names such as RFC1213-MIB::sysDescr.  Net-SNMP 5.0.9 or patch 722075 will allow
# all possibilities to be translated.  See the FAQ section in the README for more info
net_snmp_perl_best_guess = {$snmptt_config['net_snmp_perl_best_guess']}

# Configures how the OID of the received trap is handled when outputting to a log file /
# database.  It does NOT apply to the \$O variable.
# Set to 0 to use the default of numerical OID
# Set to 1 to translate the trap OID to short text (symbolic form) (eg: linkUp)
# Set to 2 to translate the trap OID to short text with module name (eg: IF-MIB::linkUp)
# Set to 3 to translate the trap OID to long text (eg: iso...snmpTraps.linkUp)
# Set to 4 to translate the trap OID to long text with module name (eg: 
# IF-MIB::iso...snmpTraps.linkUp)
# Note: -The output of the long format will vary depending on the version of Net-SNMP you
#        are using.
#       -net_snmp_perl_enable *must* be enabled
#       -If using database logging, ensure the trapoid column is large enough to hold the
#        entire line
translate_log_trap_oid = {$snmptt_config['translate_log_trap_oid']}

# Configures how OIDs contained in the VALUE of the variable bindings are handled.
# This only applies to the values for \$n, \$+n, \$-n, \$vn, \$+*, \$-*.  For substitutions
# that include variable NAMES (\$+n etc), only the variable VALUE is affected.
# Set to 0 to disable translating OID values to text (symbolic form)
# Set to 1 to translate OID values to short text (symbolic form) (eg: BuildingAlarm)
# Set to 2 to translate OID values to short text with module name (eg: UPS-MIB::BuildingAlarm)
# Set to 3 to translate OID values to long text (eg: iso...upsAlarm.BuildingAlarm)
# Set to 4 to translate OID values to long text with module name (eg: 
# UPS-MIB::iso...upsAlarm.BuildingAlarm)
# For example, if the value contained: 'A UPS Alarm (.1.3.6.1.4.1.534.1.7.12) has cleared.',
# it could be translated to: 'A UPS Alarm (UPS-MIB::BuildingAlarm) has cleared.'
# Note: net_snmp_perl_enable *must* be enabled
translate_value_oids = {$snmptt_config['translate_value_oids']}

# Configures how the symbolic enterprise OID will be displayed for \$E.
# Set to 1, 2, 3 or 4.  See translate_value_oids options 1,2,3 and 4. 
# Note: net_snmp_perl_enable *must* be enabled
translate_enterprise_oid_format = {$snmptt_config['translate_enterprise_oid_format']}

# Configures how the symbolic trap OID will be displayed for \$O.
# Set to 1, 2, 3 or 4.  See translate_value_oids options 1,2,3 and 4. 
# Note: net_snmp_perl_enable *must* be enabled
translate_trap_oid_format = {$snmptt_config['translate_trap_oid_format']}

# Configures how the symbolic trap OID will be displayed for \$v, \$-n, \$+n, \$-* and \$+*.
# Set to 1, 2, 3 or 4.  See translate_value_oids options 1,2,3 and 4. 
# Note: net_snmp_perl_enable *must* be enabled
translate_varname_oid_format = {$snmptt_config['translate_varname_oid_format']}

# Set to 0 to disable converting INTEGER values to enumeration tags as defined in the 
# MIB files
# Set to 1 to enable converting INTEGER values to enumeration tags as defined in the 
# MIB files
# Example: moverDoorState:open instead of moverDoorState:2
# Note: net_snmp_perl_enable *must* be enabled
translate_integers = {$snmptt_config['translate_integers']}

# Allows you to set the MIBS environment variable used by SNMPTT
# Leave blank or comment out to have the systems enviroment settings used
# To have all MIBS processed, set to ALL
# See the snmp.conf manual page for more info
#mibs_environment = ALL

# Set what is used to separate variables when wildcards are expanded on the FORMAT /
# EXEC line.  Defaults to a space.  Value MUST be within quotes.  Can contain 1 or 
# more characters
wildcard_expansion_separator = "{$wildcard_expansion_separator}"

# Set to 1 to allow unsafe REGEX code to be executed.
# Set to 0 to prevent unsafe REGEX code from being executed (default).
# Enabling unsafe REGEX code will allow variable interopolation and the use of the e
# modifier to allow statements such as substitution with captures such
# as:            (one (two) three)(five \$1 six)
# which outputs: five two six
# or:            (one (two) three)("five ".length(\$1)." six")e
# which outputs: five 3 six
#
# This is considered unsafe because the contents of the regular expression 
# (right) is executed (eval) by Perl which *could contain unsafe code*.
# BE SURE THAT THE SNMPTT CONFIGURATION FILES ARE SECURE!
allow_unsafe_regex = {$snmptt_config['allow_unsafe_regex']}

# Set to 1 to have the backslash (escape) removed from quotes passed from
# snmptrapd.  For example, \" would be changed to just "
# Set to 0 to disable
remove_backslash_from_quotes = {$snmptt_config['remove_backslash_from_quotes']}

# Set to 1 to have NODES files loaded each time a trap is processed.
# Set to 0 to have all NODES files loaded when the snmptt.conf files are loaded.
# If NODES files are used (files that contain lists of NODES), then setting to 1
# will cause the list to be loaded each time an EVENT is processed that uses
# NODES files.  This will allow the NODES file to be modified while SNMPTT is 
# running but can result in many file reads depending on the number of traps
# received.  Defaults to 0
dynamic_nodes = {$snmptt_config['dynamic_nodes']}

# This option allows you to use the \$D substitution variable to include the
# description text from the SNMPTT.CONF or MIB files.
# Set to 0 to disable the \$D substitution variable.  If \$D is used, nothing
#  will be outputted.
# Set to 1 to enable the \$D substitution variable and have it use the
#  descriptions stored in the SNMPTT .conf files.  Enabling this option can
#  greatly increase the amount of memory used by SNMPTT.
# Set to 2 to enable the \$D substitution variable and have it use the
#  description from the MIB files.  This enables the UCD-SNMP / NET-SNMP Perl 
#  module save_descriptions variable.  Enabling this option can greatly 
#  increase the amount of memory used by the Net-SNMP SNMP Perl module, which 
#  will result in an increase of memory usage by SNMPTT.
description_mode = {$snmptt_config['description_mode']}

# Set to 1 to remove any white space at the start of each line from the MIB
# or SNMPTT.CONF description when description_mode is set to 1 or 2.
description_clean = {$snmptt_config['description_clean']}

# Warning: Experimental.  Not recommended for production environments.
#          When threads are enabled, SNMPTT may quit unexpectedly.
# Set to 1 to enable threads (ithreads) in Perl 5.6.0 or higher.  If enabled,
# EXEC will launch in a thread to allow SNMPTT to continue processing other
# traps.  See also threads_max.
# Set to 0 to disable threads (ithreads).
# Defaults to 0
threads_enable = {$snmptt_config['threads_enable']}

# Warning: Experimental.  Not recommended for production environments.
#          When threads are enabled, SNMPTT may quit unexpectedly.
# This option allows you to set the maximum number of threads that will 
# execute at once.  Defaults to 10
threads_max = {$snmptt_config['threads_max']}

# The date format for \$x in strftime() format.  If not defined, defaults 
# to %a %b %e %Y.
date_format = {$snmptt_config['date_format']}

# The time format for \$X in strftime() format.  If not defined, defaults 
# to %H:%M:%S.
time_format = {$snmptt_config['time_format']}

# The date time format in strftime() format for the date/time when logging 
# to standard output, snmptt log files (log_file) and the unknown log file 
# (unknown_trap_log_file).  Defaults to localtime().  For SQL, see 
# date_time_format_sql.
# Example:  %a %b %e %Y %H:%M:%S
date_time_format = {$snmptt_config['date_time_format']}

[DaemonMode]
# Set to 1 to have snmptt fork to the background when run in daemon mode
# Ignored by Windows.  See documentation
daemon_fork = 1

# Set to the numerical user id (eg: 500) or textual user id (eg: snmptt)
# that snmptt should change to when running in daemon mode.  Leave blank
# to disable.  The user used should have read/write access to all log
# files, the spool folder, and read access to the configuration files.
# Only use this if you are starting snmptt as root.
# A second (child) process will be started as the daemon_uid user so
# there will be two snmptt processes running.  The first process will 
# continue to run as the user that ran snmptt (root), waiting for the
# child to quit.  After the child quits, the parent process will remove 
# the snmptt.pid file and exit. 
daemon_uid = snmptt

# Complete path of file to store process ID when running in daemon mode.
pid_file = /var/run/snmptt/snmptt.pid

# Directory to read received traps from.  Ex: /var/spool/snmptt/
# Don't forget the trailing slash!
spool_directory = /var/spool/snmptt/

# Amount of time in seconds to sleep between processing spool files
sleep = {$snmptt_config['sleep']}

# Set to 1 to have SNMPTT use the time that the trap was processed by SNMPTTHANDLER
# Set to 0 to have SNMPTT use the time the trap was processed.  Note:  Using 0 can
# result in the time being off by the number of seconds used for 'sleep'
use_trap_time = {$snmptt_config['use_trap_time']}

# Set to 0 to have SNMPTT erase the spooled trap file after it attempts to process
# the trap even if it did not successfully log the trap to any of the log systems.
# Set to 1 to have SNMPTT erase the spooled trap file only after it successfully
# logs to at least ONE log system.
# Set to 2 to have SNMPTT erase the spooled trap file only after it successfully
# logs to ALL of the enabled log systems.  Warning:  If multiple log systems are
# enabled and only one fails, the other log system will continuously be logged to
# until ALL of the log systems function.
# The recommended setting is 1 with only one log system enabled.
keep_unlogged_traps = {$snmptt_config['keep_unlogged_traps']}

# How often duplicate traps will be processed.  An MD5 hash of all incoming traps
# is stored in memory and is used to check for duplicates.  All variables except for
# the uptime variable are used when calculating the MD5.  The larger this variable,
# the more memory snmptt will require.
# Note:  In most cases it may be a good idea to enable this but sometimes it can have a 
#        negative effect.  For example, if you are trying to troubleshoot a wireless device
#        that keeps losing it's connection you may want to disable this so that you see
#        all the associations and disassociations.
# 5 minutes = 300
# 10 minutes = 600
# 15 minutes = 900
duplicate_trap_window = {$snmptt_config['duplicate_trap_window']}

[Logging]
# Set to 1 to enable messages to be sent to standard output, or 0 to disable.
# Would normally be disabled unless you are piping this program to another
stdout_enable = {$snmptt_config['stdout_enable']}

# Set to 1 to enable text logging of *TRAPS*.  Make sure you specify a log_file 
# location
log_enable = {$snmptt_config['log_enable']}

# Log file location.  The COMPLETE path and filename.  Ex: '/var/log/snmptt/snmptt.log'
log_file = {$snmptt_config['log_file']}

# Set to 1 to enable text logging of *SNMPTT system errors*.  Make sure you 
# specify a log_system_file location
log_system_enable = {$snmptt_config['log_system_enable']}

# Log file location.  The COMPLETE path and filename.  
# Ex: '/var/log/snmptt/snmpttsystem.log'
log_system_file = {$snmptt_config['log_system_file']} 

# Set to 1 to enable logging of unknown traps.  This should normally be left off
# as the file could grow large quickly.  Used primarily for troubleshooting.  If
# you have defined a trap in snmptt.conf, but it is not executing, enable this to
# see if it is being considered an unknown trap due to an incorrect entry or 
# simply missing from the snmptt.conf file.
# Unknown traps can be logged either a text file, a SQL table or both.
# See SQL section to define a SQL table to log unknown traps to.
unknown_trap_log_enable = {$snmptt_config['unknown_trap_log_enable']}

# Unknown trap log file location.  The COMPLETE path and filename.  
# Ex: '/var/log/snmptt/snmpttunknown.log'
# Leave blank to disable logging to text file if logging to SQL is enabled
# for unknown traps
unknown_trap_log_file = {$snmptt_config['unknown_trap_log_file']}

# How often in seconds statistics should be logged to syslog or the event log.
# Set to 0 to disable
# 1 hour = 216000
# 12 hours = 2592000
# 24 hours = 5184000
statistics_interval = {$snmptt_config['statistics_interval']}

# Set to 1 to enable logging of *TRAPS* to syslog.  If you do not have the Sys::Syslog
# module then disable this.  Windows users should disable this.
syslog_enable = {$snmptt_config['syslog_enable']}

# Syslog facility to use for logging of *TRAPS*.  For example: 'local0'
syslog_facility = {$snmptt_config['syslog_facility']}

# Set the syslog level for *TRAPS* based on the severity level of the trap
# as defined in the snmptt.conf file.  Values must be one per line between 
# the syslog_level_* and END lines, and are not case sensitive.  For example:
#   Warning
#   Critical
# Duplicate definitions will use the definition with the higher severity.
syslog_level_debug = <<END
END
syslog_level_info = <<END
END
syslog_level_notice = <<END
END
syslog_level_warning = <<END
END
syslog_level_err = <<END
END
syslog_level_crit = <<END
END
syslog_level_alert = <<END
END

# Syslog default level to use for logging of *TRAPS*.  For example: warning
# Valid values: emerg, alert, crit, err, warning, notice, info, debug 
syslog_level = {$snmptt_config['syslog_level']}

# Set to 1 to enable logging of *SNMPTT system errors* to syslog.  If you do not have the 
# Sys::Syslog module then disable this.  Windows users should disable this.
syslog_system_enable = {$snmptt_config['syslog_system_enable']}

# Syslog facility to use for logging of *SNMPTT system errors*.  For example: 'local0'
syslog_system_facility = {$snmptt_config['syslog_system_facility']} 

# Syslog level to use for logging of *SNMPTT system errors*..  For example: 'warning'
# Valid values: emerg, alert, crit, err, warning, notice, info, debug 
syslog_system_level = {$snmptt_config['syslog_system_level']}

[SQL]
# Determines if the enterprise column contains the numeric OID or symbolic OID
# Set to 0 for numeric OID
# Set to 1 for symbolic OID
# Uses translate_enterprise_oid_format to determine format
# Note: net_snmp_perl_enable *must* be enabled
db_translate_enterprise = 0

# FORMAT line to use for unknown traps.  If not defined, defaults to \$-*.
db_unknown_trap_format = '\$-*'

# List of custom SQL column names and values for the table of received traps
# (defined by *_table below).  The format is
#   column name
#   value
#
# For example:
#
#   binding_count
#   \$#
#   uptime2
#   The agent has been up for \$T.
sql_custom_columns = <<END
END

# List of custom SQL column names and values for the table of unknown traps
# (defined by *_table_unknown below).  See sql_custom_columns for the format.
sql_custom_columns_unknown = <<END
END

# MySQL: Set to 1 to enable logging to a MySQL database via DBI (Linux / Windows)
# This requires DBI:: and DBD::mysql
mysql_dbi_enable = 0

# MySQL: Hostname of database server (optional - default localhost)
mysql_dbi_host = localhost

# MySQL: Port number of database server (optional - default 3306)
mysql_dbi_port = 3306

# MySQL: Database to use
mysql_dbi_database = snmptt

# MySQL: Table to use
mysql_dbi_table = snmptt

# MySQL: Table to use for unknown traps
# Leave blank to disable logging of unknown traps to MySQL
# Note: unknown_trap_log_enable must be enabled.
mysql_dbi_table_unknown = snmptt_unknown

# MySQL: Table to use for statistics
# Note: statistics_interval must be set.  See also stat_time_format_sql.
#mysql_dbi_table_statistics = snmptt_statistics
mysql_dbi_table_statistics = 

# MySQL: Username to use
mysql_dbi_username = snmpttuser

# MySQL: Password to use
mysql_dbi_password = password

# MySQL: Whether or not to 'ping' the database before attempting an INSERT
# to ensure the connection is still valid.  If *any* error is generate by 
# the ping such as 'Unable to connect to database', it will attempt to 
# re-create the database connection.
# Set to 0 to disable
# Set to 1 to enable
# Note:  This has no effect on mysql_ping_interval.
mysql_ping_on_insert = 1

# MySQL: How often in seconds the database should be 'pinged' to ensure the
# connection is still valid.  If *any* error is generate by the ping such as 
# 'Unable to connect to database', it will attempt to re-create the database
# connection.  Set to 0 to disable pinging.
# Note:  This has no effect on mysql_ping_on_insert.
# disabled = 0
# 5 minutes = 300
# 15 minutes = 900
# 30 minutes = 1800
mysql_ping_interval = 300

# PostgreSQL: Set to 1 to enable logging to a PostgreSQL database via DBI (Linux / Windows)
# This requires DBI:: and DBD::PgPP
postgresql_dbi_enable = 0

# Set to 0 to use the DBD::PgPP module
# Set to 1 to use the DBD::Pg module
postgresql_dbi_module = 0

# Set to 0 to disable host and port network support
# Set to 1 to enable host and port network support
# If set to 1, ensure PostgreSQL is configured to allow connections via TCPIP by setting 
# tcpip_socket = true in the \$PGDATA/postgresql.conf file, and adding the ip address of 
# the SNMPTT server to \$PGDATApg_hba.conf.  The common location for the config files for
# RPM installations of PostgreSQL is /var/lib/pgsql/data.  
postgresql_dbi_hostport_enable = 0

# PostgreSQL: Hostname of database server (optional - default localhost)
postgresql_dbi_host = localhost

# PostgreSQL: Port number of database server (optional - default 5432)
postgresql_dbi_port = 5432

# PostgreSQL: Database to use
postgresql_dbi_database = snmptt

# PostgreSQL: Table to use for unknown traps
# Leave blank to disable logging of unknown traps to PostgreSQL
# Note: unknown_trap_log_enable must be enabled.
postgresql_dbi_table_unknown = snmptt_unknown

# PostgreSQL: Table to use for statistics
# Note: statistics_interval must be set.  See also stat_time_format_sql.
#postgresql_dbi_table_statistics = snmptt_statistics
postgresql_dbi_table_statistics = 

# PostgreSQL: Table to use
postgresql_dbi_table = snmptt

# PostgreSQL: Username to use
postgresql_dbi_username = snmpttuser

# PostgreSQL: Password to use
postgresql_dbi_password = password

# PostgreSQL: Whether or not to 'ping' the database before attempting an INSERT
# to ensure the connection is still valid.  If *any* error is generate by 
# the ping such as 'Unable to connect to database', it will attempt to 
# re-create the database connection.
# Set to 0 to disable
# Set to 1 to enable
# Note:  This has no effect on postgresqll_ping_interval.
postgresql_ping_on_insert = 1

# PostgreSQL: How often in seconds the database should be 'pinged' to ensure the
# connection is still valid.  If *any* error is generate by the ping such as 
# 'Unable to connect to database', it will attempt to re-create the database
# connection.  Set to 0 to disable pinging.
# Note:  This has no effect on postgresql_ping_on_insert.
# disabled = 0
# 5 minutes = 300
# 15 minutes = 900
# 30 minutes = 1800
postgresql_ping_interval = 300

# ODBC: Set to 1 to enable logging to a database via ODBC using DBD::ODBC.  
# This requires both DBI:: and DBD::ODBC
dbd_odbc_enable = 0

# DBD:ODBC: Database to use
dbd_odbc_dsn = snmptt

# DBD:ODBC: Table to use
dbd_odbc_table = snmptt

# DBD:ODBC: Table to use for unknown traps
# Leave blank to disable logging of unknown traps to DBD:ODBC
# Note: unknown_trap_log_enable must be enabled.
dbd_odbc_table_unknown = snmptt_unknown

# DBD:ODBC: Table to use for statistics
# Note: statistics_interval must be set.  See also stat_time_format_sql.
#dbd_odbc_table_statistics = snmptt_statistics
dbd_odbc_table_statistics = 

# DBD:ODBC: Username to use
dbd_odbc_username = snmptt

# DBD:DBC:: Password to use
dbd_odbc_password = password


# DBD:ODBC: Whether or not to 'ping' the database before attempting an INSERT
# to ensure the connection is still valid.  If *any* error is generate by 
# the ping such as 'Unable to connect to database', it will attempt to 
# re-create the database connection.
# Set to 0 to disable
# Set to 1 to enable
# Note:  This has no effect on dbd_odbc_ping_interval.
dbd_odbc_ping_on_insert = 1

# DBD:ODBC:: How often in seconds the database should be 'pinged' to ensure the
# connection is still valid.  If *any* error is generate by the ping such as 
# 'Unable to connect to database', it will attempt to re-create the database
# connection.  Set to 0 to disable pinging.
# Note:  This has no effect on dbd_odbc_ping_on_insert.
# disabled = 0
# 5 minutes = 300
# 15 minutes = 900
# 30 minutes = 1800
dbd_odbc_ping_interval = 300

# The date time format for the traptime column in SQL.  Defaults to 
# localtime().  When a date/time field is used in SQL, this should
# be changed to follow a standard that is supported by the SQL server.
# Example:  For a MySQL DATETIME, use %Y-%m-%d %H:%M:%S.
#date_time_format_sql = 

# The date time format for the stat_time column in SQL.  Defaults to 
# localtime().  When a date/time field is used in SQL, this should
# be changed to follow a standard that is supported by the SQL server.
# Example:  For a MySQL DATETIME, use %Y-%m-%d %H:%M:%S.
#stat_time_format_sql = 

[Exec]

# Set to 1 to allow EXEC statements to execute.  Should normally be left on unless you
# want to temporarily disable all EXEC commands
exec_enable = 1

# Set to 1 to allow PREEXEC statements to execute.  Should normally be left on unless you
# want to temporarily disable all PREEXEC commands
pre_exec_enable = 1

# If defined, the following command will be executed for ALL unknown traps.  Passed to the
# command will be all standard and enterprise variables, similar to unknown_trap_log_file
# but without the newlines.
unknown_trap_exec = 

# FORMAT line that is passed to the unknown_trap_exec command.  If not defined, it
# defaults to what is described in the unknown_trap_exec setting.  The following
# would be *similar* to the default described in the unknown_trap_exec setting
# (all on one line):
# \$x !! \$X: Unknown trap (\$o) received from \$A at: Value 0: \$A Value 1: \$aR 
# Value 2: \$T Value 3: \$o Value 4: \$aA Value 5: \$C Value 6: \$e Ent Values: \$+*
unknown_trap_exec_format = 

# Set to 1 to escape wildards (* and ?) in EXEC, PREEXEC and the unknown_trap_exec
# commands.  Enable this to prevent the shell from expanding the wildcard 
# characters.  The default is 1.
exec_escape = 1

[Debugging]
# 0 - do not output messages
# 1 - output some basic messages
# 2 - out all messages
DEBUGGING = 0

# Debugging file - SNMPTT
# Location of debugging output file.  Leave blank to default to STDOUT (good for
# standalone mode, or daemon mode without forking)
DEBUGGING_FILE = 
# DEBUGGING_FILE = /var/log/snmptt/snmptt.debug

# Debugging file - SNMPTTHANDLER
# Location of debugging output file.  Leave blank to default to STDOUT
DEBUGGING_FILE_HANDLER = 
# DEBUGGING_FILE_HANDLER = /var/log/snmptt/snmptthandler.debug

[TrapFiles]
# A list of snmptt.conf files (this is NOT the snmptrapd.conf file).  The COMPLETE path 
# and filename.  Ex: '/usr/local/etc/snmp/snmptt.conf'
snmptt_conf_files = <<END
/usr/local/etc/snmp/snmptt.conf
END
EOF;
?>
