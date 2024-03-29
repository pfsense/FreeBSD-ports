/*
 * server.h
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2024 Rubicon Communications, LLC (Netgate)
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

#ifndef _SERVER_H_
#define _SERVER_H_

#define filepath        "/tmp/check_test"

enum actions {
        ALL,
	ALIAS,
	ALIASES,
        FILTER,
        INTERFACE,
	IPSEC,
	IPSECDNS,
	ROUTEDNS,
	OPENVPN,
        GATEWAY,
        SERVICE,
        DNSSERVER,
        DYNDNS,
        DYNDNSALL,
	PACKAGES,
        SSHD,
        WEBGUI,
        NEWIP,
        NTPD,
        LINKUP,
        LINKDOWN,
        RELOAD,
        RECONFIGURE,
        RESTART,
	START,
	STOP,
        SYNC,
	VOUCHERS,
        NULLOPT
};

enum argtype {
        NON,
        COMPOUND,
        ADDRESS,
        PREFIX,
        STRING,
        INTEGER,
        IFNAME
};

#define	AGGREGATE	1
#define	FCGICMD		2
struct run {
        const char    *command;
	const char    *params;
        const char    *syslog;
	int flags;
};

struct command {
        enum actions    action;
        enum argtype    type;
        const char      *keyword;
        struct command  *next;
        struct run      cmd;
};

static struct command c_interface2[];
static struct command c_filter[];
static struct command c_interface[];
static struct command c_service[];
static struct command c_service2[];

#define NULL_INIT { NULL, NULL, NULL, 0 }

static struct command first_level[] = {
        { FILTER, COMPOUND, "filter", c_filter, NULL_INIT},
        { INTERFACE, COMPOUND, "interface", c_interface, NULL_INIT},
        /* { GATEWAY, COMPOUND, "gateway", c_reload }, */
        { SERVICE, COMPOUND, "service", c_service, NULL_INIT },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_filter[] = {
        { RELOAD, NON, "reload", NULL,
                { "/etc/rc.filter_configure_sync", NULL, "Reloading filter", AGGREGATE | FCGICMD } },
        { RECONFIGURE, NON, "reconfigure", NULL,
                { "/etc/rc.filter_configure_sync", NULL, "Reloading filter", AGGREGATE | FCGICMD } },
        { RESTART, NON, "restart", NULL,
                { "/etc/rc.filter_configure_sync", NULL, "Reloading filter", AGGREGATE | FCGICMD } },
        { SYNC, NON, "sync", NULL,
                { "/etc/rc.filter_synchronize", NULL, "Syncing firewall", AGGREGATE | FCGICMD } },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_interface[] = {
        { ALL, STRING, "all", c_interface2, NULL_INIT },
        { RELOAD, IFNAME, "reload", NULL,
                { "/etc/rc.interfaces_wan_configure", "interface=%s", "Configuring interface %s", AGGREGATE | FCGICMD } },
        { RECONFIGURE, IFNAME, "reconfigure", NULL,
                { "/etc/rc.interfaces_wan_configure", "interface=%s", "Configuring interface %s", AGGREGATE | FCGICMD } },
        { RESTART, IFNAME, "restart", NULL,
                { "/etc/rc.interfaces_wan_configure", "interface=%s", "Configuring interface %s", AGGREGATE | FCGICMD } },
        { NEWIP, STRING, "newip", NULL,
                { "/etc/rc.newwanip", "interface=%s", "rc.newwanip starting %s", FCGICMD } },
        { NEWIP, STRING, "newipv6", NULL,
                { "/etc/rc.newwanipv6", "interface=%s", "rc.newwanipv6 starting %s", FCGICMD } },
        { LINKUP, STRING, "linkup", c_interface2, NULL_INIT },
        { SYNC, NON, "sync", NULL,
                { "/etc/rc.filter_configure_xmlrpc", NULL, "Reloading filter_configure_xmlrpc", AGGREGATE | FCGICMD } },
        { RECONFIGURE, IFNAME, "carpmaster", NULL,
                { "/etc/rc.carpmaster", "interface=%s", "Carp master event", FCGICMD } },
        { RECONFIGURE, IFNAME, "carpbackup", NULL,
                { "/etc/rc.carpbackup", "interface=%s", "Carp backup event", FCGICMD } },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_interface2[] = {
        { RELOAD, NON, "reload", NULL,
                { "/etc/rc.reload_interfaces", NULL, "Reloading interfaces", AGGREGATE | FCGICMD } },
	{ START, IFNAME, "start", NULL,
                { "/etc/rc.linkup", "action=start&interface=%s", "Linkup starting %s", FCGICMD } },
	{ STOP, IFNAME, "stop", NULL,
                { "/etc/rc.linkup", "action=stop&interface=%s", "Linkup starting %s", FCGICMD } },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_service2[] = {
        { ALL, NON, "all", NULL,
                { "/etc/rc.reload_all", NULL, "Reloading all", AGGREGATE | FCGICMD } },
        { DNSSERVER, NON, "dns", NULL,
                { "/etc/rc.resolv_conf_generate", NULL, "Rewriting resolv.conf", AGGREGATE | FCGICMD } },
        { IPSEC, STRING, "ipsec", NULL,
                { "/etc/rc.ipsec", "interface=%s", "Restarting IPsec tunnels", AGGREGATE | FCGICMD } },
        { IPSECDNS, NON, "ipsecdns", NULL,
                { "/etc/rc.newipsecdns", NULL, "Restarting IPsec tunnels", AGGREGATE | FCGICMD } },
        { ROUTEDNS, NON, "routedns", NULL,
                { "/etc/rc.newroutedns", NULL, "Updating static routes based on hostnames", AGGREGATE | FCGICMD } },
        { OPENVPN, STRING, "openvpn", NULL,
                { "/etc/rc.openvpn", "interface=%s", "Restarting OpenVPN tunnels/interfaces", AGGREGATE | FCGICMD } },
        { DYNDNS, STRING, "dyndns", NULL,
                { "/etc/rc.dyndns.update", "dyndns=%s", "updating dyndns %s", AGGREGATE | FCGICMD } },
        { DYNDNSALL, NON, "dyndnsall", NULL,
                { "/etc/rc.dyndns.update", NULL, "Updating all dyndns", AGGREGATE | FCGICMD } },
        { NTPD, NON, "ntpd", NULL,
                { "/usr/bin/killall ntpd; /bin/sleep 3; /usr/local/sbin/ntpd -s -f /var/etc/ntpd.conf", NULL, "Starting nptd", AGGREGATE } },
        { PACKAGES, NON, "packages", NULL,
                { "/etc/rc.start_packages", NULL, "Starting packages", FCGICMD } },
        { SSHD, NON, "sshd", NULL,
                { "/etc/sshd", NULL, "starting sshd", AGGREGATE | FCGICMD } },
        { WEBGUI, NON, "webgui", NULL,
                { "/etc/rc.restart_webgui", NULL, "webConfigurator restart in progress", AGGREGATE | FCGICMD } },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_service_sync[] = {
	{ VOUCHERS, NON, "vouchers", NULL,
		{ "/etc/rc.savevoucher", NULL, "Synching vouchers", AGGREGATE | FCGICMD } },
	{ ALIASES, STRING, "alias", NULL,
		{ "/etc/rc.update_urltables", "alias=%s", "Synching URL alias %s", AGGREGATE | FCGICMD } },
	{ ALIASES, NON, "aliases", NULL,
		{ "/etc/rc.update_urltables", NULL, "Synching URL aliases", AGGREGATE | FCGICMD } },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

static struct command c_service[] = {
        { RELOAD, STRING, "reload", c_service2, NULL_INIT },
        { RECONFIGURE, STRING, "reconfigure", c_service2, NULL_INIT},
        { RESTART, STRING, "restart", c_service2, NULL_INIT },
	{ SYNC, STRING, "sync", c_service_sync, NULL_INIT },
        { NULLOPT, NON, "", NULL, NULL_INIT }
};

#endif /* _SERVER_H_ */
