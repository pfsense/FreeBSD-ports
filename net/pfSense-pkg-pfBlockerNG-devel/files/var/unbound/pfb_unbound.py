# pfb_unbound.py
# pfBlockerNG - Unbound resolver python integration

# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
# Copyright (c) 2015-2021 BBcan177@gmail.com
# All rights reserved.

# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at

# http://www.apache.org/licenses/LICENSE-2.0

# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

from datetime import datetime
import logging
import time
import csv
import sys
import re
import os

global pfb
pfb = {}

if sys.version_info < (2, 8):
    from ConfigParser import ConfigParser
    pfb['py_v3'] = False
else:
    from configparser import ConfigParser
    pfb['py_v3'] = True

from collections import defaultdict

# Import additional python modules
try:
    import threading
    pfb['mod_threading'] = True
    threads = list()
except Exception as e:
    pfb['mod_threading'] = False
    pfb['mod_threading_e'] = e
    pass

try:
    import ipaddress
    pfb['mod_ipaddress'] = True
except Exception as e:
    pfb['mod_ipaddress'] = False
    pfb['mod_ipaddress_e'] = e
    pass

try:
    import maxminddb
    pfb['mod_maxminddb'] = True
except Exception as e:
    pfb['mod_maxminddb'] = False
    pfb['mod_maxminddb_e'] = e
    pass

try:
    import sqlite3
    pfb['mod_sqlite3'] = True
except Exception as e:
    pfb['mod_sqlite3'] = False
    pfb['mod_sqlite3_e'] = e
    pass


