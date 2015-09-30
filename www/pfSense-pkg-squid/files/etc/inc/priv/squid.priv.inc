<?php
/*
	squid.priv.inc
	part of pfSense (http://www.pfSense.org/)
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
global $priv_list;

$priv_list['page-services-squid3'] = array();
$priv_list['page-services-squid3']['name'] = "WebCfg - Services: Squid3 package";
$priv_list['page-services-squid3']['descr'] = "Allow access to Squid3 package GUI";
$priv_list['page-services-squid3']['match'] = array();

$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_peer.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_redir.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_uri.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_upstream.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_users.xml*";

$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_antivirus.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_auth.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_cache.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_nac.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_general.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_peer.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_redir.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_sync.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_uri.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_sync.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_traffic.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_upstream.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_users.xml*";

$priv_list['page-services-squid3']['match'][] = "shortcuts/pkg_squid.inc*";
$priv_list['page-services-squid3']['match'][] = "squid_monitor.php*";
$priv_list['page-services-squid3']['match'][] = "squid_monitor_data.php*";
$priv_list['page-services-squid3']['match'][] = "squid_log_parser.php*";

?>
