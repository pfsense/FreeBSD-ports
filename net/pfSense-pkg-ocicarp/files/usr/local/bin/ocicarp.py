#!/bin/python3
#
# coding: utf-8
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2023, Oracle and/or its affiliates.
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

import oci

import argparse
import json
import logging
import logging.handlers
from http import HTTPStatus
from platform import system
from typing import Any, TextIO


"""
Return codes:
  0 success
  1 set up failure
  2 JSON file read failure
  10 API call issue(s)
"""
RET_SUCCESS = 0
RET_SETUP_FAIL = 1
RET_FILE_FAIL = 2
RET_API_FAIL = 10


def _init_logging() -> None:
    # Grab logger, set level and create formatter.
    logger = logging.getLogger()
    logger.setLevel(logging.INFO)
    formatter = logging.Formatter('ocicarp.py %(message)s')

    # Add a console handler and set its formatter.
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)

    os_system: str = system()
    log_address: str = ''
    if os_system == 'Linux':
        log_address = '/dev/log'
    elif os_system =='FreeBSD':
        log_address = '/var/run/log'

    # Optionally add a syslog handler (FreeBSD / Linux).
    if log_address:
        syslog_handler = logging.handlers.SysLogHandler(address=log_address)
        syslog_handler.setFormatter(formatter)
        logger.addHandler(syslog_handler)


def reassign_v4ip(vn_client: oci.core.VirtualNetworkClient, vnic: str, ocid: str) -> tuple[int, str]:
    """
    Invokes OCI API to relocate an IP v4 secondary IP address.

    Parameters:
        vn_client (VirtualNetworkClient): The virtual network client object.
        vnic (str): The OCID of the vNIC to assign the IP to.
        vocid (str): The OCID of the IP to assign to the vNIC.

    Returns:
        int: The HTTP return code of the call.
        str: The result of the call.
    """

    ret_data: str = ''
    ret_code: int = -1
    try:
        response: oci.core.modles.Response = vn_client.update_private_ip(ocid,
            oci.core.models.UpdatePrivateIpDetails(
                vnic_id=vnic
            )
        )
        ret_code = response.status
        ret_data = response.data

    except oci.exceptions.ServiceError as e:
        ret_code = -1
        ret_data = str(e)

    return ret_code, ret_data


def reassign_v6ip(vn_client: oci.core.VirtualNetworkClient, vnic: str, ocid: str) -> tuple[int, str]:
    """
    Invokes OCI API to relocate an IP v6 secondary IP address.

    Parameters:
        vn_client (VirtualNetworkClient): The virtual network client object.
        vnic (str): The OCID of the vNIC to assign the IP to.
        vocid (str): The OCID of the IP to assign to the vNIC.

    Returns:
        int: The HTTP return code of the call.
        str: The result of the call.
    """

    ret_data: str = ''
    ret_code: int = -1
    try:
        response: oci.core.modles.Response = vn_client.update_ipv6(ocid,
            oci.core.models.UpdateIpv6Details(
                vnic_id=vnic
            )
        )
        ret_code = response.status
        ret_data = response.data

    except oci.exceptions.ServiceError as e:
        ret_code = -1
        ret_data = str(e)

    return ret_code, ret_data


