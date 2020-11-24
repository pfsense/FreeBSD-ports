# pfb_unbound.py
# pfBlockerNG - Unbound resolver python integration

# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
# Copyright (c) 2015-2020 BBcan177@gmail.com
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
    global pfb, dataDB, zoneDB, regexDB, hstsDB, whiteDB, excludeDB, dnsblDB, feedGroupIndexDB, maxmindReader

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
    for l_file in ('dnsbl', 'dns_reply'):
        try:
            lfile = '/var/log/pfblockerng/' + l_file + '.log'
            if os.path.isfile(lfile) and not os.access(lfile, os.W_OK):
                new_file = '/var/log/pfblockerng/' + l_file + str(datetime.now().strftime("_%Y%m%d%H%M%S.log"))
                os.rename(lfile, new_file)
        except Exception as e:
            sys.stderr.write("[pfBlockerNG]: Failed to validate write permission: {}.log: {}" .format(l_file, e))
            pass

    if not pfb['mod_ipaddress']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'ipaddress': {}" .format(pfb['mod_ipaddress_e']))

    if not pfb['mod_maxminddb']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'maxminddb': {}" .format(pfb['mod_maxminddb_e']))

    if not pfb['mod_sqlite3']:
        sys.stderr.write("[pfBlockerNG]: Failed to load python module 'sqlite3': {}" .format(pfb['mod_sqlite3_e']))

    # Initialize default settings
    pfb['dnsbl_ipv4'] = ''
    pfb['dnsbl_ipv6'] = ''
    pfb['regexDB'] = False
    pfb['whiteDB'] = False
    pfb['python_idn'] = False
    pfb['python_ipv6'] = False
    pfb['python_hsts'] = False
    pfb['python_reply'] = False
    pfb['python_cname'] = False
    pfb['python_enable'] = False
    pfb['python_nolog'] = False
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
    pfb['pfb_py_dnsbl'] = 'pfb_py_dnsbl.sqlite'
    pfb['pfb_py_resolver'] = 'pfb_py_resolver.sqlite'
    pfb['maxminddb'] = '/usr/local/share/GeoIP/GeoLite2-Country.mmdb'

    # DNSBL validation on these RR_TYPES only
    pfb['rr_types'] = (RR_TYPE_A, RR_TYPE_AAAA, RR_TYPE_ANY, RR_TYPE_CNAME, RR_TYPE_DNAME, \
                       RR_TYPE_MX, RR_TYPE_NS, RR_TYPE_PTR, RR_TYPE_SRV, RR_TYPE_TXT)

    pfb['rr_types2'] = ('A', 'AAAA')

    # List of HSTS preload TLDs
    pfb['hsts_tlds'] = ('android', 'app', 'bank', 'chrome', 'dev', 'foo', 'gle', 'gmail', 'google', 'hangout', \
                        'insurance', 'meet', 'new', 'page', 'play', 'search', 'youtube')

    # Initialize dicts/lists
    dataDB = defaultdict(list)
    zoneDB = defaultdict(list)
    dnsblDB = defaultdict(list)
    regexDB = defaultdict(str)
    whiteDB = defaultdict(str)
    hstsDB = defaultdict(str)
    feedGroupDB = defaultdict(str)
    feedGroupIndexDB = defaultdict(list)
    excludeDB = []

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

            if pfb['python_ipv6']:
                pfb['dnsbl_ipv6'] = '::' + pfb['dnsbl_ipv4']
            else:
                pfb['dnsbl_ipv6'] = '::1'

            # DNSBL IP/Log types
            pfb['dnsbl_ip'] = {'A': {'0': '0.0.0.0', '1': pfb['dnsbl_ipv4'], '2': '0.0.0.0'},
                               'AAAA': {'0': '::1', '1': pfb['dnsbl_ipv6'], '2': '::1'} }

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
                        except re.error as e:
                            for a in e:
                                sys.stderr.write("[pfBlockerNG]: Regex [ {} ] compile error pattern [  {}  ] on line #{}: {}" .format(name, pattern, r_count, a))
                            pass
                        r_count += 1

            # While reading 'data|zone' CSV files: Replace 'Feed/Group' pairs with an index value (Memory performance)
            feedGroup_index = 0

            # Zone dicts
            pfb['zoneDB'] = False
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
            pfb['dataDB'] = False
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
                pfb['whiteDB'] = False
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
                pfb['hstsDB'] = False
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
    if kwargs and kwargs is not None and kwargs['repinfo'] and kwargs['repinfo'].addr:
        q_ip = kwargs['repinfo'].addr
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
    return str(is_unknown(ttl))


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
            elif val == 58:
                i = ':'
            elif val <= 32 or val > 126:
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

            sqlite3Db.commit()
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: Failed to write to sqlite3 db {}: {}" .format(db_file, e))
        if sqlite3Db:
            sqlite3Db.close()
        return False
    finally:
        if sqlite3Db:
            sqlite3Db.close()

    return True


