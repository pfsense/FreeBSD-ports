#!/usr/bin/env sh

########  Public functions #####################

#Usage: dns_nsupdate_add   _acme-challenge.www.domain.com   "XKrxpRBosdIKFzxW_CT3KLZNf6q0HG9i01zxXp5CPBs"
dns_nsupdate_add() {
  fulldomain=$1
  txtvalue=$2
  _checkKeyFile $fulldomain || return 1
  THISNSUPDATE_KEY="${NSUPDATE_KEY}${fulldomain}.key"
  if [ ! -r "${NSUPDATE_SERVER}${fulldomain}.server" ] || [ -z "${NSUPDATE_SERVER}" ]; then
    THISNSUPDATE_SERVER="localhost"
  else
    THISNSUPDATE_SERVER=`cat "${NSUPDATE_SERVER}${fulldomain}.server"`
  fi
  # save the dns server and key to the account conf file.
  _saveaccountconf NSUPDATE_SERVER "${THISNSUPDATE_SERVER}"
  _saveaccountconf NSUPDATE_KEY "${THISNSUPDATE_KEY}"
  _info "adding ${fulldomain}. 60 in txt \"${txtvalue}\""
  nsupdate -k "${THISNSUPDATE_KEY}" <<EOF
server ${THISNSUPDATE_SERVER}
update add ${fulldomain}. 60 in txt "${txtvalue}"
send
EOF
  if [ $? -ne 0 ]; then
    _err "error updating domain"
    return 1
  fi

  return 0
}

#Usage: dns_nsupdate_rm   _acme-challenge.www.domain.com
dns_nsupdate_rm() {
  fulldomain=$1
  test "${fulldomain#*_acme-challenge}" == "${fulldomain}" && _info "Skipping nsupdate for TXT on base domain." && return 0
  if [ ! -r "${NSUPDATE_SERVER}${fulldomain}.server" ] || [ -z "${NSUPDATE_SERVER}" ]; then
    THISNSUPDATE_SERVER="localhost"
  else
    THISNSUPDATE_SERVER=`cat "${NSUPDATE_SERVER}${fulldomain}.server"`
  fi
  _checkKeyFile $fulldomain || return 1
  THISNSUPDATE_KEY="${NSUPDATE_KEY}${fulldomain}.key"
  _info "removing ${fulldomain}. txt"
  nsupdate -k "${THISNSUPDATE_KEY}" <<EOF
server ${THISNSUPDATE_SERVER}
update delete ${fulldomain}. txt
send
EOF
  if [ $? -ne 0 ]; then
    _err "error updating domain"
    return 1
  fi

  return 0
}

####################  Private functions below ##################################

_checkKeyFile() {
  THISNSUPDATE_KEY="${NSUPDATE_KEY}${1}.key"
  if [ -z "${THISNSUPDATE_KEY}" ]; then
    _err "you must specify a path to the nsupdate key file"
    return 1
  fi
  if [ ! -r "${THISNSUPDATE_KEY}" ]; then
    _err "key ${THISNSUPDATE_KEY} is unreadable"
    return 1
  fi
}
