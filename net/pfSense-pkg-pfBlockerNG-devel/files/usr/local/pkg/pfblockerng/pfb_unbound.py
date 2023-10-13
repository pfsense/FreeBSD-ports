# pfb_unbound.py
# pfBlockerNG - Unbound resolver python integration

# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2023 Rubicon Communications, LLC (Netgate)
# Copyright (c) 2015-2023 BBcan177@gmail.com
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
from functools import wraps
import inspect
import traceback
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

def traced(func):

    # This is mostly targeted at developers making changes to pfBlockerNG, so no UI is exposed
    @wraps(func)
    def _log(*args, **kwargs):
        # Change this to False to enable logging
        if True:
            return func(*args, **kwargs)
        
        # Early check to prevent getting the name and locals if not needed
        debug('Function call (func={}): args={}, kwargs={}', func.__name__, args, kwargs)
        try: 
            result = func(*args, **kwargs)
            debug('Function call (func={}) result: {}', func.__name__, result)
            return result
        except:
            debug('Exception caught (func={}): \n\t{}', func.__name__, '\t'.join(traceback.format_exc().splitlines(True)))
            raise

    return _log

def init_standard(id, env):
    try:
        init(id, env)
    except:
        message = 'Exception caught\n\t{}\n'.format(timestamp, '\t'.join(traceback.format_exc().splitlines(True)))
        log_err('[pfBlockerNG]: {}'.format(message))
        with open('/var/log/pfblockerng/py_error.log', 'a') as error_log:
            timestamp = datetime.now().strftime("%b %-d %H:%M:%S")
            error_log.write('{}|ERROR| {}'.format(timestamp, message))
        raise

