#!/bin/sh

rc_start() {
    # if the agent or lapi are enabled in the settings page: start the service if not already running, otherwise stop it

    agent="$(/usr/local/sbin/read_xml_tag.sh string installedpackages/crowdsec/config/enable_agent)"
    lapi="$(/usr/local/sbin/read_xml_tag.sh string installedpackages/crowdsec/config/enable_lapi)"
    if [ "$agent" = 'on' ] || [ "$lapi" = 'on' ]; then
        service crowdsec onestatus || service crowdsec onestart
    else
        rc_stop
    fi
}

rc_stop() {
    service crowdsec onestop
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
