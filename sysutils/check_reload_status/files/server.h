/*
        Copyright (C) 2010 Ermal Luçi
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.

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
        { IPSECDNS, NON, "ipsecdns", NULL,
                { "/etc/rc.newipsecdns", NULL, "Restarting ipsec tunnels", AGGREGATE | FCGICMD } },
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