def init(id, env):
    global pfb, rcodeDB, dataDB, wildcardDataDB, zoneDB, regexDataDB, regexDB, hstsDB, whiteDB, wildcardWhiteDB, regexWhiteDB, excludeDB, excludeAAAADB, excludeSS, dnsblDB, noAAAADB, gpListDB, safeSearchDB, maxmindReader

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
    
    # Clear debug file
    debug_logfile = '/var/log/pfblockerng/py_debug.log'
    if os.path.isfile(debug_logfile):
        os.remove(debug_logfile)
        # Touch the file
        open(debug_logfile, 'w').close()

    # Store previous error message to avoid repeating
    pfb['p_err'] = ''

    # Log stderr to file
    class log_stderr(object):
        def __init__(self, logger):
            self.logger = logger
            self.linebuf = ''

        def write(self, msg):
            if msg != pfb['p_err']:
                msg = msg.rstrip()
                self.logger.log(logging.ERROR, msg)
                _debug('[ERROR LOG]: {}', msg)
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
    pfb['python_idn'] = False
    pfb['python_ipv6'] = False
    pfb['python_hsts'] = False
    pfb['python_reply'] = False
    pfb['python_cname'] = False
    pfb['group_policy'] = False
    pfb['python_enable'] = False
    pfb['python_debug'] = False
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
    dataDB = dict()
    wildcardDataDB = dict()
    regexDataDB = dict()
    whiteDB = dict()
    wildcardWhiteDB = dict()
    regexWhiteDB = dict()
    zoneDB = dict()
    dnsblDB = dict()
    safeSearchDB = dict()

    regexDB = dict()
    hstsDB = set()
    gpListDB = set()
    noAAAADB = dict()
    excludeDB = set()
    excludeAAAADB = set()
    excludeSS = set()

    # String deduplication for in-memory databases
    # Less invasive than String interning, gets collected at the end of initialization
    stringDeduplicationDB = dict()
    def dedup(str_val):
        if not str_val:
            return str_val

        cached = stringDeduplicationDB.get(str_val)
        if cached:
            return cached

        stringDeduplicationDB[str_val] = str_val
        return str_val

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
            if config.has_option('MAIN', 'python_debug'):
                pfb['python_debug'] = config.getboolean('MAIN', 'python_debug')
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

            debug('Python mode enabled')

            # Enable the Blacklist functions (IDN)
            if pfb['python_idn']:
                pfb['python_blacklist'] = True
                debug('Python IDN enabled')
                debug('Python Blacklist enabled. Reason: IDN')

            # Enable the Blacklist functions (TLD Allow)
            if pfb['python_tld'] and pfb['python_tlds']:
                pfb['python_blacklist'] = True
                debug('Python TLD Allow enabled: {}', pfb['python_tlds'])
                debug('Python Blacklist enabled. Reason: TLD Allow')

            # Collect user-defined Regex patterns
            if config.has_section('REGEX'):
                regex_config = config.items('REGEX')
                if regex_config:
                    debug('REGEX configuration section found')
                    r_count = 1
                    for name, pattern in regex_config:
                        try:
                            debug('Compiling REGEX: {}', pattern)
                            regexDB[name] = re.compile(pattern, re.IGNORECASE)
                        except Exception as e:
                            sys.stderr.write("[pfBlockerNG]: Regex [ {} ] compile error pattern [  {}  ] on line #{}: {}" .format(name, pattern, r_count, e))
                            pass
                        r_count += 1
                    pfb['python_blacklist'] = True
                    debug('Python Blacklist enabled. Reason: REGEX')

            # Collect user-defined no AAAA domains
            if config.has_section('noAAAA'):
                noaaaa_config = config.items('noAAAA')
                if noaaaa_config:
                    debug('noAAAA configuration section found')
                    try:
                        for row, line in noaaaa_config:
                            line = line.rstrip('\r\n')
                            debug('Parsing no-AAAA domain: {}', line)
                            data = line.rstrip('\r\n').split(',')
                            if data and len(data) == 2:
                                domain_name = data[0].lower()
                                wildcard = data[1] == '1'

                                debug('Parsed no-AAAA domain: {}, wildcard={}', domain_name, wildcard)

                                # if both wildcard and non-wildcard entries are found, keep the wildcard only
                                if wildcard:
                                    noAAAADB[domain_name] = True
                                elif not noAAAADB.get(domain_name):
                                    noAAAADB[domain_name] = False
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: noAAAA: row:{} line:{}" .format(row, line))

                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load no AAAA domain list: {}" .format(e))
                        pass

            # Collect user-defined Group Policy Global Bypass List
            if config.has_section('GP_Bypass_List'):
                gp_bypass_list = config.items('GP_Bypass_List')
                if gp_bypass_list:
                    debug('GP_Bypass_List configuration section found')
                    try:
                        for row, line in gp_bypass_list:
                            value = line.rstrip('\r\n')
                            debug('Parsed Group Policy Bypass entry: {}', value)
                            gpListDB.add(line)
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load GP Bypass List: {}" .format(e))
                        pass 

            # Collect SafeSearch Redirection list
            if os.path.isfile(pfb['pfb_py_ss']):
                try:
                    with open(pfb['pfb_py_ss']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        debug('SafeSearch Redirection file found: {}', pfb['pfb_py_ss'])
                        for row in csv_reader:
                            if row and len(row) == 3:
                                domain_name = row[0].lower()
                                entry = {'A': row[1], 'AAAA': row[2]}
                                debug('Parsed SafeSearch Redirection entry: {}: {}', domain_name, entry)
                                safeSearchDB[domain_name] = entry
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_ss'], row))

                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_ss'], e))
                    pass

            # Zone dicts
            if os.path.isfile(pfb['pfb_py_zone']):
                try:
                    with open(pfb['pfb_py_zone']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        debug('Zone Blacklist file found: {}', pfb['pfb_py_zone'])
                        for row in csv_reader:
                            if row and len(row) >= 6:
                                # Query Feed/Group/index
                                domain_name = row[1].lower()
                                entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5])};
                                debug('Parsed Zone Blacklist entry: {}: {}', domain_name, entry)
                                zoneDB[domain_name] = entry
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_zone'], row))

                        pfb['python_blacklist'] = True
                        debug('Python Blacklist enabled. Reason: Zone Blacklist')
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_zone'], e))
                    pass

            # Data dicts
            if os.path.isfile(pfb['pfb_py_data']):
                try:
                    with open(pfb['pfb_py_data']) as csv_file:
                        csv_reader = csv.reader(csv_file, delimiter=',')
                        debug('Blacklist data file found: {}', pfb['pfb_py_data'])
                        for row in csv_reader:
                            if row and (len(row) == 6 or len(row) == 7):
                                if len(row) == 7 and row[6] == '2':
                                    expression = row[1]
                                    entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5]), 'regex': re.compile(row[1], re.IGNORECASE)}
                                    debug('Parsed Blacklist entry (Regex): {}: {}', expression, entry)
                                    regexDataDB[expression] = entry
                                elif len(row) == 7 and row[6] == '1':
                                    domain_name = row[1].lower()
                                    entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5])}
                                    debug('Parsed Blacklist entry (Wildcard): {}: {}', domain_name, entry)
                                    wildcardDataDB[domain_name] = entry
                                else:
                                    domain_name = row[1].lower()
                                    entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5])}
                                    debug('Parsed Blacklist entry (Domain): {}: {}', domain_name, entry)
                                    dataDB[domain_name] = entry
                                    
                            else:
                                sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_data'], row))
                        pfb['python_blacklist'] = True
                        debug('Python Blacklist enabled. Reason: Blacklist data')
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_data'], e))
                    pass

            if pfb['python_blacklist']:

                # Collect user-defined Whitelist, TOP1M and downloaded Whitelists
                if os.path.isfile(pfb['pfb_py_whitelist']):
                    try:
                        with open(pfb['pfb_py_whitelist']) as csv_file:
                            csv_reader = csv.reader(csv_file, delimiter=',')
                            debug('User-defined whitelist data file found: {}', pfb['pfb_py_whitelist'])
                            for row in csv_reader:
                                if row and (len(row) == 2 or len(row) == 7):
                                    if len(row) == 2:
                                        
                                        domain_name = row[0].lower()
                                        entry = {'log': '1', 'feed': 'DNSBL_WHITELIST', 'group': 'USER'}
                                        
                                        if row[1] == '1':
                                            debug('Parsed Whitelist entry (Wildcard): {}: {}', domain_name, entry)
                                            wildcardWhiteDB[domain_name] = entry
                                        else:
                                            debug('Parsed Whitelist entry (Domain): {}: {}', domain_name, entry)
                                            whiteDB[domain_name] = entry

                                    else:

                                        if row[6] == '2':
                                            expression = row[1]
                                            entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5]), 'regex': re.compile(expression, re.IGNORECASE)}
                                            debug('Parsed Whitelist entry (Regex): {}: {}', expression, entry)
                                            regexWhiteDB[expression] = entry
                                        else:
                                            if row[6] == '1':
                                                domain_name = row[1].lower()
                                                entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5])}
                                                debug('Parsed Whitelist entry (Wildcard): {}: {}', domain_name, entry)
                                                wildcardWhiteDB[domain_name] = entry
                                            else:
                                                domain_name = row[1].lower()
                                                entry = {'log': dedup(row[3]), 'feed': dedup(row[4]), 'group': dedup(row[5])}
                                                debug('Parsed Whitelist entry (Domain): {}: {}', domain_name, entry)
                                                whiteDB[domain_name] = entry

                                else:
                                    sys.stderr.write("[pfBlockerNG]: Failed to parse: {}: {}" .format(pfb['pfb_py_whitelist'], row))

                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_whitelist'], e))
                        pass

                # HSTS dicts
                if pfb['python_hsts'] and os.path.isfile(pfb['pfb_py_hsts']):
                    try:
                        with open(pfb['pfb_py_hsts']) as hsts:
                            debug('HSTS data found: {}', pfb['python_hsts'])
                            for line in hsts:
                                value = line.rstrip('\r\n').lower()
                                debug('Parsed HSTS entry: {}', value)
                                hstsDB.add(value)
                    except Exception as e:
                        sys.stderr.write("[pfBlockerNG]: Failed to load: {}: {}" .format(pfb['pfb_py_hsts'], e))
                        pass

            # Validate SQLite3 database connections
            if pfb['mod_sqlite3']:

                debug('Connecting to SQLite databases')
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

                    debug('Enabling DNSBL statistics')
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

                debug('Open MaxMind database for DNS Reply GeoIP logging')
                try:
                    maxmindReader = maxminddb.open_database(pfb['maxminddb'])
                    pfb['python_maxmind'] = True
                except Exception as e:
                    sys.stderr.write("[pfBlockerNG]: Failed to open MaxMind DB: {}" .format(e))
                    pass
    else:
        log_info('[pfBlockerNG]: Failed to load ini configuration. Ini file missing.')

    debug('Initialization complete. Summary of parsed data:')
    debug('------------------------------------------------')
    debug('REGEX count: {}', len(regexDB))
    debug('Zone count: {}', len(zoneDB))
    debug('DNSBL count (Domain): {}', len(dataDB))
    debug('DNSBL count (Wildcard): {}', len(wildcardDataDB))
    debug('DNSBL count (Regex): {}', len(regexDataDB))
    debug('Whitelist count (Domain): {}', len(whiteDB))
    debug('Whitelist count (Wildcard): {}', len(wildcardWhiteDB))
    debug('Whitelist count (Regex): {}', len(regexWhiteDB))
    debug('No-AAAA count (Regex): {}', len(noAAAADB))
    debug('Group Policy count (Regex): {}', len(gpListDB))
    debug('Safe Search count (Regex): {}', len(safeSearchDB))
    debug('HSTS count (Regex): {}', len(hstsDB))
    debug('------------------------------------------------')

    log_info('[pfBlockerNG]: init_standard script loaded')