def get_details(m_type, qinfo, qstate, rep, kwargs):
    global pfb, dnsblDB, maxmindReader

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
        if pfb['python_nolog'] and not isDNSBL['b_ip'] in ('0.0.0.0', '::1'):
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
        q_ip = get_q_ip(qstate)

        log_name = 'DNSBL-python'
        log_file = 'dnsbl.log'

    else:

        # Do not log Replies, if disabled
        if not pfb['python_reply']:
            return True

        log_name = 'DNS-reply'
        log_file = 'dns_reply.log'

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

        r_addr = is_unknown(r_addr)
        o_type = get_q_type(qstate, qinfo)
        if m_type == 'cache' or o_type == 'PTR':
            q_type = o_type
        else:
            q_type = get_o_type(qstate, rep)

        # Determine NODATA replies
        if q_type == 'SOA' and r_addr == 'NXDOMAIN':
            r_addr = 'NODATA'

        if pfb['python_maxmind'] and r_addr not in ('', 'Unknown', 'NXDOMAIN', 'NODATA'):
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

        if m_type == 'reply':
            q_ip = get_q_ip(qstate)
        else:
            q_ip = get_q_ip_comm(kwargs)

    ttl = get_rep_ttl(rep)
    # Cached TTLs are in unix timestamp (time remaining)
    if m_type == 'cache' and ttl.isdigit():
        ttl = int(ttl) - int(time.time())

    for i in range(2):
        try:
            timestamp = datetime.now().strftime("%b %d %H:%M:%S")
        except TypeError:
            pass
            continue
        break

    if log_name != 'DNSBL-python':
        csv_line = ','.join('{}'.format(v) for v in (log_name, timestamp, m_type, o_type, q_type, ttl, q_name, q_ip, r_addr, iso_code))
    else:
        csv_line = ','.join('{}'.format(v) for v in (log_name, timestamp, q_name, q_ip, isDNSBL['p_type'], isDNSBL['b_type'], isDNSBL['group'], isDNSBL['b_eval'], isDNSBL['feed'], dupEntry))

    with open('/var/log/pfblockerng/' + log_file, 'a') as append_log:
        append_log.write(csv_line + '\n')
    return True

def inplace_cb_reply(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details('reply', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_cache(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details('cache', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_local(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details('local', qinfo, qstate, rep, kwargs)
    return True

def inplace_cb_reply_servfail(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details('servfail', qinfo, qstate, rep, kwargs)
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
    global pfb, dataDB, zoneDB, hstsDB, whiteDB, excludeDB, dnsblDB, feedGroupIndexDB

    # DNSBL Validation for specific RR_TYPES only
    if pfb['python_blacklist'] and qstate is not None and qstate.qinfo.qtype is not None and qstate.qinfo.qtype in pfb['rr_types']:
        q_name_original = get_q_name_qstate(qstate)
        q_type = qstate.qinfo.qtype

	# Create list of Domain/CNAMES to be evaluated
        validate = []

        # Skip 'in-addr.arpa' domains
        if not q_name_original.endswith('.in-addr.arpa'):
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
                            domain = convert_other(rr.entry.data.rr_data[j])
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
                        if pfb['python_tld'] and tld != '' and tld not in pfb['python_tlds']:
                            isFound = True
                            feed = 'TLD_Allow'
                            group = 'DNSBL_TLD_Allow'

                        # Block IDN or 'xn--' Domains
                        if not isFound and pfb['python_idn'] and q_name.startswith('xn--') or '.xn--' in q_name:
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

                        # Determine full domain match
                        isDomainInWhitelist = whiteDB.get(q_name)
                        if isDomainInWhitelist is not None:
                            isInWhitelist = True
                        elif q_name.startswith('www.'):
                            isDomainInWhitelist = whiteDB.get(q_name[4:])
                            if isDomainInWhitelist is not None:
                                isInWhitelist = True

                        # Determine TLD segment matches
                        if not isInWhitelist:
                            q = q_name.split('.', 1)
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

                        # Skip subsequent DNSBL validation for domain, and add domain to dict for get_details function
                        dnsblDB[q_name] = {'qname': q_name, 'b_type': b_type, 'p_type': p_type, 'b_ip': b_ip, 'log': log_type, 'feed': feed, 'group': group, 'b_eval': b_eval}

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

                    qstate.return_msg.rep.security = 2
 
                    qstate.return_rcode = RCODE_NOERROR
                    qstate.ext_state[id] = MODULE_FINISHED
                    return True

    if event == MODULE_EVENT_NEW:
        qstate.ext_state[id] = MODULE_WAIT_MODULE
        return True

    if event == MODULE_EVENT_MODDONE:
        qstate.ext_state[id] = MODULE_FINISHED
        return True

    if event == MODULE_EVENT_PASS:
        qstate.ext_state[id] = MODULE_WAIT_MODULE
        return True

    log_err('[pfBlockerNG]: BAD event')
    qstate.ext_state[id] = MODULE_ERROR
    return True

log_info('[pfBlockerNG]: pfb_unbound.py script loaded')
