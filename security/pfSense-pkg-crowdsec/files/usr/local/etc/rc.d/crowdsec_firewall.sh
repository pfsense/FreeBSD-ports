#!/bin/sh

rc_start() {
    # if the bouncer is enabled in the settings page: start the service if not already running, otherwise stop it

    bouncer="$(/usr/local/sbin/read_xml_tag.sh string installedpackages/crowdsec/config/enable_fw_bouncer)"
    if [ "$bouncer" = 'on' ]; then
        service crowdsec_firewall onestatus || service crowdsec_firewall onestart
    else
        rc_stop
    fi
}

rc_stop() {
    service crowdsec_firewall onestop
}

rc_restart() {
    rc_stop
    rc_start
}

case $1 in
        start)
                rc_start
                ;;
        stop)
                rc_stop
                ;;
        restart)
                rc_restart
                ;;
        *)
                echo "Usage: $0 {start|stop|restart}"
                exit 1
                ;;
esac