@traced
def pfb_regex_match(q_name):
    global regexDB

    if q_name:
        for k,r in regexDB.items():
            if r.search(q_name):
                return k
    return False

@traced
def pfb_regex_data_match(q_name):
    global regexDataDB

    if q_name:
        for k,v in regexDataDB.items():
            if v['regex'].search(q_name):
                return k
    return False

@traced
def pfb_regex_whitelist_match(q_name):
    global regexWhiteDB

    if q_name:
        for k,v in regexWhiteDB.items():
            if v['regex'].search(q_name):
                return k
    return False

@traced
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

@traced
def get_q_name_qinfo(qinfo):
    q_name = ''
    try:
        if qinfo and qinfo.qname_str and qinfo.qname_str.strip():
            q_name = qinfo.qname_str.rstrip('.')
    except Exception as e:
        sys.stderr.write("[pfBlockerNG]: Failed get_q_name_qinfo: {}" .format(e))
        pass
    return is_unknown(q_name)

@traced
def get_q_ip(qstate):
    q_ip = ''

    try:
        if qstate:
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

@traced
def get_q_ip_comm(kwargs):
    q_ip = ''

    try:
        if kwargs:
            q_ip = kwargs.get('pfb_addr')
            if not q_ip:
                repinfo = kwargs.get('repinfo')
                if repinfo:
                     q_ip = repinfo.addr
    except Exception as e:
        for a in e:
            sys.stderr.write("[pfBlockerNG]: Failed get_q_ip_comm: {}" .format(a))
        pass
    return is_unknown(q_ip)

@traced
def get_q_type(qstate, qinfo):
    q_type = ''
    if qstate and qstate.qinfo.qtype_str:
        q_type = qstate.qinfo.qtype_str
    elif qinfo and qinfo.qtype_str:
        q_type = qinfo.qtype_str
    return is_unknown(q_type)

@traced
def get_o_type(qstate, rep):
    o_type = ''
    if qstate:
        if qstate.return_msg and qstate.return_msg.rep and qstate.return_msg.rep.rrsets[0] and qstate.return_msg.rep.rrsets[0].rk:
            o_type = qstate.return_msg.rep.rrsets[0].rk.type_str
        elif qstate.qinfo.qtype_str:
            o_type = qstate.qinfo.qtype_str
        elif rep and rep.rrsets[0] and rep.rrsets[0].rk:
             o_type = rep.rrsets[0].rk.type_str
    return is_unknown(o_type)

@traced
def get_rep_ttl(rep):
    ttl = ''
    if rep and rep.ttl:
        ttl = rep.ttl
    return str(is_unknown(ttl)).replace('Unknown', 'Unk')

@traced
def get_tld(qstate):
    tld = ''
    if qstate and qstate.qinfo and len(qstate.qinfo.qname_list) > 1:
        tld = qstate.qinfo.qname_list[-2]
    return tld

