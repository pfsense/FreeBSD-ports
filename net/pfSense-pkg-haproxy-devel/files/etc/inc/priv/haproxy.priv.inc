<?php
/*
	haproxy.priv.inc
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

$priv_list['page-service-haproxy'] = array();
$priv_list['page-service-haproxy']['name'] = "WebCfg - Services: HAProxy package";
$priv_list['page-service-haproxy']['descr'] = "Allow access to HAProxy package GUI";
$priv_list['page-service-haproxy']['match'] = array();

$priv_list['page-service-haproxy']['match'][] = "haproxy_files.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_global.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_listeners.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_listeners_edit.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_pool_edit.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_pools.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy_templates.php*";

$priv_list['page-service-haproxy-stats'] = array();
$priv_list['page-service-haproxy-stats']['name'] = "WebCfg - Services: HAProxy package stats";
$priv_list['page-service-haproxy-stats']['descr'] = "Allow access to HAProxy package GUI stats";
$priv_list['page-service-haproxy-stats']['match'] = array();
$priv_list['page-service-haproxy-stats']['match'][] = "haproxy_stats.php*";

?>