def init_standard(id, env):
    global pfb, rcodeDB, dataDB, zoneDB, regexDB, hstsDB, whiteDB, excludeDB, excludeAAAADB, dnsblDB, noAAAADB, gpListDB, safeSearchDB, feedGroupIndexDB, maxmindReader

    if not register_inplace_cb_reply(inplace_cb_reply, env, id):
        log_info('[pfBlockerNG]: Failed register_inplace_cb_reply')
        return False

    if not register_inplace_cb_reply_cache(inplace_cb_reply_cache, env, id):
        log_info('[pfBlockerNG]: Failed register_inplace_cb_reply_cache')
        return False

    if not register_inplace_cb_reply_local(inplace_cb_reply_local, env, id):
        log_info('[pfBlockerNG]: Failed register_inplace_cb_reply_local')
        return False

    if not register_inplace_cb_reply_servfail(inplace_cb_reply_servfail, env, id):
        log_info('[pfBlockerNG]: Failed register_inplace_cb_reply_servfail')
        return False

    # Store previous error message to avoid repeating
    pfb['p_err'] = ''

    # Log stderr to file
    class log_stderr(object):
        def __init__(self, logger):
            self.logger = logger
            self.linebuf = ''

        def write(self, msg):
            if msg != pfb['p_err']:
                self.logger.log(logging.ERROR, msg.rstrip())
            pfb['p_err'] = msg

    # Create python error logfile
    logfile = '/var/log/pfblockerng/py_error.log'

    for i in range(2):
        try:
            logging.basicConfig(format = '%(asctime)s|%(levelname)s| %(message)s', filename = logfile, filemode = 'a')
            break
        except IOError:
            # Remove logfile if ownership is not 'unbound:unbound'
            if os.path.isfile(logfile):
                os.remove(logfile)
    sys.stderr = log_stderr(logging.getLogger('pfb_stderr'))

    # Validate write access to log files
    for l_file in ('dnsbl', 'dns_reply', 'unified'):
        lfile = '/var/log/pfblockerng/' + l_file + '.log'

        try:
            if os.path.isfile(lfile) and not os.access(lfile, os.W_OK):
                new_file = '/var/log/pfblockerng/' + l_file + str(datetime.now().strftime("_%Y%m%-d%H%M%S.log"))
                os.rename(lfile, new_file)
        except Exception as e:
            sys.stderr.write("[pfBlockerNG]: Failed to validate write permission: {}.log: {}" .format(l_file, e))
            if os.path.isfile(lfile):
                new_file = '/var/log/pfblockerng/' + l_file + str(datetime.now().strftime("_%Y%m%-d%H%M%S.log"))
                os.rename(lfile, new_file)
            pass

    if not pfb['mod_threading']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'threading': {}" .format(pfb['mod_threading_e']))

    if not pfb['mod_ipaddress']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'ipaddress': {}" .format(pfb['mod_ipaddress_e']))

    if not pfb['mod_maxminddb']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'maxminddb': {}" .format(pfb['mod_maxminddb_e']))

    if not pfb['mod_sqlite3']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'sqlite3': {}" .format(pfb['mod_sqlite3_e']))

    # Initialize default settings
    pfb['dnsbl_ipv4'] = ''
    pfb['dnsbl_ipv6'] = ''
    pfb['dataDB'] = False
    pfb['zoneDB'] = False
    pfb['hstsDB'] = False
    pfb['whiteDB'] = False
    pfb['regexDB'] = False
    pfb['whiteDB'] = False
    pfb['gpListDB'] = False
    pfb['noAAAADB'] = False
    pfb['python_idn'] = False
    pfb['python_ipv6'] = False
    pfb['python_hsts'] = False
    pfb['python_reply'] = False
    pfb['python_cname'] = False
    pfb['safeSearchDB'] = False
    pfb['group_policy'] = False
    pfb['python_enable'] = False
    pfb['python_nolog'] = False
    pfb['python_control'] = False
    pfb['python_maxmind'] = False
    pfb['python_blocking'] = False
    pfb['python_blacklist'] = False
    pfb['sqlite3_dnsbl_con'] = False
    pfb['sqlite3_resolver_con'] = False

    # DNSBL Python files
    pfb['pfb_unbound.ini'] = 'pfb_unbound.ini'
    pfb['pfb_py_whitelist'] = 'pfb_py_whitelist.txt'
    pfb['pfb_py_zone'] = 'pfb_py_zone.txt'
    pfb['pfb_py_data'] = 'pfb_py_data.txt'
    pfb['pfb_py_hsts'] = 'pfb_py_hsts.txt'
    pfb['pfb_py_ss'] = 'pfb_py_ss.txt'  
    pfb['pfb_py_dnsbl'] = 'pfb_py_dnsbl.sqlite'
    pfb['pfb_py_cache'] = 'pfb_py_cache.sqlite'
    pfb['pfb_py_resolver'] = 'pfb_py_resolver.sqlite'
    pfb['maxminddb'] = '/usr/local/share/GeoIP/GeoLite2-Country.mmdb'

    # Remove DNSBL cache file (For Reports tab query)
    if os.path.isfile(pfb['pfb_py_cache']):
        os.remove(pfb['pfb_py_cache'])

    # DNSBL validation on these RR_TYPES only
    pfb['rr_types'] = (RR_TYPE_A, RR_TYPE_AAAA, RR_TYPE_ANY, RR_TYPE_CNAME, RR_TYPE_DNAME, RR_TYPE_SIG, \
                       RR_TYPE_MX, RR_TYPE_NS, RR_TYPE_PTR, RR_TYPE_SRV, RR_TYPE_TXT, 64, 65)

    pfb['rr_types2'] = ('A', 'AAAA')

    # List of HSTS preload TLDs
    pfb['hsts_tlds'] = ('android', 'app', 'bank', 'chrome', 'dev', 'foo', 'gle', 'gmail', 'google', 'hangout', \
                        'insurance', 'meet', 'new', 'page', 'play', 'search', 'youtube')

    # Initialize dicts/lists
    dataDB = defaultdict(list)
    zoneDB = defaultdict(list)
    dnsblDB = defaultdict(list)
    safeSearchDB = defaultdict(list)
    feedGroupIndexDB = defaultdict(list)

    regexDB = defaultdict(str)
    whiteDB = defaultdict(str)
    hstsDB = defaultdict(str)
    gpListDB = defaultdict(str)
    noAAAADB = defaultdict(str)
    feedGroupDB = defaultdict(str)
    excludeDB = []
    excludeAAAADB = []

    # Read pfb_unbound.ini settings
    if os.path.isfile(pfb['pfb_unbound.ini']):
        try:
            config = ConfigParser()
            config.read(pfb['pfb_unbound.ini'])
        except Exception as e:
            sys.stderr.write("[pfBlockerNG]: Failed to load ini configuration: {}" .format(e))
            pass

        if config.has_section('MAIN'):
            if config.has_option('MAIN', 'python_enable'):
                pfb['python_enable'] = config.getboolean('MAIN', 'python_enable')
            if config.has_option('MAIN', 'python_ipv6'):
                pfb['python_ipv6'] = config.getboolean('MAIN', 'python_ipv6')
            if config.has_option('MAIN', 'python_reply'):
                pfb['python_reply'] = config.getboolean('MAIN', 'python_reply')
            if config.has_option('MAIN', 'python_blocking'):
                pfb['python_blocking'] = config.getboolean('MAIN', 'python_blocking')
            if config.has_option('MAIN', 'python_hsts'):
                pfb['python_hsts'] = config.getboolean('MAIN', 'python_hsts')
            if config.has_option('MAIN', 'python_idn'):
                pfb['python_idn'] = config.getboolean('MAIN', 'python_idn')
            if config.has_option('MAIN', 'python_tld_seg'):
                pfb['python_tld_seg'] = config.getint('MAIN', 'python_tld_seg')
            if config.has_option('MAIN', 'python_tld'):
                pfb['python_tld'] = config.getboolean('MAIN', 'python_tld')
            if config.has_option('MAIN', 'python_tlds'):
                pfb['python_tlds'] = config.get('MAIN', 'python_tlds').split(',')
            if config.has_option('MAIN', 'dnsbl_ipv4'):
                pfb['dnsbl_ipv4'] = config.get('MAIN', 'dnsbl_ipv4')
            if config.has_option('MAIN', 'python_nolog'):
                pfb['python_nolog'] = config.getboolean('MAIN', 'python_nolog')
            if config.has_option('MAIN', 'python_cname'):
                pfb['python_cname'] = config.getboolean('MAIN', 'python_cname')
            if config.has_option('MAIN', 'python_control'):
                pfb['python_control'] = config.getboolean('MAIN', 'python_control')

            if pfb['python_ipv6']:
                pfb['dnsbl_ipv6'] = '::' + pfb['dnsbl_ipv4']
            else:
                pfb['dnsbl_ipv6'] = '::'

            # DNSBL IP/Log types (0 = Null Blocking logging, 1 = DNSBL Web Server logging, 2 = Null Blocking no logging)
            pfb['dnsbl_ip'] = {'A': {'0': '0.0.0.0', '1': pfb['dnsbl_ipv4'], '2': '0.0.0.0'},
                               'AAAA': {'0': '::', '1': pfb['dnsbl_ipv6'], '2': '::'} }

            # List of DNS R_CODES
            rcodeDB = {0: 'NoError', 1: 'FormErr', 2: 'ServFail', 3: 'NXDOMAIN', 4: 'NotImp', 5: 'Refused', 6: 'YXDomain',
                       7: 'YXRRSet', 8: 'NXRRSet', 9: 'NotAuth', 10: 'NotZone', 11: 'DSOTYPENI', 16: 'BADVERS', 17: 'BADKEY',
                       18: 'BADTIME', 19: 'BADMODE', 20: 'BADNAME', 21: 'BADALG', 22: 'BADTRUNC', 23: 'BADCOOKIE' }

        if pfb['python_enable']:

            # Enable the Blacklist functions (IDN)
            if pfb['python_idn']:
                pfb['python_blacklist'] = True

            # Enable the Blacklist functions (TLD Allow)
            if pfb['python_tld'] and pfb['python_tlds'] != '':
                pfb['python_blacklist'] = True

            # Collect user-defined Regex patterns
            if config.has_section('REGEX'):
                regex_config = config.items('REGEX')
                if regex_config:
                    r_count = 1
                    for name, pattern in regex_config:
                        try:
                            regexDB[name] = re.compile(pattern)
                            pfb['regexDB'] = True
                            pfb['python_blacklist'] = True
                        except Exception as e:
                            sys.stderr.write("[pfBlockerNG]: Regex [ {} ] compile error pattern [  {}  ] on line #{}: {}" .format(name, pattern, r_count, e))
                            pass
                        r_count += 1

            # Collect user-defined no AAAA domains
            if config.has_section('noAAAA'):
                noaaaa_config = config.items('noAAAA')
                if noaaaa_config:
                    try:
                        for row, line in noaaaa_config:
                            data = line.rstrip('\r\n').split(',')
                            if data and len(data) == 2:
                                if data[1] == '1':
                                    wildcard = True
                                else:
                                    wildcard = False
                                noAAAADB[data[0]] = wildcard
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: noAAAA: row:{} line:{}" .format(row, line))

                        pfb['noAAAADB'] = True
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load no AAAA domain list: {}" .format(e))
                        pass

            # Collect user-defined Group Policy Global Bypass List
            if config.has_section('GP_Bypass_List'):
                gp_bypass_list = config.items('GP_Bypass_List')
                if gp_bypass_list:
                    try:
                        for row, line in gp_bypass_list:
                            gpListDB[line.rstrip('\r\n')] = 0

                        pfb['gpListDB'] = True
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load GP Bypass List: {}" .format(e))
                        pass 

            # Collect SafeSearch Redirection list
            if os.path.isfile(pfb['pfb_py_ss']):
                try:
                    with open(pfb['pfb_py_ss']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        for row in csv_reader:
                            if row and len(row) == 3:
                                safeSearchDB[row[0]] = {'A': row[1], 'AAAA': row[2]}
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_ss'], row))

                        pfb['safeSearchDB'] = True
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_zone'], e))
                    pass

            # While reading 'data|zone' CSV files: Replace 'Feed/Group' pairs with an index value (Memory performance)
            feedGroup_index = 0

            # Zone dicts
            if os.path.isfile(pfb['pfb_py_zone']):
                try:
                    with open(pfb['pfb_py_zone']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        for row in csv_reader:
                            if row and len(row) == 6:
                                # Query Feed/Group/index
                                isInFeedGroupDB = feedGroupDB.get(row[4] + row[5])

                                # Add Feed/Group/index
                                if isInFeedGroupDB is None:
                                    feedGroupDB[row[4] + row[5]] = feedGroup_index
                                    feedGroupIndexDB[feedGroup_index] = {'feed': row[4], 'group': row[5]}
                                    final_index = feedGroup_index
                                    feedGroup_index += 1

                                # Use existing Feed/Group/index
                                else:
                                    final_index = isInFeedGroupDB

                                zoneDB[row[1]] = {'log': row[3], 'index': final_index}
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_zone'], row))

                        pfb['zoneDB'] = True
                        pfb['python_blacklist'] = True
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_zone'], e))
                    pass

            # Data dicts
            if os.path.isfile(pfb['pfb_py_data']):
                try:
                    with open(pfb['pfb_py_data']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        for row in csv_reader:
                            if row and len(row) == 6:
                                # Query Feed/Group/index
                                isInFeedGroupDB = feedGroupDB.get(row[4] + row[5])

                                # Add Feed/Group/index
                                if isInFeedGroupDB is None:
                                    feedGroupDB[row[4] + row[5]] = feedGroup_index
                                    feedGroupIndexDB[feedGroup_index] = {'feed': row[4], 'group': row[5]}
                                    final_index = feedGroup_index
                                    feedGroup_index += 1

                                # Use existing Feed/Group/index
                                else:
                                    final_index = isInFeedGroupDB

                                dataDB[row[1]] = {'log': row[3], 'index': final_index}
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_data'], row))

                        pfb['dataDB'] = True
                        pfb['python_blacklist'] = True
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_data'], e))
                    pass

            # Clear temporary Feed/Group/Index list
            feedGroupDB.clear()

            if pfb['python_blacklist']:

                # Collect user-defined Whitelist
                if os.path.isfile(pfb['pfb_py_whitelist']):
                    try:
                        with open(pfb['pfb_py_whitelist']) as csv_file:
                            csv_reader = csv.reader(csv_file, delimiter=',')
                            for row in csv_reader:
                                if row and len(row) == 2:
                                    if row[1] == '1':
                                        wildcard = True
                                    else:
                                        wildcard = False
                                    whiteDB[row[0]] = wildcard
                                    pfb['whiteDB'] = True
                                else:
                                    sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_whitelist'], row))

                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_whitelist'], e))
                        pass

                # HSTS dicts
                if pfb['python_hsts'] and os.path.isfile(pfb['pfb_py_hsts']):
                    try:
                        with open(pfb['pfb_py_hsts']) as hsts:
                            for line in hsts:
                                hstsDB[line.rstrip('\r\n')] = 0
                            pfb['hstsDB'] = True
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_hsts'], e))
                        pass

            # Validate SQLite3 database connections
            if pfb['mod_sqlite3']:

                # Enable Resolver query statistics
                for i in range(2):
                    try:
                        if write_sqlite(1, '', False):
                            pfb['sqlite3_resolver_con'] = True
                            break
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to open pfb_py_resolver.sqlite database (Attempt: {}/2): {}" .format(i+1, e))
                        pass
                        if os.path.isfile(pfb['pfb_py_resolver']):
                            os.remove(pfb['pfb_py_resolver'])

                # Enable DNSBL statistics
                if pfb['python_blacklist']:
                    for i in range(2):
                        try:
                            if write_sqlite(2, '', False):
                                pfb['sqlite3_dnsbl_con'] = True
                                break
                        except Exception as e:
                            sys.stderr.write("[pfBlockerNG]: Failed to open pfb_py_dnsbl.sqlite database (Attempt: {}/2): {}" .format(i+1, e))
                            pass
                            if os.path.isfile(pfb['pfb_py_dnsbl']):
                                os.remove(pfb['pfb_py_dnsbl'])

            # Open MaxMind db reader for DNS Reply GeoIP logging
            if pfb['mod_maxminddb'] and pfb['python_reply'] and os.path.isfile(pfb['maxminddb']):
                try:
                    maxmindReader = maxminddb.open_database(pfb['maxminddb'])
                    pfb['python_maxmind'] = True
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to open MaxMind DB: {}" .format(e))
                    pass
    else:
        log_info('[pfBlockerNG]: Failed to load ini configuration. Ini file missing.')

    log_info('[pfBlockerNG]: init_standard script loaded')


def pfb_regex_match(q_name):
    global regexDB

    if q_name:
        for k,r in regexDB.items():
            if r.search(q_name):
                return k
    return False


def get_q_name_qstate(qstate):
    q_name = ''
    try:
        if qstate and qstate.qinfo and qstate.qinfo.qname_str and qstate.qinfo.qname_str.strip():
            q_name = qstate.qinfo.qname_str.rstrip('.')
        elif qstate and qstate.return_msg and qstate.return_msg.qinfo and qstate.return_msg.qinfo.qname_str.strip():
            q_name = qstate.return_msg.qinfo.qname_str.rstrip('.')
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: Failed get_q_name_qstate: {}" .format(e))
        pass
    return is_unknown(q_name)


def get_q_name_qinfo(qinfo):
    q_name = ''
    try:
        if qinfo and qinfo.qname_str and qinfo.qname_str.strip():
            q_name = qinfo.qname_str.rstrip('.')
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: Failed get_q_name_qinfo: {}" .format(e))
        pass
    return is_unknown(q_name)


def get_q_ip(qstate):
    q_ip = ''

    try:
        if qstate and qstate.mesh_info.reply_list:
            reply_list = qstate.mesh_info.reply_list
            while reply_list:
                if reply_list.query_reply:
                    q_ip = reply_list.query_reply.addr
                    break
                reply_list = reply_list.next
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: Failed get_q_ip: {}" .format(e))
        pass
    return is_unknown(q_ip)


def get_q_ip_comm(kwargs):
    q_ip = ''

    try:
        if kwargs and kwargs is not None and ('pfb_addr' in kwargs):
            q_ip = kwargs['pfb_addr']
        elif kwargs and kwargs is not None and kwargs['repinfo'] and kwargs['repinfo'].addr:
            q_ip = kwargs['repinfo'].addr
    except Exception as e:
        for a in e:
            sys.stderr.write("[pfBlockerNG]: Failed get_q_ip_comm: {}" .format(a))
        pass
    return is_unknown(q_ip)


def get_q_type(qstate, qinfo):
    q_type = ''
    if qstate and qstate.qinfo.qtype_str:
        q_type = qstate.qinfo.qtype_str
    elif qinfo and qinfo.qtype_str:
        q_type = qinfo.qtype_str
    return is_unknown(q_type)


def get_o_type(qstate, rep):
    o_type = ''
    if qstate:
        if qstate.return_msg and qstate.return_msg.rep and qstate.return_msg.rep.rrsets[0] and qstate.return_msg.rep.rrsets[0].rk:
            o_type = qstate.return_msg.rep.rrsets[0].rk.type_str
        elif qstate.qinfo.qtype_str:
            o_type = qstate.qinfo.qtype_str
        elif rep is not None and rep.rrsets[0] is not None and rep.rrsets[0].rk is not None:
             o_type = rep.rrsets[0].rk.type_str
    return is_unknown(o_type)


def get_rep_ttl(rep):
    ttl = ''
    if rep and rep.ttl:
        ttl = rep.ttl
    return str(is_unknown(ttl)).replace('Unknown', 'Unk')


def get_tld(qstate):
    tld = ''
    if qstate and qstate.qinfo and len(qstate.qinfo.qname_list) > 1:
        tld = qstate.qinfo.qname_list[-2]
    return tld


def convert_ipv4(x):
    global pfb

    ipv4 = ''
    if x:
        if pfb['py_v3']:
            ipv4 = "{}.{}.{}.{}" .format(x[2], x[3], x[4], x[5])
        else:
            ipv4 = "{}.{}.{}.{}" .format(ord(x[2]), ord(x[3]), ord(x[4]), ord(x[5]))
    return is_unknown(ipv4)


def convert_ipv6(x):
    global pfb

    ipv6 = ''
    if x:
        if pfb['py_v3']:
            ipv6 = "{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}" \
                .format(x[2],x[3],x[4],x[5],x[6],x[7],x[8],x[9],x[10],x[11],x[12],x[13],x[14],x[15],x[16],x[17])
        else:
            ipv6 = "{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}:{:02x}{:02x}" \
                .format(ord(x[2]),ord(x[3]),ord(x[4]),ord(x[5]),ord(x[6]),ord(x[7]),ord(x[8]),ord(x[9]),ord(x[10]), \
                ord(x[11]),ord(x[12]),ord(x[13]),ord(x[14]),ord(x[15]),ord(x[16]),ord(x[17]))
    return is_unknown(ipv6)


def convert_other(x):
    global pfb

    final = ''
    if x:
        for i in x[3:]:

            if pfb['py_v3']:
                val = i
            else:
                val = ord(i)

            if val == 0:
                i = '|'
            elif 1 <= val <= 12:
                i = '.'
            elif val == 13:
                break
            elif val == 32:
                i = ' '
            elif val == 58:
                i = ':'
            elif val <= 33 or val > 126:
                continue
            else:
                if pfb['py_v3']:
                    i = chr(i)

            final += i
        final = final.strip('.|')
    return is_unknown(final)


def is_unknown(x):
    try:
        if not x or x is None:
            return 'Unknown'
    except Exception as e:
        for a in e:
            sys.stderr.write("[pfBlockerNG]: Failed is_unknown: {}" .format(a))
        pass
    return x


def write_sqlite(db, groupname, update):
    global pfb

    if db == 1:
        db_file = pfb['pfb_py_resolver']
    elif db == 2:
        db_file = pfb['pfb_py_dnsbl']
    elif db == 3:
        db_file = pfb['pfb_py_cache']
    else:
        return False

    sqlite3Db = None
    for i in range(2):
        try:
            sqlite3Db = sqlite3.connect(db_file, timeout=100000)
        except Exception as e:
            if sqlite3Db:
                sqlite3Db.close()
            if i == 2:
                sys.stderr.write("[pfBlockerNG]: Failed to open sqlite3 db {}: {}" .format(db_file, e))
                return False
            else:
                time.sleep(0.25)
                continue
        break

    isException = False
    for i in range(1,5):
        try:
            if sqlite3Db:
                sqlite3DbCursor = sqlite3Db.cursor()

                if db == 1:
                    sqlite3DbCursor.execute("CREATE TABLE IF NOT EXISTS resolver (row integer, totalqueries integer, queries integer)")

                    # Create row if not found
                    sqlite3DbCursor.execute("SELECT COUNT(*) FROM resolver")
                    py_validate = sqlite3DbCursor.fetchone()
                    if py_validate[0] == 0:
                        sqlite3DbCursor.execute("INSERT INTO resolver ( row, totalqueries, queries ) VALUES ( 0, 0, 0 )")

                    # Increment resolver totalqueries
                    if update:
                        sqlite3DbCursor.execute("UPDATE resolver SET totalqueries = totalqueries + 1 WHERE row = 0")

                elif db == 2:
                    sqlite3DbCursor.execute("CREATE TABLE IF NOT EXISTS dnsbl ( groupname TEXT, timestamp TEXT, entries INTEGER, counter INTEGER )")

                    # Increment DNSBL Groupname counter
                    if update:
                        sqlite3DbCursor.execute("UPDATE dnsbl SET counter = counter + 1 WHERE groupname = ?", (groupname,) )

                elif db == 3:
                    sqlite3DbCursor.execute("CREATE TABLE IF NOT EXISTS dnsblcache ( type TEXT, domain TEXT, groupname TEXT, final TEXT, feed TEXT );")
                    sqlite3DbCursor.execute("INSERT INTO dnsblcache (type, domain, groupname, final, feed ) VALUES (?,?,?,?,?);", update)

                sqlite3Db.commit()
                isException = False

        except Exception as e:
            if i == 4:
                if sqlite3Db:
                    sqlite3Db.close()

                sys.stderr.write("[pfBlockerNG]: Failed to write to sqlite3 db {}: {}" .format(db_file, e))

                # Attempt to clear DNSBL Cache file on error
                if db == 3 and os.path.isfile(pfb['pfb_py_cache']):
                    os.remove(pfb['pfb_py_cache'])
                    sys.stderr.write("[pfBlockerNG]: DNSBL Cache database cleared OK")

                pass
                return False

            else:
                time.sleep(0.25)
                isException = True
                continue

        finally:
            if not isException and sqlite3Db:
                sqlite3Db.close()
            break

    return True


def get_details_dnsbl(m_type, qinfo, qstate, rep, kwargs):
    global pfb, rcodeDB, dnsblDB, noAAAADB, maxmindReader

    if qstate and qstate is not None:
        q_name = get_q_name_qstate(qstate)
    elif qinfo and qinfo is not None:
        q_name = get_q_name_qinfo(qinfo)
    else:
        return True

    # Increment totalqueries counter
    if pfb['sqlite3_resolver_con']:
        write_sqlite(1, '', True)

    # Determine if event is a 'reply' or DNSBL block
    isDNSBL = dnsblDB.get(q_name)
    if isDNSBL is not None:

        # If logging is disabled, do not log blocked DNSBL events (Utilize DNSBL Webserver) except for Python nullblock events
        if pfb['python_nolog'] and not isDNSBL['b_ip'] in ('0.0.0.0', '::'):
            return True

        # Increment dnsblgroup counter
        if pfb['sqlite3_dnsbl_con'] and isDNSBL['group'] != '':
            write_sqlite(2, isDNSBL['group'], True)

        dupEntry = '+'
        lastEvent = dnsblDB.get('last-event')
        if lastEvent is not None:
            if str(lastEvent) == str(isDNSBL):
                dupEntry = '-'
            else:
                dnsblDB['last-event'] = isDNSBL
        else:
            dnsblDB['last-event'] = isDNSBL

        # Skip logging
        if isDNSBL['log'] == '2':
            return True

        m_type = isDNSBL['b_type']

        q_ip = get_q_ip_comm(kwargs)
        if q_ip == 'Unknown':
            q_ip = '127.0.0.1'

        for i in range(2):
            try:
                timestamp = datetime.now().strftime("%b %-d %H:%M:%S")
            except TypeError:
                pass
                continue
            break

        csv_line = ','.join('{}'.format(v) for v in ('DNSBL-python', timestamp, q_name, q_ip, isDNSBL['p_type'], isDNSBL['b_type'], isDNSBL['group'], isDNSBL['b_eval'], isDNSBL['feed'], dupEntry))
        log_entry(csv_line, '/var/log/pfblockerng/dnsbl.log')
        log_entry(csv_line, '/var/log/pfblockerng/unified.log')

    return True


def log_entry(line, log):
    for i in range(1,5):
        try:
            with open(log, 'a') as append_log:
                append_log.write(line + '\n')
        except Exception as e:
            if i == 4:
                sys.stderr.write("[pfBlockerNG]: log_entry: {}: {}" .format(i, e))
            time.sleep(0.25)
            pass
            continue
        break


def get_details_reply(m_type, qinfo, qstate, rep, kwargs):
    global pfb, rcodeDB, dnsblDB, noAAAADB, maxmindReader

    if qstate and qstate is not None:
        q_name = get_q_name_qstate(qstate)
    elif qinfo and qinfo is not None:
        q_name = get_q_name_qinfo(qinfo)
    else:
        return True

    q_ip = get_q_ip_comm(kwargs)
    if q_ip == 'Unknown' or q_ip == '127.0.0.1':
        q_ip = '127.0.0.1'
        m_type = 'resolver'

    o_type = get_q_type(qstate, qinfo)
    if m_type == 'cache' or o_type == 'PTR':
        q_type = o_type
    else:
        q_type = get_o_type(qstate, rep)

    # Collect 'python_control' and 'noAAAA' events from inplace_cb_reply
    if m_type == 'reply-x':
        is_reply = False
        if q_name.startswith('python_control.'):
            is_reply = True
        if not is_reply and q_type == 'AAAA' and noAAAADB.get(q_name) is not None:
            is_reply = True

        if not is_reply:
            return True
        m_type = 'reply'

    # Increment totalqueries counter (Don't include the Resolver DNS requests)
    if pfb['sqlite3_resolver_con'] and q_ip != '127.0.0.1':
        write_sqlite(1, '', True)

    # Do not log Replies, if disabled
    if not pfb['python_reply']:
        return True

    r_addr = ''
    if rep and rep is not None:
        if rep.an_numrrsets and rep.an_numrrsets > 0:
            for i in range(0, rep.an_numrrsets):
                if rep.rrsets[i].rk and rep.rrsets[i].entry.data:
                    e = rep.rrsets[i].rk
                    if e.type_str:
                        d = rep.rrsets[i].entry.data
                        if e.type_str == 'CNAME' and d.count > 1:
                            continue

                        for j in range(0, d.count):
                            x = d.rr_data[j]
                            if e.type_str == 'A':
                                r_addr = convert_ipv4(x)
                                break
                            elif e.type_str == 'AAAA':
                                if pfb['mod_ipaddress']:
                                    r_addr = convert_ipv6(x)
                                    try:
                                        if pfb['py_v3']:
                                            r_addr = ipaddress.ip_address(r_addr).compressed
                                        else:
                                            r_addr = ipaddress.ip_address(unicode(r_addr)).compressed
                                    except Exception as e:
                                        sys.stderr.write("[pfBlockerNG]: Failed to compress IPv6: {}, {}" .format(r_addr, e))
                                        pass
                                break
                            elif e.type_str in ('DNSKEY', 'DS'):
                                r_addr = 'DNSSEC'
                                break
                            else:
                                r_addr = r_addr + '|' + convert_other(x)
                                r_addr = r_addr.strip('|')
                            if not r_addr:
                                r_addr = 'NXDOMAIN'

        else:
            # No Answer section found
            r_addr = 'NXDOMAIN'

    # Collect RCODE for non-NOError codes
    try:
        if qstate and qstate.return_rcode is not None and qstate.return_rcode != 0:
            isrcode = rcodeDB.get(qstate.return_rcode)
            if isrcode is not None:
               r_addr = isrcode
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: RCODE {}: {}" .format(e, q_name))
        pass

    r_addr = is_unknown(r_addr)

    if q_type == 'SOA' and r_addr == 'NXDOMAIN':
        r_addr = 'SOA'

    if q_type == 'NSEC3' and r_addr == 'NXDOMAIN':
        r_addr = 'NSEC3'

    if q_type == 'NS' and q_name == 'Unknown':
        q_name = 'NS'

    # Determine if domain was noAAAA blocked
    if r_addr == 'NXDOMAIN' and q_type == 'AAAA' and noAAAADB.get(q_name) is not None:
        r_addr = 'noAAAA'

    if pfb['python_maxmind'] and r_addr not in ('', 'Unknown', 'NXDOMAIN', 'NODATA', 'DNSSEC', 'SOA', 'NS'):
        try:
            if pfb['py_v3']:
                version = ipaddress.ip_address(r_addr).version
            else:
                version = ipaddress.ip_address(unicode(r_addr)).version

        except Exception as e:
            version = ''
            pass

        if version != '':
            try:
                if pfb['py_v3']:
                    isPrivate = ipaddress.ip_address(r_addr).is_private
                    isLoopback = ipaddress.ip_address(r_addr).is_loopback
                else:
                    isPrivate = ipaddress.ip_address(unicode(r_addr)).is_private
                    isLoopback = ipaddress.ip_address(unicode(r_addr)).is_loopback

                if isPrivate:
                    iso_code = 'prv'
                elif isLoopback:
                    iso_code = 'l.b.'
                else:
                    geoip = maxmindReader.get(r_addr)
                    if geoip:
                        if 'country' in geoip:
                            country = geoip['country']
                            if 'iso_code' in country:
                                iso_code = geoip['country']['iso_code']
                            else:
                                iso_code = 'unk'
                        elif 'continent' in geoip:
                            continent = geoip['continent']
                            if 'code' in continent:
                                iso_code = geoip['continent']['code']
                            else:
                                iso_code = 'unk'
                        else:
                            iso_code = 'unk'
                    else:
                        iso_code = 'unk'

            except Exception as e:
                sys.stderr.write("[pfBlockerNG]: MaxMind Reader failed: {}: IP: {}" .format(e, r_addr))
                iso_code = 'unk'
                pass
        else:
            iso_code = 'unk'
    else:
        iso_code = 'unk'

    ttl = get_rep_ttl(rep)
    # Cached TTLs are in unix timestamp (time remaining)
    if m_type == 'cache' and ttl.isdigit():
        ttl = int(ttl) - int(time.time())

    for i in range(2):
        try:
            timestamp = datetime.now().strftime("%b %-d %H:%M:%S")
        except TypeError:
            pass
            continue
        break

    csv_line = ','.join('{}'.format(v) for v in ('DNS-reply', timestamp, m_type, o_type, q_type, ttl, q_name, q_ip, r_addr, iso_code))
    log_entry(csv_line, '/var/log/pfblockerng/dns_reply.log')
    log_entry(csv_line, '/var/log/pfblockerng/unified.log')

    return True


# Is sleep duration valid
def python_control_duration(duration):

    try:
        if duration.isnumeric():
            duration = int(duration)
            if (0 < duration <= 3600):
                return duration
            return False
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_duration: {}" .format(e))
        pass
    return False


# Is thread still active
def python_control_thread(tname):
    global threads

    try:
        for t in threading.enumerate():
            if t.name == tname:
                return True
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_thread: {}" .format(e))
        pass
    return False

# Python_control Start Thread
def python_control_start_thread(tname, fcall, arg1, arg2):
    global threads

    try:
        t1 = threading.Thread(name=tname, target=fcall, args=(arg1, arg2), daemon=True)
        threads.append(t1)
        t1.start()
        return True
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_start_thread: {}" .format(e))
        pass
    return False


# Python_control sleep timer
def python_control_sleep(duration, arg):
    global pfb

    try:
        time.sleep(duration)
        pfb['python_blacklist'] = True;
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_sleep: {}" .format(e))
        pass
    return True


# Python_control Add Bypass IP for specified duration
def python_control_addbypass(duration, b_ip):
    global pfb, gpListDB

    try:
        time.sleep(duration)
        if gpListDB.get(b_ip) is not None:
            gpListDB.pop(b_ip)
            return True
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_addbypass: {}" .format(e))
        pass
    return False

def inplace_cb_reply(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('reply-x', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_cache(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('cache', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_local(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('local', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_servfail(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('servfail', qinfo, qstate, rep, kwargs)
    return True

def deinit(id):
    global pfb, maxmindReader

    if pfb['python_maxmind']:
        maxmindReader.close()

    log_info('[pfBlockerNG]: pfb_unbound.py script exiting')
    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def operate(id, event, qstate, qdata):
    global pfb, threads, dataDB, zoneDB, hstsDB, whiteDB, excludeDB, excludeAAAADB, dnsblDB, noAAAADB, gpListDB, safeSearchDB, feedGroupIndexDB

    qstate_valid = False
    try:
        if qstate is not None and qstate.qinfo.qtype is not None:
            qstate_valid = True
            q_type = qstate.qinfo.qtype
            q_name_original = get_q_name_qstate(qstate).lower()
            q_ip = get_q_ip(qstate)
        else:
            sys.stderr.write("[pfBlockerNG] qstate is not None and qstate.qinfo.qtype is not None")
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] qstate_valid: {}: {}" .format(event, e))
        pass

    if (event == MODULE_EVENT_NEW) or (event == MODULE_EVENT_PASS):

        # no AAAA validation
        if qstate_valid and q_type == RR_TYPE_AAAA and pfb['noAAAADB'] and q_name_original not in excludeAAAADB:
            isin_noAAAA = False

            # Determine full domain match
            isnoAAAA = noAAAADB.get(q_name_original)
            if isnoAAAA is not None:
                isin_noAAAA = True

            # Wildcard verification of domain
            if not isin_noAAAA:
                q = q_name_original.split('.', 1)
                q = q[-1]

                # Validate to 2nd level TLD only
                for x in range(q.count('.'), 0, -1):
                    isnoAAAA = noAAAADB.get(q)

                    # Determine if domain is a wildcard whitelist entry
                    if isnoAAAA is not None and isnoAAAA:
                        isin_noAAAA = True

                        # Add sub-domain to noAAAA DB
                        noAAAADB[q_name_original] = True

                        break
                    else:
                        q = q.split('.', 1)
                        q = q[-1]

            # Create FQDN Reply Message (AAAA -> A)
            if isin_noAAAA:
                msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
                if msg is None or not msg.set_return_msg(qstate):
                    qstate.ext_state[id] = MODULE_ERROR
                    return True

                qstate.return_rcode = RCODE_NOERROR
                qstate.return_msg.rep.security = 2
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            # Add domain to excludeAAAADB to skip subsequent validation
            else:
                excludeAAAADB.append(q_name_original)

        # SafeSearch Redirection validation
        if qstate_valid and pfb['safeSearchDB']:
            isSafeSearch = safeSearchDB.get(q_name_original)

            # Validate 'www.' Domains
            if isSafeSearch is None and not q_name_original.startswith('www.'):
                isSafeSearch = safeSearchDB.get('www.' + q_name_original)

            if isSafeSearch is not None:

                ss_found = False
                if isSafeSearch['A'] == 'nxdomain':
                    qstate.return_rcode = RCODE_NXDOMAIN
                    qstate.ext_state[id] = MODULE_FINISHED
                    return True

                elif isSafeSearch['A'] == 'cname':
                    if isSafeSearch['AAAA'] is not None:

                        if q_type == RR_TYPE_A:
                            cname_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RD | PKT_RA)
                            cname_msg.answer.append("{} 3600 IN CNAME {}" .format(qstate.qinfo.qname_str, isSafeSearch['AAAA']))
                            ss_found = True
                        elif q_type == RR_TYPE_AAAA:
                            cname_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_AAAA, RR_CLASS_IN, PKT_QR | PKT_RD | PKT_RA | PKT_AA)
                            cname_msg.answer.append("{} 3600 IN CNAME {}" .format(qstate.qinfo.qname_str, isSafeSearch['AAAA']))
                            ss_found = True

                        if ss_found:
                            if cname_msg is None or not cname_msg.set_return_msg(qstate):
                                qstate.ext_state[id] = MODULE_ERROR
                                return True

                            qstate.ext_state[id] = MODULE_WAIT_MODULE
                            return True
                else:
                    if q_type == RR_TYPE_A or (q_type == RR_TYPE_AAAA and isSafeSearch['AAAA'] == ''):
                        msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
                        msg.answer.append("{} 300 IN {} {}" .format(qstate.qinfo.qname_str, 'A', isSafeSearch['A']))
                        ss_found = True
                    elif q_type == RR_TYPE_AAAA and isSafeSearch['AAAA'] != '':
                        msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_AAAA, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
                        msg.answer.append("{} 300 IN {} {}" .format(qstate.qinfo.qname_str, 'AAAA', isSafeSearch['AAAA']))
                        ss_found = True

                if ss_found:
                    if msg is None or not msg.set_return_msg(qstate):
                        qstate.ext_state[id] = MODULE_ERROR
                        return True
 
                    qstate.return_rcode = RCODE_NOERROR
                    qstate.return_msg.rep.security = 2
                    qstate.ext_state[id] = MODULE_FINISHED
                    return True

        # Python_control - Receive TXT commands from pfSense local IP
        if qstate_valid and q_type == RR_TYPE_TXT and q_name_original.startswith('python_control.'):

            control_rcd = False
            if pfb['python_control'] and q_ip == '127.0.0.1':

                control_command = q_name_original.split('.')
                if (len(control_command) >= 2):

                    if control_command[1] == 'disable':
                        control_rcd = True
                        control_msg = 'Python_control: DNSBL disabled'
                        pfb['python_blacklist'] = False

                        # If duration specified, disable DNSBL Blocking for specified time in seconds
                        if pfb['mod_threading'] and len(control_command) == 3 and control_command[2] != '':

                            # Validate Duration argument
                            duration = python_control_duration(control_command[2])
                            if duration:

                                # Ensure thread is not active
                                if not python_control_thread('sleep'):

                                    # Start Thread
                                    if not python_control_start_thread('sleep', python_control_sleep, duration, None):
                                        control_rcd = False
                                        control_msg = 'Python_control: DNSBL disabled: Thread failed'
                                    else:
                                        control_msg = "{} for {} second(s)" .format(control_msg, duration)
                                else:
                                    control_rcd = False
                                    control_msg = 'Python_control: DNSBL disabled: Previous call still in progress'
                            else:
                                control_rcd = False
                                control_msg = "Python_control: DNSBL disabled: duration [ {} ] out of range (1-3600sec)" .format(control_command[2])

                    elif control_command[1] == 'enable':
                        control_rcd = True
                        control_msg = 'Python_control: DNSBL enabled'
                        pfb['python_blacklist'] = True;

                    elif control_command[1] == 'addbypass' or control_command[1] == 'removebypass':
                        b_ip = (control_command[2]).replace('-', '.')
                        if pfb['py_v3']:
                            isIPValid = ipaddress.ip_address(b_ip)
                        else:
                            isIPValid = ipaddress.ip_address(unicode(b_ip))

                        if isIPValid:
                            if not pfb['gpListDB']:
                                pfb['gpListDB'] = True

                            control_rcd = True
                            if control_command[1] == 'addbypass':
                                control_msg = "Python_control: Add bypass for IP: [ {} ]" .format(b_ip)

                                # If duration specified, disable DNSBL Blocking for specified time in seconds
                                if pfb['mod_threading'] and len(control_command) == 4 and control_command[3] != '':

                                    # Validate Duration argument
                                    duration = python_control_duration(control_command[3])
                                    if duration:

                                        # Ensure thread is not active
                                        if not python_control_thread('addbypass' + b_ip):

                                            # Start Thread
                                            if not python_control_start_thread('addbypass' + b_ip, python_control_addbypass, duration, b_ip):
                                                control_rcd = False
                                                control_msg = "Python_control: Add bypass for IP: [ {} ] thread failed" .format(b_ip)
                                            else:
                                                control_msg = "{} for {} second(s)" .format(control_msg, duration)
                                        else:
                                            control_rcd = False
                                            control_msg = "Python_control: Add bypass for IP: [ {} ]: Previous call still in progress" .format(b_ip)
                                    else:
                                        control_rcd = False
                                        control_msg = "Python_control: Add bypass for IP: [ {} ]: duration [ {} ] out of range (1-3600sec)" .format(b_ip, control_command[3])
                                else:
                                    # Add bypass called without duration
                                    if control_rcd:
                                        gpListDB[b_ip] = 0

                            elif control_command[1] == 'removebypass':
                                if gpListDB.get(b_ip) is not None:
                                    control_msg = "Python_control: Remove bypass for IP: [ {} ]" .format(b_ip)
                                    gpListDB.pop(b_ip)
                                else:
                                    control_msg = "Python_control: IP not in Group Policy: [ {} ]" .format(b_ip)

                if control_rcd:
                    q_reply = 'python_control'
                else:
                    if control_msg == '':
                        control_msg = "Python_control: Command not authorized! [ {} ]" .format(q_name_original)
                    q_reply = 'python_control_fail'

                txt_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_TXT, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
                txt_msg.answer.append("{}. 0 IN TXT \"{}\"" .format(q_reply, control_msg))

                if txt_msg is None or not txt_msg.set_return_msg(qstate):
                     qstate.ext_state[id] = MODULE_ERROR
                     return True

                qstate.return_rcode = RCODE_NOERROR
                qstate.return_msg.rep.security = 2
                qstate.ext_state[id] = MODULE_FINISHED 
                return True
 
    # DNSBL Validation for specific RR_TYPES only
    if qstate_valid and pfb['python_blacklist'] and q_type in pfb['rr_types']:

        # Group Policy - Bypass DNSBL Validation
        bypass_dnsbl = False
        if pfb['gpListDB']:
            q_ip = get_q_ip(qstate)

            if q_ip != 'Unknown':
                isgpBypass = gpListDB.get(q_ip)

                if isgpBypass is not None:
                    bypass_dnsbl = True

        # Create list of Domain/CNAMES to be evaluated
        validate = []

        # Skip 'in-addr.arpa' domains
        if not q_name_original.endswith('.in-addr.arpa') and not bypass_dnsbl:
            validate.append(q_name_original)

            # DNSBL CNAME Validation
            if pfb['python_cname'] and qstate.return_msg:
                r = qstate.return_msg.rep
                if r.an_numrrsets > 1:
                    for i in range (0, r.an_numrrsets):
                        rr = r.rrsets[i]

                        if rr.rk.type_str != 'CNAME':
                            continue

                        for j in range(0, rr.entry.data.count):
                            domain = convert_other(rr.entry.data.rr_data[j]).lower()
                            if domain != 'Unknown':
                                validate.append(domain)

        isCNAME = False
        for val_counter, q_name in enumerate(validate, start=1):

            if val_counter > 1:
                isCNAME = True

            # Determine if domain has been previously validated
            if q_name not in excludeDB:

                q_type_str = qstate.qinfo.qtype_str
                isFound = False
                log_type = False
                isInWhitelist = False
                isInHsts = False
                b_type = 'Python'
                p_type = 'Python'
                feed = 'Unknown'
                group = 'Unknown'

                # print "v0: " + q_name

                # Determine if domain was previously DNSBL blocked
                isDomainInDNSBL = dnsblDB.get(q_name)
                if isDomainInDNSBL is None:
                    tld = get_tld(qstate)

                    # Determine if domain is in DNSBL 'data|zone' database
                    if pfb['python_blocking']:

                        # Determine if domain is in DNSBL 'data' database (log to dnsbl.log)
                        isDomainInData = False
                        if pfb['dataDB']:
                            isDomainInData = dataDB.get(q_name)
                            if isDomainInData is not None:
                                #print q_name + ' data: ' + str(isDomainInData) 
                                isFound = True
                                log_type = isDomainInData['log']

                                # Collect Feed/Group
                                feedGroup = feedGroupIndexDB.get(isDomainInData['index'])
                                if feedGroup is not None:
                                    feed = feedGroup['feed']
                                    group = feedGroup['group']

                                b_type = 'DNSBL'
                                b_eval = q_name

                        # Determine if domain is in DNSBL 'zone' database (log to dnsbl.log)
                        if not isFound and pfb['zoneDB']:
                            q = q_name
                            for x in range(q.count('.') +1, 0, -1):
                                isDomainInZone = zoneDB.get(q)
                                if isDomainInZone is not None:
                                    #print q_name + ' zone: ' + str(isDomainInZone)
                                    isFound = True
                                    log_type = isDomainInZone['log']

                                    # Collect Feed/Group
                                    feedGroup = feedGroupIndexDB.get(isDomainInZone['index'])
                                    if feedGroup is not None:
                                        feed = feedGroup['feed']
                                        group = feedGroup['group']

                                    b_type = 'TLD'
                                    b_eval = q
                                    break
                                else:
                                    q = q.split('.', 1)
                                    q = q[-1]

                    # Validate other python methods, if not blocked via DNSBL zone/data
                    if not isFound:

                        # Allow only approved TLDs
                        if pfb['python_tld'] and tld != '' and q_name not in (pfb['dnsbl_ipv4'], '::' + pfb['dnsbl_ipv4']) and tld not in pfb['python_tlds']:
                            isFound = True
                            feed = 'TLD_Allow'
                            group = 'DNSBL_TLD_Allow'

                        # Block IDN or 'xn--' Domains
                        if not isFound and pfb['python_idn'] and (q_name.startswith('xn--') or '.xn--' in q_name):
                            isFound = True
                            feed = 'IDN'
                            group = 'DNSBL_IDN'

                        # Block via Regex
                        if not isFound and pfb['regexDB']:
                            isRegexMatch = pfb_regex_match(q_name)
                            #print q_name + ' regex: ' + str(isRegexMatch)
                            if isRegexMatch:
                                isFound = True
                                feed = isRegexMatch
                                group = 'DNSBL_Regex'

                        if isFound:
                            b_eval = q_name
                            log_type = '1'

                    # Validate domain in DNSBL Whitelist
                    if isFound and pfb['whiteDB']:
                        # print q_name + ' w'

                        # Create list of Domain/CNAMES to be validated against Whitelist
                        whitelist_validate = []
                        whitelist_validate.append(q_name)

                        if isCNAME:
                            whitelist_validate.append(q_name_original)

                        for w_q_name in whitelist_validate:

                            # Determine full domain match
                            isDomainInWhitelist = whiteDB.get(w_q_name)
                            if isDomainInWhitelist is not None:
                                isInWhitelist = True
                            elif w_q_name.startswith('www.'):
                               isDomainInWhitelist = whiteDB.get(w_q_name[4:])
                               if isDomainInWhitelist is not None:
                                    isInWhitelist = True

                            # Determine TLD segment matches
                            if not isInWhitelist:
                                q = w_q_name.split('.', 1)
                                q = q[-1]
                                for x in range(q.count('.') +1, 0, -1):
                                    if x >= pfb['python_tld_seg']:
                                        isDomainInWhitelist = whiteDB.get(q)

                                        # Determine if domain is a wildcard whitelist entry
                                        if isDomainInWhitelist is not None and isDomainInWhitelist:
                                            isInWhitelist = True
                                            break
                                        else:
                                            q = q.split('.', 1)
                                            q = q[-1]

                    # Add domain to excludeDB to skip subsequent blacklist validation
                    if not isFound or isInWhitelist:
                        #print "Add to Pass: " + q_name 
                        excludeDB.append(q_name)

                    # Domain to be blocked and is not whitelisted
                    if isFound and not isInWhitelist:

                        # Determine if domain is in HSTS database (Null blocking)
                        if pfb['hstsDB']:
                            #print q_name + ' hsts:'

                            # Determine if TLD is in HSTS database
                            if tld in pfb['hsts_tlds']:
                                isInHsts = True
                                p_type = 'HSTS_TLD'
                                #print q_name + " HSTS"
                            else:
                                q = q_name
                                for x in range(q.count('.') +1, 0, -2):
                                    # print q_name + ' validate: ' + q
                                    isDomainInHsts = hstsDB.get(q)
                                    if isDomainInHsts is not None:
                                        #print q_name + " q: " + q + " HSTS blacklist"
                                        isInHsts = True
                                        if q_type_str in pfb['rr_types2']:
                                            p_type = 'HSTS_' + q_type_str
                                        else:
                                            p_type = 'HSTS'
                                        break
                                    else:
                                        q = q.split('.', 1)
                                        q = q[-1]

                                # print q_name + ' break'

                        # Determine blocked IP type (DNSBL VIP vs Null Blocking)
                        if not isInHsts:
                            # A/AAAA RR_Types
                            if q_type_str in pfb['rr_types2']:
                                if log_type:
                                    b_ip = pfb['dnsbl_ip'][q_type_str][log_type]
                                else:
                                    b_ip = pfb['dnsbl_ip'][q_type_str]['0']

                            # All other RR_Types (use A RR_Type)
                            else:
                                if log_type:
                                    b_ip = pfb['dnsbl_ip']['A'][log_type]
                                else:
                                    b_ip = pfb['dnsbl_ip']['A']['0']

                            # print q_name + ' ' + str(qstate.qinfo.qtype) + ' ' + q_type_str

                        else:
                            if q_type_str in pfb['rr_types2']:
                                b_ip = pfb['dnsbl_ip'][q_type_str]['0']
                            else:
                                b_ip = pfb['dnsbl_ip']['A']['0']


                        # Add 'CNAME' suffix to Block type (CNAME Validation)
                        if isCNAME:
                            b_type = b_type + '_CNAME'
                            q_name = q_name_original

                        # Add q_type to b_type (Block type)
                        b_type = b_type + '_' + q_type_str

                        # Skip subsequent DNSBL validation for domain, and add domain to dict for get_details_dnsbl function
                        dnsblDB[q_name] = {'qname': q_name, 'b_type': b_type, 'p_type': p_type, 'b_ip': b_ip, 'log': log_type, 'feed': feed, 'group': group, 'b_eval': b_eval }
                        # Skip subsequent DNSBL validation for original domain (CNAME validation), and add domain to dict for get_details_dnsbl function
                        if isCNAME and dnsblDB.get(q_name_original) is None:
                            dnsblDB[q_name_original] = {'qname': q_name_original, 'b_type': b_type, 'p_type': p_type, 'b_ip': b_ip, 'log': log_type, 'feed': feed, 'group': group, 'b_eval': b_eval }

                        # Add domain data to DNSBL cache for Reports tab
                        write_sqlite(3, '', [b_type, q_name, group, b_eval, feed])

                # Use previously blocked domain details
                else:
                    b_ip = isDomainInDNSBL['b_ip']
                    b_type = isDomainInDNSBL['b_type']
                    isFound = True
                    # print "v: " + q_name 

                if isFound and not isInWhitelist:

                    # Default RR_TYPE ANY -> A
                    if q_type == RR_TYPE_ANY:
                        q_type = RR_TYPE_A
                        q_type_str = 'A'

                    # print q_name + ' Blocked ' + b_ip + ' ' + q_type_str

                    # Create FQDN Reply Message
                    msg = DNSMessage(qstate.qinfo.qname_str, q_type, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
                    msg.answer.append("{}. 60 IN {} {}" .format(q_name, q_type_str, b_ip))

                    if msg is None or not msg.set_return_msg(qstate):
                        qstate.ext_state[id] = MODULE_ERROR
                        return True

                    # Log entry
                    kwargs = {'pfb_addr': q_ip}
                    if qstate.return_msg:
                        get_details_dnsbl('dnsbl', None, qstate, qstate.return_msg.rep, kwargs)
                    else:
                        get_details_dnsbl('dnsbl', None, qstate, None, kwargs)

                    qstate.return_rcode = RCODE_NOERROR
                    qstate.return_msg.rep.security = 2
                    qstate.ext_state[id] = MODULE_FINISHED
                    return True

    if (event == MODULE_EVENT_NEW) or (event == MODULE_EVENT_PASS):
        qstate.ext_state[id] = MODULE_WAIT_MODULE  
        return True

    if event == MODULE_EVENT_MODDONE:

        # Log entry
        if qstate_valid and qstate.return_msg:
            kwargs = {'pfb_addr': q_ip}
            get_details_reply('reply', None, qstate, qstate.return_msg.rep, kwargs)
        else:
            get_details_reply('reply', None, qstate, None, None)

        qstate.ext_state[id] = MODULE_FINISHED
        return True

    log_err('[pfBlockerNG]: BAD event')
    qstate.ext_state[id] = MODULE_ERROR
    return True

log_info('[pfBlockerNG]: pfb_unbound.py script loaded')