@traced
def convert_ipv4(x):
    global pfb

    ipv4 = ''
    if x:
        if pfb['py_v3']:
            ipv4 = "{}.{}.{}.{}" .format(x[2], x[3], x[4], x[5])
        else:
            ipv4 = "{}.{}.{}.{}" .format(ord(x[2]), ord(x[3]), ord(x[4]), ord(x[5]))
    return is_unknown(ipv4)

@traced
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

@traced
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

@traced
def is_unknown(x):
    try:
        if not x or x is None:
            return 'Unknown'
    except Exception as e:
        for a in e:
            sys.stderr.write("[pfBlockerNG]: Failed is_unknown: {}" .format(a))
        pass
    return x

@traced
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

@traced
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

def _debug(format_str, *args, stack_height=1):
    global pfb
    if pfb.get('python_debug') and isinstance(format_str, str):
        with open('/var/log/pfblockerng/py_debug.log', 'a') as append_log:
            append_log.write(datetime.now().strftime("%b %-d %H:%M:%S"))
            append_log.write(' | ')
            stack_element = inspect.stack()[stack_height]
            append_log.write(stack_element[3])  # caller function
            append_log.write(':')
            append_log.write(str(stack_element[2]))  # call site line number
            append_log.write(' | DEBUG: ')
            if args:
                append_log.write(format_str.format(*args))
            else:
                append_log.write(format_str)
            append_log.write('\n')

def debug(format_str, *args, stack_height=1):
    global pfb

    # validate before to avoid additional costs for non-debug calls
    if pfb.get('python_debug') and isinstance(format_str, str):
        for i in range(1,5):
            try:
                _debug(format_str, *args, stack_height=stack_height+1)
            except Exception as e:
                if i == 4:
                    sys.stderr.write("[pfBlockerNG]: log_entry: {}: {}" .format(i, e))
                time.sleep(0.25)
                pass
                continue
            break

@traced
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
    if r_addr == 'NXDOMAIN' and q_type == 'AAAA' and q_name in noAAAADB:
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
    if m_type == 'cache':
        if ttl.isdigit() and len(ttl) == 10:
            ttl = int(ttl) - int(time.time())
        else:
            ttl = ''

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
@traced
def python_control_duration(duration):

    try:
        if duration.isnumeric() and 0 < duration <= 3600:
            duration = int(duration)
            return duration
        else:
            return False
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_duration: {}" .format(e))
        pass
    return False

# Is thread still active
@traced
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
@traced
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
@traced
def python_control_sleep(duration):
    global pfb

    try:
        time.sleep(duration)
        pfb['python_blacklist'] = True;
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_sleep: {}" .format(e))
        pass
    return True


# Python_control Add Bypass IP for specified duration
@traced
def python_control_addbypass(duration, b_ip):
    global pfb, gpListDB

    try:
        time.sleep(duration)
        if b_ip in gpListDB:
            gpListDB.remove(b_ip)
            return True
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] python_control_addbypass: {}" .format(e))
        pass
    return False

