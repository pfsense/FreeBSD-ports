
 - enable crowdsec
 - enable crowdsec_firewall
 - register agent (local [pfsense] or remote LAPI [linux])
 - register bouncer (local [pfsense] or remote LAPI [linux])


Settings / CrowdSec

  [x] enable log processor (agent)
  [x] enable firewall bouncer

  LAPI host/ip  (default localhost)
  LAPI port     (default 8080)

  [ ] use an external security engine (LAPI) - update host/ip and port if selected
      log processor - watcher id (text)
      log processor - password (text)
      firewall bouncer - api_key (text)



fields for config.xml
  enable_agent (bool)
  enable_fw_bouncer (bool)
  lapi_url (text)
  lapi_port (int)
  lapi_is_remote (bool)
  agent_user (text)
  agent_password (text)
  fw_bouncer_api_key (text)


at boot or service restart update, whenever:

  /usr/local/etc/rc.d/{crowdsec,crowdsec_firewall}     enabled=yes
  /usr/local/etc/crowdsec/config.yaml
  /usr/local/etc/crowdsec/local_api_credentials.yaml
  /usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml
  
