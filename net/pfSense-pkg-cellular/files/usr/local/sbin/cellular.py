#!/usr/local/bin/python2.7
#
# cellular.py
#
# part of pfSense (https://www.pfsense.org)
# Copyright (C) 2016 Voleatech GmbH, Fabian Schweinfurth
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
#

from __future__ import print_function
"""
CLI for lte cards in pfsense systems.

2016 - 2017 by Voleatech GmbH (tech@voleatech.de)
"""

import sys
import os
import time

import argparse
import ConfigParser
import serial

__version__ = "1.1.6"

huawei = (lambda a: a.startswith("Huawei"))

class CellularInterface:
    """
    Cellular Serial interface for LTE cards by Voleatech.
    """

    DEBUG = False

    def __init__(self):
        """initialize the interface.

        search for config files "/usr/local/etc/defaults/cellular.defaults.conf" or "/usr/local/etc/cellular.conf"
        """
        self.cmd_string = ""

        self.path = "/usr/local"
        self.confdir = self.path + "/etc/cellular"
        self.conf = self.confdir + "/cellular.conf"
        self.defconf = self.confdir + "/cellular.defaults.conf"

        self.config = ConfigParser.ConfigParser()

        # init default Config if not present
        self.init_config(self.defconf)

        self.config.readfp(open(self.defconf))
        # read custom settings from webinterface
        self.config.read([self.conf])

        self.device = self.config.get("Interface", "port")
        # baudrate one of 50, 75, 110, 134, 150, 200, 300, 600, 1200,
           # 1800, 2400, 4800, 9600, 19200, 38400, 57600, 115200
        self.baudrate = int(self.config.get("Interface", "baudrate"))

        self.timeout = float(self.config.get("Interface", "timeout"))

        self.ser = serial.Serial()
        self.ser.baudrate = self.baudrate
        self.ser.port = "/dev/{}".format(self.device)
        self.ser.timeout = self.timeout

        self.init_hardware()

        #if not huawei(self.module):
        #    print("DEVICE NOT SUPPORTED", file=sys.stdout)
        #    exit(1)

    def set_device(self, d):
        self.device = d
        self.ser.port = "/dev/{}".format(d)

    def set_baudrate(self, b):
        self.baudrate = b

    def set_timeout(self, t):
        if (t >= 0):
            self.timeout = t

    def set_config(self, args):
        """
        write config to .cellular.conf
        """
        tmp = ConfigParser.ConfigParser()

        if (os.path.isfile(self.conf)):
            tmp.readfp(open(self.conf))

        # empty or deleted field must not be wrote to config
        if not args.value and tmp.has_option(args.section, args.key):
            tmp.remove_option(args.section, args.key)
        elif args.value:
            if not tmp.has_section(args.section):
                tmp.add_section(args.section)
            tmp.set(args.section, args.key, args.value)

        with open(self.conf, "wb") as f:
            tmp.write(f)

        print("OK", file=sys.stdout)

    def init_hardware(self):
        """
        write hardware information to .cellular.conf.

        sets self.module and self.manufacturer
        """

        tmp = ConfigParser.ConfigParser()

        if (os.path.isfile(self.conf)):
            tmp.readfp(open(self.conf))
            found = False

            # return module when in config
            if tmp.has_section("Hardware") and tmp.has_option("Hardware", "module"):
                self.module = tmp.get("Hardware", "module")
                found = True

            # return manufacturer when in config
            if tmp.has_section("Hardware") and tmp.has_option("Hardware", "manufacturer"):
                self.manufacturer = tmp.get("Hardware", "manufacturer")
                found = True

            if found:
                return

        tmp.add_section("Hardware")
        args = (lambda: 0)
        args.verbose = False # little cheaty...

        module = self.get_model(args, silent=True)[1]
        if module == "ERROR":
            print("ERROR", file=sys.stdout)
            exit(1)

        self.module = module
        tmp.set("Hardware", "module", module)

        manufacturer = self.get_manufacturer(args, silent=True)[1]
        if manufacturer == "ERROR":
            print("ERROR", file=sys.stdout)
            exit(1)

        self.manufacturer = manufacturer
        tmp.set("Hardware", "manufacturer", manufacturer)

        with open(self.conf, "wb") as f:
            tmp.write(f)

    def init_config(self, config_file):
        """
        initialize config file if not present or from old version.
        """
        
        #make sure confdir exists
        if (not os.path.isdir(self.confdir)):
            os.makedirs(self.confdir)

        # check if config file is from an old version.
        if (os.path.isfile(config_file)):
            tmp = ConfigParser.ConfigParser()
            tmp.readfp(open(config_file))

            config_version = tmp.get("Software", "version")

            if config_version.split("_")[0] != __version__.split("_")[0]:
                # version mismatch: remove /etc/defaults/cellular.defaults.conf
                print("Cleaning up old config file", file=sys.stdout)
                os.remove(config_file)

        if (not os.path.isfile(config_file)):
            # generate new /etc/defaults/cellular.defaults.conf
            pre_text = """\
# Default configuration 
# DO NOT ALTER THESE SETTINGS.
# If you want to introduce custom settings, you may do so in /etc/cellular.conf"""

            self.config.add_section("Software")
            self.config.set("Software", "version", __version__)

            self.config.add_section("Interface")
            self.config.set("Interface", "port", "cuaZ99.1")
            self.config.set("Interface", "baudrate", "9600")
            # self.config.set("Interface", "bytesize", "serial.FIVEBITS") # check for that value..
            # self.config.set("Interface", "parity", "serial.PARITY_NONE") # dito
            # self.config.set("Interface", "stopbits", "serial.STOPBITS_ONE")
            self.config.set("Interface", "timeout", "0.5")

            with open(config_file, "wb") as f:
                f.write(pre_text)
                f.write("\n")
                self.config.write(f)

    def at_cmd(self, cmd, args, short = False):
        """send AT command to serial port and

        Arguments:
        cmd - AT command without <AT> at the beginning
        to_stdout - should answer be printed to stdout? (default True)

        return (return code, answer).
        """

        import re

        to_stdout = args.verbose

        # open the serial port
        try:
            self.ser.open()
        except OSError as err:
            print("ERROR: ", err)
            return ("-1", "-1")

        # send AT command
        # We will try 3 times since the modem is sometimes busy
        for x in range(0, 2):

            ret = self.ser.write("AT{}\r".format(cmd))
            answ = self.ser.read(1024)

            # better save than sorry
            if "OK" in answ:
                break

            time.sleep(0.2)

        # better save than sorry
        if ("ERROR" in answ) or (not "OK" in answ):
            if to_stdout:
                print("ERROR", file=sys.stdout)

            return ("-1", "-1")

        if to_stdout:
            print(answ, file=sys.stdout)

        if short:
            m = re.search("{}: (\S+)".format(re.escape(cmd.split("=")[0])), answ)
            if m:
                answ = m.group(1)

        self.ser.close()
        return (ret, answ)

    def custom_command(self, args):
        """
        send a custom command to the module
        """
        cmd = args.cmd

        if ("AT" in args.cmd and args.cmd.index("AT") == 0):
            cmd = args.cmd[2:]

        return self.at_cmd(cmd, args)

    def signal_strength(self, args):
        """
        receive signal strength converted to 1-4

        returns ERROR on Error
        """

        args.silent = True
        widget = self.widget(args)

        if (len(widget[0])):
            print(widget[0][0], file=sys.stdout)
            return widget

        print("ERROR", file=sys.stdout)

        return widget

    def _parse_signal_strength(self, strength):
        """
        parse and convert signal strength to 1-4
        """

        steps = [0, 1, 9, 14, 19, 31]

        rssi = int(strength.split(",")[0])

        # convert rssi to number (1(marginal) - 4(excellent))
        ret = map(lambda a: rssi <= a, steps).index(True)

        if (ret == 0):
            return "ERROR"
        else:
            return str(ret -1)

    def _at_information(self, args):
        """
        receive module information
        """

        return self.at_cmd("I", args)

    def _at_infoex(self, args):
        """receive system information"""

        return self.at_cmd("^SYSINFOEX", args, short = True)

    def infoex(self, args):
        """
        system information
        ^SYSINFOEX: 2,3,0,1,,6,"LTE",101,"LTE"
        ^SYSINFOEX: <srv_status>,<srv_domain>,<roam_status>,<sim_state>,<lock_state>,<sysmode>,<sysmode_name>,<submode>,<submode_name>
        """

        if not huawei(self.manufacturer):
            print("ERROR", file=sys.stdout)
            return "ERROR"

        info = self._at_infoex(args)
        print(info[1], file=sys.stdout)

        return info

    def _get_submode_name(self, infoex):

        if (infoex == "ERROR"):
            return "ERROR"
        else:
            return infoex.split(",")[6].strip('"')

    def get_model(self, args, silent=False):
        """
        get model of module.
        """
        import re
        # TODO: +GMM
        info = self._at_information(args)
        m = re.search("Model:(?:\W)*(.*)", info[1])

        if m:
            if not silent:
                print(m.group(1), file=sys.stdout)
            return (info[0], m.group(1))
        else:
            if not silent:
                print("ERROR", file=sys.stdout)
            return ("-1", "ERROR")

        return info

    def get_manufacturer(self, args, silent=False):
        """
        get manufacturere of module.
        """
        import re
        # TODO: +GMI 
        info = self._at_information(args)
        m = re.search("Manufacturer:(?:\W)*(.*)", info[1])

        if m:
            if not silent:
                print(m.group(1), file=sys.stdout)
            return (info[0], m.group(1))
        else:
            if not silent:
                print("ERROR", file=sys.stdout)
            return ("-1", "ERROR")

        return info

    def information(self, args):
        ret = self._at_information(args)

        return ret

    def get_carrier(self, args):
        """
        get carrier and convert to string.
        """

        args.silent = True
        widget = self.widget(args)

        if (len(widget[0])):
            print(widget[0][1], file=sys.stdout)
            return widget

        print("ERROR", file=sys.stdout)

        return widget

    def _parse_carrier(self, ret):
        '''
        Parse carrier string
        '''
        return ret.split(",")[2].strip('"')

    def widget(self, args):
        """
        get widget information (signal strength, carrier, mode)
        """
        import re

        cmds = (("+CSQ", self._parse_signal_strength),
            ("+COPS?", self._parse_carrier),
            ("^SYSINFOEX", self._get_submode_name))

        ret = self.at_cmd("; ".join([cmd[0] for cmd in cmds]), args)

        out = []

        for cmd, f in cmds:
            m = re.search("{}: (\S+)".format(re.escape(cmd.split("=")[0].split("?")[0])), ret[1])
            if m:
                out.append(f(m.group(1)))
            else:
                out.append("ERROR")

        if (not args.silent):
            print(",".join(out), file=sys.stdout)

        return (out, ret[1])


