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
  [ -n "${NSUPDATE_SERVER_PORT}" ] || NSUPDATE_SERVER_PORT=53
  # save the dns server and key to the account conf file.
  _saveaccountconf NSUPDATE_SERVER "${THISNSUPDATE_SERVER}"
  _saveaccountconf NSUPDATE_SERVER_PORT "${THISNSUPDATE_SERVER_PORT}"
  _saveaccountconf NSUPDATE_KEY "${THISNSUPDATE_KEY}"
  _saveaccountconf NSUPDATE_ZONE "${THISNSUPDATE_ZONE}"
  _info "adding ${fulldomain}. 60 in txt \"${txtvalue}\""
  [ -n "$DEBUG" ] && [ "$DEBUG" -ge "$DEBUG_LEVEL_1" ] && nsdebug="-d"
  [ -n "$DEBUG" ] && [ "$DEBUG" -ge "$DEBUG_LEVEL_2" ] && nsdebug="-D"
  if [ -z "${THISNSUPDATE_ZONE}" ]; then
    nsupdate -k "${THISNSUPDATE_KEY}" $nsdebug <<EOF
server ${THISNSUPDATE_SERVER}  ${NSUPDATE_SERVER_PORT} 
update add ${fulldomain}. 60 in txt "${txtvalue}"
send
EOF
  else
    nsupdate -k "${THISNSUPDATE_KEY}" $nsdebug <<EOF
server ${THISNSUPDATE_SERVER}  ${NSUPDATE_SERVER_PORT}
zone ${THISNSUPDATE_ZONE}.
update add ${fulldomain}. 60 in txt "${txtvalue}"
send
EOF
  fi
  if [ $? -ne 0 ]; then
    _err "error updating domain"
    return 1
  fi

  return 0
}

#Usage: dns_nsupdate_rm   _acme-challenge.www.domain.com
dns_nsupdate_rm() {
  fulldomain=$1
  _checkKeyFile || return 1
  test "${fulldomain#*_acme-challenge}" == "${fulldomain}" && _info "Skipping nsupdate for TXT on base domain." && return 0
  if [ ! -r "${NSUPDATE_SERVER}${fulldomain}.server" ] || [ -z "${NSUPDATE_SERVER}" ]; then
    THISNSUPDATE_SERVER="localhost"
  else
    THISNSUPDATE_SERVER=`cat "${NSUPDATE_SERVER}${fulldomain}.server"`
  fi
  _checkKeyFile $fulldomain || return 1
  THISNSUPDATE_KEY="${NSUPDATE_KEY}${fulldomain}.key"
  [ -n "${NSUPDATE_SERVER_PORT}" ] || NSUPDATE_SERVER_PORT=53
  _info "removing ${fulldomain}. txt"
  [ -n "$DEBUG" ] && [ "$DEBUG" -ge "$DEBUG_LEVEL_1" ] && nsdebug="-d"
  [ -n "$DEBUG" ] && [ "$DEBUG" -ge "$DEBUG_LEVEL_2" ] && nsdebug="-D"
  if [ -z "${THISNSUPDATE_ZONE}" ]; then
    nsupdate -k "${THISNSUPDATE_KEY}" $nsdebug <<EOF
server ${THISNSUPDATE_SERVER}  ${NSUPDATE_SERVER_PORT} 
update delete ${fulldomain}. txt
send
EOF
  else
    nsupdate -k "${THISNSUPDATE_KEY}" $nsdebug <<EOF
server ${THISNSUPDATE_SERVER}  ${NSUPDATE_SERVER_PORT}
zone ${THISNSUPDATE_ZONE}.
update delete ${fulldomain}. txt
send
EOF
  fi
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
