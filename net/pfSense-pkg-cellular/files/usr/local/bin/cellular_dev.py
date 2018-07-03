#!/usr/local/bin/python2.7
#
# /usr/local/bin/cellular_dev.py
#
# part of pfSense (https://www.pfsense.org)
# Copyright (C) 2017 Voleatech GmbH
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

"""
Helper to mount cellular modem at fixed dev point
"""

import sys
import os
import commands

import argparse

data_file = "cuaZ99.0"
data_path = "/dev/" + data_file
mgm_file = "cuaZ99.1"
mgmt_path = "/dev/" + mgm_file

def remove_link():

    if os.path.lexists(data_path):
        os.remove(data_path)

    if os.path.lexists(mgmt_path):
        os.remove(mgmt_path)

    #Remove locks
    data_lock = "/var/spool/lock/LCK.." + data_file
    if os.path.exists(data_lock):
        os.remove(data_lock)
    
    mgmt_lock = "/var/spool/lock/LCK.." + mgm_file
    if os.path.exists(mgmt_lock):
        os.remove(mgmt_lock)

# Argument parser
parser = argparse.ArgumentParser(description = "Interface for Cellular Dev Point")

parser.add_argument("-a", "--add",
        action="store_true",
        dest="add",
        help="add modem")
parser.add_argument("-r", "--remove",
        action="store_true",
        dest="remove",
        help="remove modem")
parser.add_argument("-d", "--device",
        dest="device",
        help="device name")


# parse commandline args
args = parser.parse_args()
args.silent = False

if args.add:
    if args.device:

        #Make sure the links are gone
        remove_link()

        #Before we start kill 3gstat as it blocks us
        os.system("/bin/pkill -fx '.*[3]gstats.*'")

        dev_pre = args.device[:-1]
        dev_post = args.device[-1]
        dev_ug = commands.getoutput("/sbin/sysctl -n dev." + dev_pre + "." + dev_post + ".ttyname")
        path_ug = "/dev/cua" + dev_ug

        data_ug = path_ug + ".0"
        if os.path.exists(data_ug):
            os.symlink(data_ug, data_path)

        mgmt_ug = path_ug + ".2"
        if os.path.exists(mgmt_ug):
            os.symlink(mgmt_ug, mgmt_path)

elif args.remove:

    remove_link()