##############
#### now for the CLI part
##############
if __name__ == "__main__":
    interface = CellularInterface()

    # actions to take when calling
    actions = {
        "custom": {
            "action": interface.custom_command,
            "help": "Send custom command.",
            "kwargs": {
            }
        },
        "signal": {
            "action": interface.signal_strength,
            "help": "Get signal strength.",
            "kwargs": {
            }
        },
        "carrier": {
            "action": interface.get_carrier,
            "help": "Get current carrier.",
            "kwargs": {
            }
        },
        "information": {
            "action": interface.information,
            "help": "Get information of the module.",
            "kwargs": {
            }
        },
        "infoex": {
            "action": interface.infoex,
            "help": "Get system information.",
            "kwargs": {
            }
        },
        "model": {
            "action": interface.get_model,
            "help": "Get model of the module.",
            "kwargs": {
            }
        },
        "manufacturer": {
            "action": interface.get_manufacturer,
            "help": "Get manufacturer of the module.",
            "kwargs": {
            }
        },
        "widget": {
            "action": interface.widget,
            "help": "Get all information for widget init.",
            "kwargs": {
            }
        },
        "setcfg": {
            "action": interface.set_config,
            "help": "Set config of module.",
            "kwargs": {
            }
        }
    }

    # Argument parser
    parser = argparse.ArgumentParser(description = "Interface for Cellular Modems")

    parser.add_argument("-v", "--verbose",
            action="store_true",
            dest="verbose",
            help="verbose output")

    parser.add_argument("-d", "--device",
            dest="device",
            help="set device manually")
    parser.add_argument("-b", "--baudrate",
            dest="baudrate",
            help="set baudrate manually")
    parser.add_argument("-t", "--timeout",
            dest="timeout",
            help="set timeout manually")

    # Subparser for subcommands (e.g <file> signal, <file> carrier, ..)
    subparsers = parser.add_subparsers(dest="action", help="action")

    my_subparsers = {}
    ## construct subparsers per command in "actions"
    for action, ac_args in actions.iteritems():
        tmp_subparser = subparsers.add_parser(action, help=ac_args["help"])

        tmp_subparser.add_argument("-v", "--verbose",
                   action="store_true",
                   dest="verbose",
                   help="verbose output")

        my_subparsers[action] = tmp_subparser

    my_subparsers["custom"].add_argument("cmd", help="custom command (without AT)")

    sections = ["Interface"]
    keys = ["port", "baudrate", "timeout", "initstring"]

    my_subparsers["setcfg"].add_argument("section", choices=sections, help="section to write to");
    my_subparsers["setcfg"].add_argument("key", choices=keys, help="the key");
    my_subparsers["setcfg"].add_argument("value", nargs="?", default="", help="the value");

    # parse commandline args
    args = parser.parse_args()
    args.silent = False
    if args.device:
        interface.set_device(args.device)
    if args.baudrate:
        interface.set_baudrate(args.baudrate)
    if args.timeout:
        interface.set_timeout(args.timeout)

    #Before we start kill 3gstat as it blocks us
    os.system("/bin/pkill -fx '.*[3]gstats.*'")

    ## now call the function
    actions[args.action]["action"](args, **actions[args.action]["kwargs"])