@traced
def inplace_cb_reply(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('reply-x', qinfo, qstate, rep, kwargs)
    return True

@traced
def inplace_cb_reply_cache(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('cache', qinfo, qstate, rep, kwargs)
    return True

@traced
def inplace_cb_reply_local(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('local', qinfo, qstate, rep, kwargs)
    return True

@traced
def inplace_cb_reply_servfail(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    get_details_reply('servfail', qinfo, qstate, rep, kwargs)
    return True

@traced
def deinit(id):
    global pfb, maxmindReader

    if pfb['python_maxmind']:
        maxmindReader.close()

    log_info('[pfBlockerNG]: pfb_unbound.py script exiting')
    return True

@traced
def inform_super(id, qstate, superqstate, qdata):
    return True

@traced
def operate(id, event, qstate, qdata):
    global pfb, threads, dataDB, zoneDB, wildcardDataDB, regexDataDB, hstsDB, whiteDB, wildcardWhiteDB, regexWhiteDB, excludeDB, excludeAAAADB, excludeSS, dnsblDB, noAAAADB, gpListDB, safeSearchDB, feedGroupDB

    qstate_valid = False
    try:
        if qstate and qstate.qinfo.qtype:
            qstate_valid = True
            q_type = qstate.qinfo.qtype
            q_type_str = qstate.qinfo.qtype_str
            q_name_original = get_q_name_qstate(qstate).lower()
            q_ip = get_q_ip(qstate)
            debug('[{}]: q_type={}, q_ip={}', q_name_original, q_type_str, q_ip)
        else:
            sys.stderr.write("[pfBlockerNG] qstate is not None and qstate.qinfo.qtype is not None")
    except Exception as e:
        sys.stderr.write("[pfBlockerNG] qstate_valid: {}: {}" .format(event, e))
        pass

    if (event == MODULE_EVENT_NEW) or (event == MODULE_EVENT_PASS):

        # no AAAA validation
        if qstate_valid and q_type == RR_TYPE_AAAA and noAAAADB and q_name_original not in excludeAAAADB:

            debug('[{}]: checking no-AAAA DB', q_name_original)

            # Determine full domain match
            isnoAAAA = noAAAADB.get(q_name_original)

            # Wildcard verification of domain
            if not isnoAAAA:
                debug('[{}]: original domain name not found in no-AAAA DB. Trying TLDs...', q_name_original)

                q = q_name_original.split('.', 1)
                q = q[-1]

                # Validate to 2nd level TLD only
                for x in range(q.count('.'), 0, -1):
                    debug('[{}]: checking TLD no-AAAA DB for TLD: {}', q_name_original, q)

                    isnoAAAA = noAAAADB.get(q)

                    # Determine if domain is a wildcard whitelist entry
                    if isnoAAAA:

                        # Add sub-domain to noAAAA DB
                        noAAAADB[q_name_original] = True
                        break
                    else:
                        q = q.split('.', 1)
                        q = q[-1]

            # Create FQDN Reply Message (AAAA -> A)
            if isnoAAAA:
                debug('[{}]: domain found in no-AAAA DB. Creating FQDN Reply Message (AAAA -> A)', q_name_original)
                msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA)
                if msg is None or not msg.set_return_msg(qstate):
                    qstate.ext_state[id] = MODULE_ERROR
                    return True

                qstate.return_rcode = RCODE_NOERROR
                qstate.return_msg.rep.security = 2
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            # Add domain to excludeAAAADB to skip subsequent no AAAA validation 
            else:
                debug('[{}]: domain added to AAAA exclusion DB', q_name_original)
                excludeAAAADB.add(q_name_original)


        # SafeSearch Redirection validation
        if qstate_valid and safeSearchDB:

            # Determine if domain has been previously validated
            if q_name_original not in excludeSS:
                debug('[{}]: checking Safe Search DB', q_name_original)

                isSafeSearch = safeSearchDB.get(q_name_original)

                # Validate 'www.' Domains
                if not isSafeSearch and not q_name_original.startswith('www.'):
                    isSafeSearch = safeSearchDB.get('www.{}'.format(q_name_original))

                # TODO: See CNAME message below
                #if not isSafeSearch and q_name_original != 'safe.duckduckgo.com' and q_name_original.endswith('duckduckgo.com'):
                #    isSafeSearch = safeSearchDB.get('duckduckgo.com') 
                #if not isSafeSearch and q_name_original != 'safesearch.pixabay.com' and q_name_original.endswith('pixabay.com'):
                #    isSafeSearch = safeSearchDB.get('pixabay.com')

                if isSafeSearch:

                    debug('[{}]: domain found in Safe Search DB: {}', q_name_original, safeSearchDB)

                    ss_found = False
                    if isSafeSearch['A'] == 'nxdomain':
                        qstate.return_rcode = RCODE_NXDOMAIN
                        qstate.ext_state[id] = MODULE_FINISHED
                        return True

                    # TODO: Wait for Unbound code changes to allow for this functionality, using local-zone/local-data entries for CNAMES for now
                    elif isSafeSearch['A'] == 'cname':
                        if isSafeSearch['AAAA']:
                            if q_type == RR_TYPE_A:
                                answer = "{} 3600 IN CNAME {}".format(qstate.qinfo.qname_str, isSafeSearch['AAAA'])
                                debug('[{}]: answer: {}', q_name_original, answer)
                                cname_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RD | PKT_RA)
                                cname_msg.answer.append(answer)
                                ss_found = True
                            elif q_type == RR_TYPE_AAAA:
                                answer = "{} 3600 IN CNAME {}".format(qstate.qinfo.qname_str, isSafeSearch['AAAA'])
                                debug('[{}]: answer: {}', q_name_original, answer)
                                cname_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_AAAA, RR_CLASS_IN, PKT_QR | PKT_RD | PKT_RA)
                                cname_msg.answer.append(answer)
                                ss_found = True

                            if ss_found:
                                cname_msg.set_return_msg(qstate)
                                if cname_msg is None or not cname_msg.set_return_msg(qstate):
                                    qstate.ext_state[id] = MODULE_ERROR
                                    return True

                                MODULE_RESTART_NEXT = 3
                                qstate.no_cache_store = 1
                                qstate.ext_state[id] = MODULE_RESTART_NEXT
                                return True
                    else:
                        if (q_type == RR_TYPE_A and isSafeSearch['A']) or (q_type == RR_TYPE_AAAA and not isSafeSearch['AAAA']):
                            answer = "{} 300 IN {} {}".format(qstate.qinfo.qname_str, 'A', isSafeSearch['A'])
                            debug('[{}]: answer: {}', q_name_original, answer)
                            msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA)
                            msg.answer.append(answer)
                            ss_found = True
                        elif q_type == RR_TYPE_AAAA and isSafeSearch['AAAA']:
                            answer = "{} 300 IN {} {}".format(qstate.qinfo.qname_str, 'AAAA', isSafeSearch['AAAA'])
                            debug('[{}]: answer: {}', q_name_original, answer)
                            msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_AAAA, RR_CLASS_IN, PKT_QR | PKT_RA)
                            msg.answer.append(answer)
                            ss_found = True

                    if ss_found:
                        msg.set_return_msg(qstate)
                        if msg is None or not msg.set_return_msg(qstate):
                            qstate.ext_state[id] = MODULE_ERROR
                            return True

                        qstate.return_rcode = RCODE_NOERROR
                        qstate.return_msg.rep.security = 2
                        qstate.ext_state[id] = MODULE_FINISHED
                        return True

            # Add domain to excludeSS to skip subsequent SafeSearch validation
            else:
                debug('[{}]: domain added to Safe Search exclusion DB', q_name_original)
                excludeSS.add(q_name_original)

        # Python_control - Receive TXT commands from pfSense local IP
        if qstate_valid and q_type == RR_TYPE_TXT and q_name_original.startswith('python_control.'):

            control_rcd = False
            if pfb['python_control'] and q_ip == '127.0.0.1':

                debug('[{}]: Python Control', q_name_original)

                control_command = q_name_original.split('.')
                if (len(control_command) >= 2):

                    if control_command[1] == 'disable':
                        control_rcd = True
                        control_msg = 'Python_control: DNSBL disabled'
                        pfb['python_blacklist'] = False

                        # If duration specified, disable DNSBL Blocking for specified time in seconds
                        if pfb['mod_threading'] and len(control_command) == 3 and control_command[2]:

                            # Validate Duration argument
                            duration = python_control_duration(control_command[2])
                            if duration:

                                # Ensure thread is not active
                                if not python_control_thread('sleep'):

                                    # Start Thread
                                    if not python_control_start_thread('sleep', python_control_sleep, duration):
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

                            control_rcd = True
                            if control_command[1] == 'addbypass':
                                control_msg = "Python_control: Add bypass for IP: [ {} ]" .format(b_ip)

                                # If duration specified, disable DNSBL Blocking for specified time in seconds
                                if pfb['mod_threading'] and len(control_command) == 4 and control_command[3]:

                                    # Validate Duration argument
                                    duration = python_control_duration(control_command[3])
                                    if duration:

                                        # Ensure thread is not active
                                        if not python_control_thread('addbypass {}'.format(b_ip)):

                                            # Start Thread
                                            if not python_control_start_thread('addbypass {}'.format(b_ip), python_control_addbypass, duration, b_ip):
                                                control_rcd = False
                                                control_msg = "Python_control: Add bypass for IP: [ {} ] thread failed".format(b_ip)
                                            else:
                                                control_msg = "{} for {} second(s)".format(control_msg, duration)
                                        else:
                                            control_rcd = False
                                            control_msg = "Python_control: Add bypass for IP: [ {} ]: Previous call still in progress".format(b_ip)
                                    else:
                                        control_rcd = False
                                        control_msg = "Python_control: Add bypass for IP: [ {} ]: duration [ {} ] out of range (1-3600sec)".format(b_ip, control_command[3])
                                else:
                                    # Add bypass called without duration
                                    if control_rcd:
                                        gpListDB.add(b_ip)

                            elif control_command[1] == 'removebypass':
                                if b_ip in gpListDB:
                                    control_msg = "Python_control: Remove bypass for IP: [ {} ]".format(b_ip)
                                    gpListDB.remove(b_ip)
                                else:
                                    control_msg = "Python_control: IP not in Group Policy: [ {} ]".format(b_ip)

                if control_rcd:
                    q_reply = 'python_control'
                else:
                    if control_msg == '':
                        control_msg = "Python_control: Command not authorized! [ {} ]".format(q_name_original)
                    q_reply = 'python_control_fail'

                answer = '{}. 0 IN TXT "{}"'.format(q_reply, control_msg)
                debug('[{}]: answer: {}', q_name_original, answer)

                txt_msg = DNSMessage(qstate.qinfo.qname_str, RR_TYPE_TXT, RR_CLASS_IN, PKT_QR | PKT_RA)
                txt_msg.answer.append(answer)

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
        if gpListDB:
            debug('[{}]: checking Group Policy DB', q_name_original)

            q_ip = get_q_ip(qstate)
            if q_ip != 'Unknown' and q_ip in gpListDB:
                debug('[{}]: bypassing DNSBQ due to Group Policy match for IP {}', q_name_original, q_ip)
                bypass_dnsbl = True

        # Create list of Domain/CNAMES to be evaluated
        validate = []

        # Skip 'in-addr.arpa' domains
        if not bypass_dnsbl and not q_name_original.endswith('.in-addr.arpa'):
            validate.append(q_name_original)

            # DNSBL CNAME Validation
            if pfb['python_cname'] and qstate.return_msg:
                debug('[{}]: adding CNAMEs for validation', q_name_original)

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

        debug('[{}]: validating domain names: {}', q_name_original, validate)

        isCNAME = False
        for val_counter, q_name in enumerate(validate, start=1):

            if val_counter > 1:
                isCNAME = True

            # Determine if domain has been previously validated
            if q_name not in excludeDB:

                isFound = False
                log_type = False
                isInWhitelist = False
                isInHsts = False
                b_type = 'Python'
                p_type = 'Python'
                feed = 'Unknown'
                group = 'Unknown'

                debug('[{}]: validating domain name: {}', q_name_original, q_name)

                # Determine if domain was previously DNSBL blocked
                isDomainInDNSBL = dnsblDB.get(q_name)
                if not isDomainInDNSBL:
                    tld = get_tld(qstate)
                    debug('[{}]: validating domain name: {}, TLD: {}', q_name_original, q_name, tld)

                    # Determine if domain is in DNSBL 'data|zone' database
                    if pfb['python_blocking']:

                        # Determine if domain is in DNSBL 'data' database (log to dnsbl.log)
                        if dataDB:
                            # Create list of Domain/CNAMES to be validated against Blacklist
                            blacklist_validate = []
                            blacklist_validate.append(q_name)

                            if isCNAME:
                                blacklist_validate.append(q_name_original)

                            for w_q_name in blacklist_validate:
                                debug('[{}]: checking Blacklist DB (Domain) for: {}', q_name_original, w_q_name)

                                # Determine full domain match
                                isDomainInData = dataDB.get(w_q_name)
                                if isDomainInData:
                                    isFound = True
                                elif w_q_name.startswith('www.'):
                                   isDomainInData = dataDB.get(w_q_name[4:])
                                   if isDomainInData:
                                        isFound = True

                                # Determine TLD segment matches
                                if not isFound and wildcardDataDB:
                                    debug('[{}]: checking Blacklist DB (Wildcard) for: {}', q_name_original, w_q_name)

                                    isDomainInData = wildcardDataDB.get(w_q_name)
                                    if isDomainInData:
                                        isFound = True
                                    elif w_q_name.startswith('www.'):
                                        isDomainInData = wildcardDataDB.get(w_q_name[4:])
                                        if isDomainInData:
                                            isFound = True

                                    if not isFound:
                                        q = w_q_name.split('.', 1)
                                        q = q[-1]
                                        for x in range(q.count('.') +1, 0, -1):
                                            if x >= pfb['python_tld_seg']:
                                                isDomainInData = wildcardDataDB.get(q)

                                                # Determine if domain is a wildcard blacklist entry
                                                if isDomainInData:
                                                    isFound = True
                                                    break
                                                else:
                                                    q = q.split('.', 1)
                                                    q = q[-1]

                                # Set log data, if we got a match
                                if isFound:
                                    debug('[{}]: found Blacklist entry for: {}: {}', q_name_original, q_name, isDomainInData)

                                    (log_type, feed, group) = (isDomainInData['log'], isDomainInData['feed'], isDomainInData['group'])
                                    b_type = 'DNSBL'
                                    b_eval = q_name

                        # Determine if domain is in DNSBL 'zone' database (log to dnsbl.log)
                        if not isFound and zoneDB:
                            debug('[{}]: checking Zone DB for: {}', q_name_original, q_name)

                            q = q_name
                            for x in range(q.count('.') +1, 0, -1):
                                isDomainInZone = zoneDB.get(q)
                                if isDomainInZone:
                                    isFound = True
                                    debug('[{}]: found Zone entry for: {}: {}', q_name_original, q_name, isDomainInZone)
                                    (log_type, feed, group) = (isDomainInZone['log'], isDomainInZone['feed'], isDomainInZone['group'])
                                    b_type = 'TLD'
                                    b_eval = q

                                    break
                                else:
                                    q = q.split('.', 1)
                                    q = q[-1]
                        
                        # Block via Domain Name Regex
                        if not isFound and regexDataDB:
                            debug('[{}]: checking Blacklist DB (Regex) for: {}', q_name_original, q_name)
                            
                            isDomainInRegexData = pfb_regex_data_match(q_name)
                            if isDomainInRegexData:
                                isDomainInData = regexDataDB[isDomainInRegexData]
                                debug('[{}]: found Blacklist (Regex) entry for: {}: {}', q_name_original, q_name, isDomainInData)

                                isFound = True
                                (log_type, feed, group) = (isDomainInData['log'], isDomainInData['feed'], isDomainInData['group'])
                                b_type = 'DNSBL'
                                b_eval = q_name


                    # Validate other python methods, if not blocked via DNSBL zone/data
                    if not isFound:

                        debug('[{}]: domain not blacklisted: {}', q_name_original, q_name)

                        # Allow only approved TLDs
                        if tld and pfb['python_tld'] and tld not in pfb['python_tlds'] and q_name not in (pfb['dnsbl_ipv4'], '::{}'.format(pfb['dnsbl_ipv4'])):
                            isFound = True
                            debug('[{}]: domain TLD not found in TLD Allow list: {}: {}', q_name_original, q_name, tld)
                            feed = 'TLD_Allow'
                            group = 'DNSBL_TLD_Allow'

                        # Block IDN or 'xn--' Domains
                        if not isFound and pfb['python_idn'] and (q_name.startswith('xn--') or '.xn--' in q_name):
                            isFound = True
                            debug("[{}]: blocked IDN or 'xn--': {}: {}", q_name_original, q_name, tld)
                            feed = 'IDN'
                            group = 'DNSBL_IDN'

                        # Block via Regex
                        if not isFound and regexDB:
                            debug('[{}]: checking REGEX DB for: {}', q_name_original, q_name)
                            isRegexMatch = pfb_regex_match(q_name)
                            if isRegexMatch:
                                debug('[{}]: found REGEX entry for: {}: {}', q_name_original, q_name, isRegexMatch)
                                isFound = True
                                feed = isRegexMatch
                                group = 'DNSBL_Regex'

                        if isFound:
                            b_eval = q_name
                            log_type = '1'

                    # Validate domain in DNSBL Whitelist
                    if isFound and whiteDB:
                        debug('[{}]: domain blacklisted: {}', q_name_original, q_name)
                        debug('[{}]: checking whitelist: {}', q_name_original, q_name)

                        # Create list of Domain/CNAMES to be validated against Whitelist
                        whitelist_validate = []
                        whitelist_validate.append(q_name)

                        if isCNAME:
                            whitelist_validate.append(q_name_original)

                        for w_q_name in whitelist_validate:

                            debug('[{}]: checking Whitelist DB (Domain) for: {}', q_name_original, w_q_name)

                            # Determine full domain match
                            isDomainInWhitelist = whiteDB.get(w_q_name)
                            if isDomainInWhitelist is not None:
                                isInWhitelist = True
                            elif w_q_name.startswith('www.'):
                               isDomainInWhitelist = whiteDB.get(w_q_name[4:])
                               if isDomainInWhitelist is not None:
                                    isInWhitelist = True

                            # Determine TLD segment matches
                            if not isInWhitelist and wildcardWhiteDB:

                                debug('[{}]: checking Whitelist DB (Wildcard) for: {}', q_name_original, w_q_name)

                                # Determine full domain match
                                isDomainInWhitelist = wildcardWhiteDB.get(w_q_name)
                                if isDomainInWhitelist is not None:
                                    isInWhitelist = True
                                elif w_q_name.startswith('www.'):
                                   isDomainInWhitelist = wildcardWhiteDB.get(w_q_name[4:])
                                   if isDomainInWhitelist is not None:
                                        isInWhitelist = True

                                if not isInWhitelist:
                                    q = w_q_name.split('.', 1)
                                    q = q[-1]
                                    for x in range(q.count('.') +1, 0, -1):
                                        if x >= pfb['python_tld_seg']:
                                            isDomainInWhitelist = wildcardWhiteDB.get(q)

                                            # Determine if domain is a wildcard whitelist entry
                                            if isDomainInWhitelist is not None:
                                                isInWhitelist = True
                                                break
                                            else:
                                                q = q.split('.', 1)
                                                q = q[-1]

                            # Set log data, if we got a match
                            if isInWhitelist:
                                debug('[{}]: found Whitelist entry for: {}: {}', q_name_original, q_name, isDomainInWhitelist)
                                (log_type, feed, group) = (isDomainInWhitelist['log'], isDomainInWhitelist['feed'], isDomainInWhitelist['group'])
                                b_type = 'DNSBL'
                                b_eval = q_name

                    # Validate domain in DNSBL Domain Name Regex
                    if isFound and not isInWhitelist and regexDataDB:
                        debug('[{}]: checking Whitelist DB (Regex) for: {}', q_name_original, q_name)
                        
                        isDomainInWhitelistRegex = pfb_regex_whitelist_match(q_name)
                        if isDomainInWhitelistRegex:
                            isDomainInWhitelist = regexWhiteDB[isDomainInWhitelistRegex]
                            debug('[{}]: found Whitelist (Regex) entry for: {}: {}', q_name_original, q_name, isDomainInWhitelist)

                            isInWhitelist = True
                            (log_type, feed, group) = (isDomainInWhitelist['log'], isDomainInWhitelist['feed'], isDomainInWhitelist['group'])
                            b_type = 'DNSBL'
                            b_eval = q_name

                    # Add domain to excludeDB to skip subsequent blacklist validation
                    if not isFound or isInWhitelist:
                        debug('[{}]: add to whitelist cache: {}', q_name_original, q_name)
                        excludeDB.add(q_name)
                        if isCNAME and q_name_original not in excludeDB:
                            debug('[{}]: add to whitelist cache: {}', q_name_original, q_name)
                            excludeDB.add(q_name_original)

                    # Domain to be blocked and is not whitelisted
                    if isFound and not isInWhitelist:

                        # Determine if domain is in HSTS database (Null blocking)
                        if hstsDB:
                            debug('[{}]: checking HSTS for: {}', q_name_original, q_name)

                            # Determine if TLD is in HSTS database
                            if tld in pfb['hsts_tlds']:
                                debug('[{}]: found TLD in HSTS: {}: {}', q_name_original, q_name, tld)
                                isInHsts = True
                                p_type = 'HSTS_TLD'
                            else:
                                q = q_name
                                for x in range(q.count('.') +1, 0, -2):
                                    if q in hstsDB:
                                        debug('[{}]: found HSTS blacklist entry: {}: {}', q_name_original, q_name, q)
                                        isInHsts = True
                                        if q_type_str in pfb['rr_types2']:
                                            p_type = 'HSTS_' + q_type_str
                                        else:
                                            p_type = 'HSTS'
                                        break
                                    else:
                                        q = q.split('.', 1)
                                        q = q[-1]


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
                        entry = {'qname': q_name, 'b_type': b_type, 'p_type': p_type, 'b_ip': b_ip, 'log': log_type, 'feed': feed, 'group': group, 'b_eval': b_eval }
                        debug('[{}]: adding entry to DNSBL cache: {}', q_name_original, entry)
                        dnsblDB[q_name] = entry
                        # Skip subsequent DNSBL validation for original domain (CNAME validation), and add domain to dict for get_details_dnsbl function
                        if isCNAME and q_name_original not in dnsblDB:
                            entry = {'qname': q_name_original, 'b_type': b_type, 'p_type': p_type, 'b_ip': b_ip, 'log': log_type, 'feed': feed, 'group': group, 'b_eval': b_eval }
                            debug('[{}]: adding entry to DNSBL cache: {}', q_name_original, entry)
                            dnsblDB[q_name_original] = entry

                        # Add domain data to DNSBL cache for Reports tab
                        write_sqlite(3, '', [b_type, q_name, group, b_eval, feed])

                # Use previously blocked domain details
                else:
                    debug('[{}]: found domain name in DNSBL cache: {}: {}', q_name_original, q_name, isDomainInDNSBL)
                    b_ip = isDomainInDNSBL['b_ip']
                    b_type = isDomainInDNSBL['b_type']
                    isFound = True

                if isFound and not isInWhitelist:

                    # Default RR_TYPE ANY -> A
                    if q_type == RR_TYPE_ANY:
                        q_type = RR_TYPE_A
                        q_type_str = 'A'

                    debug('[{}]: blocked: {}, b_ip={}, q_type={}', q_name_original, q_name, b_ip, q_type_str)

                    # Create FQDN Reply Message
                    answer = "{}. 60 IN {} {}".format(q_name, q_type_str, b_ip)
                    debug('[{}]: answer: {}', q_name_original, answer)
                    msg = DNSMessage(qstate.qinfo.qname_str, q_type, RR_CLASS_IN, PKT_QR | PKT_RA)
                    msg.answer.append(answer)

                    msg.set_return_msg(qstate)
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

            else:
                debug('[{}]: domain found in exclusion cache: {}', q_name_original, q_name)

    debug('[{}]: passed through', q_name)

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
