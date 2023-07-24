<?php
/*
 * crowdsec.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020-2023 Crowdsec
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("guiconfig.inc");
require_once("globals.inc");


$g['disablehelpicon'] = true;

$pgtitle = array(gettext("Security"), gettext("Crowdsec"), gettext("About"));
$pglinks = ['@self', '@self', '@self'];
$shortcut_section = "crowdsec";

include("head.inc");

$tab_array = array();
$tab_array[] = array("Read me", true, "/crowdsec_landing.php");
$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=crowdsec.xml&amp;id=0");
$tab_array[] = array("Status", false, "/crowdsec_status.php");
display_top_tabs($tab_array);


$content = <<<EOT
	<style type="text/css">
#introduction a.btn-info {
  color: black;
  margin: 3px;
}

.tab-pane {
  margin: 10px;
}
</style>


<div class="content-box tab-content">
    <div id="introduction" class="tab-pane fade in active">
        <h1>Introduction</h1>

        <p>This plugin installs a CrowdSec agent/<a href="https://doc.crowdsec.net/docs/next/local_api/intro">LAPI</a>
        node, and a <a href="https://docs.crowdsec.net/docs/bouncers/firewall/">Firewall Bouncer</a>.</p>

        <p>Out of the box, by enabling them in the "Settings" tab, they can protect the pfSense server
        by receiving thousands of IP addresses of active attackers, which are immediately banned at the
        firewall level. In addition, the logs of the ssh service and pfSense administration interface are
        analyzed for possible brute-force attacks; any such scenario triggers a ban and is reported to the
        CrowdSec Central API
        (meaning <a href="https://docs.crowdsec.net/docs/concepts/">timestamp, scenario, attacking IP</a>).</p>

        <p>Other attack behaviors can be recognized on the pfSense server and its plugins, or
        <a href="https://doc.crowdsec.net/docs/next/user_guides/multiserver_setup">any other agent</a>
        connected to the same LAPI node. Other types of remediation are possible (ex. captcha test for scraping attempts).</p>

	We recommend you to <a href="https://app.crowdsec.net/">register to the Console</a>. This helps you manage your instances,
	and us to have better overall metrics.

        <p>Please refer to the <a href="https://crowdsec.net/blog/category/tutorial/">tutorials</a> to explore
        the possibilities.</p>

        <p>For the latest plugin documentation, including how to use it with an external LAPI, see <a
        href="https://docs.crowdsec.net/docs/next/getting_started/install_crowdsec_pfsense">Install
        CrowdSec (pfSense)</a></p>

        <p>A few remarks:</p>

        <ul>
            <li>
                New acquisition files go under <code>/usr/local/etc/crowdsec/acquis.d</code>. See pfsense.yaml for details.
                The option <code>poll_without_inotify: true</code> is required if the acquitision targets are symlinks (which
                is the case for most pfsense logs).
            </li>
            <li>
                If your pfSense is &lt;22.1, you must check "Disable circular logs" in the Settings menu for the
                ssh and web-auth parsers to work. If you upgrade to 22.1, it will be done automatically.
                See <a href="https://github.com/crowdsecurity/pfsense-plugin-crowdsec/blob/main/src/etc/crowdsec/acquis.d/pfsense.yaml">acquis.d/pfsense.yaml</a>
            </li>
            <li>
                At the moment, the CrowdSec package for pfSense is fully functional on the
                command line but its web interface is limited; you can only list the installed objects and revoke
                <a href="https://docs.crowdsec.net/docs/user_guides/decisions_mgmt/">decisions</a>. For anything else
                you need the shell.
            </li>
            <li>
                Do not enable/start the agent and bouncer services with <code>sysrc</code> or <code>/etc/rc.conf</code>
                like you would on vanilla freebsd, the plugin takes care of that.
            </li>
            <li>
                The parsers, scenarios and all plugins from the Hub are periodically upgraded. The
                <a href="https://hub.crowdsec.net/author/crowdsecurity/collections/freebsd">crowdsecurity/freebsd</a> and
                <a href="https://hub.crowdsec.net/author/crowdsecurity/collections/pfsense">crowdsecurity/pfsense</a>
                collections are installed by default.
            </li>
        </ul>

        <div>
            <a class="btn btn-default btn-info" href="https://doc.crowdsec.net/docs/intro">
                Documentation
            </a>
            <a class="btn btn-default btn-info" href="https://crowdsec.net/blog/">
                Blog
            </a>
            <a class="btn btn-default btn-info" href="https://app.crowdsec.net/">
                Console
            </a>
            <a class="btn btn-default btn-info" href="https://hub.crowdsec.net/">
                CrowdSec Hub
            </a>
        </div>

        <h1>Installation</h1>

        <p>
            On the Settings tab, you can expose CrowdSec to the LAN for other servers by changing `LAPI listen address`.
            Otherwise, leave the default value.
        </p>

        <p>
            Select the first three checkboxes: IDS, LAPI and IPS. Click Apply. If you need to restart, you can do so
            from the <a href="/ui/core/service">System > Diagnostics > Services</a> page.
        </p>

        <h1>Test the plugin</h1>

        <p>
            A quick way to test that everything is working correctly is to
            execute the following command.
        </p>

        <p>
            Your ssh session should freeze and you should be kicked out from
            the firewall. You will not be able to connect to it (from the same
            IP address) for two minutes.
        </p>

        <p>
            It might be a good idea to have a secondary IP from which you can
            connect, should anything go wrong.
	</p>

	<pre><code>[root@pfSense ~]# cscli decisions add -t ban -d 2m -i &lt;your_ip_address&gt;</code></pre>

	<p>
	    This is a more secure way to test than attempting to brute-force
	    yourself: the default ban period is 4 hours, and Crowdsec reads the
	    logs from the beginning, so it could ban you even if you failed ssh
	    login 10 times in 30 seconds two hours before installing it.
	</p>

        <div>
            <a class="btn btn-default btn-info" href="https://github.com/crowdsecurity/crowdsec">
                GitHub
            </a>
            <a class="btn btn-default btn-info" href="https://discourse.crowdsec.net/">
                Discourse
            </a>
            <a class="btn btn-default btn-info" href="https://discord.com/invite/wGN7ShmEE8">
                Discord
            </a>
            <a class="btn btn-default btn-info" href="https://twitter.com/Crowd_Security">
                Twitter
            </a>
        </div>
    </div>
</div>

EOT;


echo $content;


include("foot.inc");