def process_json(args: argparse.Namespace) -> tuple[int, list[str]]:
    """
    Processes the provided JSON file and attempt relocation of IP addresses
    to vNICs. The structure of the JSON is expected to be:
    {
        "vnic-ocid-here": {
            "ipv4": [
                "ipv4-ocid-here",
                ...
            ],
            "ipv6": [
                "ipv6-ocid-here",
                ...
            ]
        },
        ...
    }

    Parameters:
        args (Namespace): All command-line arguements.

    Returns:
        int: Script return code.
        str[]: List of messages to return.
    """

    verbose: bool = args.verbose
    vnic_ips: dict[str, dict[str, list[str]]] = {}
    try:
        json_file: TextIO = open(args.json_file)
        vnic_ips = json.load(json_file)
        json_file.close
    except json.JSONDecodeError as jde:
        logging.error(f"Problem loading '{args.json_file}': {jde}")
        raise SystemExit(RET_FILE_FAIL)
    except FileNotFoundError as fnf:
        logging.error(fnf)
        raise SystemExit(RET_FILE_FAIL)
    except Exception as e:
        logging.exception('Encountered an unexpected exception reading JSON file')
        raise SystemExit(RET_FILE_FAIL)

    # Specifying to use a profile is an options intended mostly for manyal
    # execution and troubleshooting/debugging; the intention of this script is
    # to primarily be run using instance principals. Using a profile (and
    # thus local API keys) is *slightly* faster than using an instance
    # principal, though it is far less convenient.
    # config: type[dict[Any, Any]] = dict[Any, Any]
    config: dict[str, Any] = {}
    if args.use_profile:
        try:
            config = oci.config.from_file(file_location=args.config_file, profile_name=args.profile_name)
            oci.config.validate_config(config)
        except oci.exceptions.ConfigFileNotFound as cfnf:
            logging.error(f"Unable to locate configuration '{args.config_file}'")
            raise SystemExit(RET_SETUP_FAIL)
        except oci.exceptions.ProfileNotFound as pnf:
            logging.error(f"Unable to locate profile '{args.profile_name}'")
            raise SystemExit(RET_SETUP_FAIL)
        except Exception as e:
            logging.exception(f"Encountered an unexpected exception processing '{args.config_file} - '{args.profile_name}'")
            raise SystemExit(RET_SETUP_FAIL)

    vn_client: oci.core.VirtualNetworkClient = None
    try:
        if config:
            # A config was specified, use that to connect.
            vn_client = oci.core.VirtualNetworkClient(config=config,
                retry_strategy = oci.retry.DEFAULT_RETRY_STRATEGY)
        else:
            # No config was provided, attempt to use an instance principal.
            signer = oci.auth.signers.InstancePrincipalsSecurityTokenSigner()
            vn_client = oci.core.VirtualNetworkClient(config={}, signer=signer,
                retry_strategy = oci.retry.DEFAULT_RETRY_STRATEGY)
    except Exception as e:
        logging.exception('Unable to create OCI client')
        raise SystemExit(RET_SETUP_FAIL)

    exit_code: int = RET_SUCCESS
    exit_msgs: list[str] = []
    vnic: str = ''
    ips: dict[str, list[str]] = dict()
    for vnic, ips in vnic_ips.items():
        ret_code: int = -1
        ret_data: str = ''
        for ipv4 in ips['ipv4']:
            ret_code, ret_data = reassign_v4ip(vn_client, vnic, ipv4)
            if ret_code != HTTPStatus.OK:
                exit_code = RET_API_FAIL
                exit_msgs.append(f'{ipv4} failed')
            else:
                if isinstance(ret_data, oci.core.models.PrivateIp):
                    exit_msgs.append(f'{ret_data.ip_address} success')
                else:
                    # If for some reason the call succeeded but we didn't get
                    # the expected object back(?!), report the OCID instead.
                    exit_msgs.append(f'{ipv4} success')
            if verbose: exit_msgs.append(ret_data)

        for ipv6 in ips['ipv6']:
            ret_code, ret_data = reassign_v6ip(vn_client, vnic, ipv6)
            if ret_code != HTTPStatus.OK:
                exit_code = RET_API_FAIL
                exit_msgs.append(f'{ipv6} failed')
            else:
                if isinstance(ret_data, oci.core.models.Ipv6):
                    exit_msgs.append(f'{ret_data.ip_address} success')
                else:
                    # If for some reason the call succeeded but we didn't get
                    # the expected object back(?!), report the OCID instead.
                    exit_msgs.append(f'{ipv6} success')
            if verbose: exit_msgs.append(ret_data)

    return exit_code, exit_msgs


def get_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description='Bulk reassignment of IP addresses.')
    parser.add_argument(
        '--use-profile',
        help='use OCI profile rather than instance principal',
        action='store_true')
    parser.add_argument(
        '--config',
        help=f'the OCI config file to use (default: {oci.config.DEFAULT_LOCATION})',
        metavar='config',
        dest='config_file',
        action='store',
        default=oci.config.DEFAULT_LOCATION)
    parser.add_argument(
        '--profile',
        help=f'the profile to use from the config file (default: {oci.config.DEFAULT_PROFILE})',
        metavar='name',
        dest='profile_name',
        action='store',
        default=oci.config.DEFAULT_PROFILE)
    parser.add_argument(
        '--json',
        help='JSON file containing vNIC and IP/OCID details',
        metavar='json',
        required=True,
        dest='json_file',
        action='store')
    parser.add_argument(
        '--verbose',
        help='be very verbose with API output',
        action='store_true')

    args: argparse.Namespace = parser.parse_args()

    if args.verbose:
        if args.use_profile:
            logging.info(f'Config: {args.config_file}')
            logging.info(f'Profile: {args.profile_name}')
        else:
            logging.info('Config: (instance principal)')
        logging.info(f'JSON: {args.json_file}')
        logging.info(f'Verbose: {args.verbose}')

    return args


def main() -> None:
    _init_logging()
    # Parse args
    args: argparse.Namespace = get_args()
    exit_code: int
    exit_msgs: list[str]
    exit_code, exit_msgs = process_json(args)
    for msg in exit_msgs:
        logging.error(msg)
    raise SystemExit(exit_code)

if __name__ == '__main__':
    main()
