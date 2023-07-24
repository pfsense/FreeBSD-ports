 - remove the alias and rules when the plugin is uninstalled
 - possibly validation of tags or free-style strings in user settings
 - crowdsec -> CrowdSec



YAML
 1 - embed/vendor (possibly version issues with php)
 2 - use composer as dependency (we have no way to cleanly uninstall yaml)

 3 - use an alternative yaml package (may be easier to integrate, but less popular, less security vetting whatever)

 4 - use yaml package of another language that comes as *.pkg (python has it)   python3, devel/py-yaml as deps - problem solved


what do we do with yaml?
 - configure lapi listen address, port
 - configure crowdsec_blacklists, crowdse6_blacklists
 - ... acquisition probably not


at install time, we also install collections and stuff
  pfsense equivalent of https://docs.opnsense.org/development/backend/autorun.html
  this needs to be in a shell script run at boot or when the plugin is installed
  cscli collections install crowdsecurity/pfsense (we can install crowdsecurity/opnsense right now)



 - see if other plugins are shipping cron files




------------------------



 rule Settings
    log: bool
    tag: words
    direction: in, out, any

--------------------------

 - debug rules creation
 - YAML: if we can install the extension as dependency, something like https://freebsd.pkgs.org/12/freebsd-amd64/php80-pear-YAML-1.0.6.pkg.html
 - defaults for lapi hostname and port => DONE
 - move page in Services
   at least landing tab with lorem ispum and links to crowdsec docs, console, discord/reddit/twitter
      and a few how-to paragraphs
   second tab with status/overview
      it will likely need to call cscli to gather information
 - dynamic active/unactive widgets with enable check (see teelgraf plugin) => DONE
 - automate alias creation crowdsec_blacklist, crowdsec6_blacklist




------------------------------------------

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
  
