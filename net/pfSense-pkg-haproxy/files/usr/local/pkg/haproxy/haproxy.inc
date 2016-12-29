<?php
/*
    haproxy.inc
	Copyright (C) 2013-2016 PiBa-NL
	Copyright (C) 2009 Scott Ullrich <sullrich@pfsense.com>
    Copyright (C) 2008 Remco Hoef
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

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("notices.inc");
require_once("filter.inc");
require_once("haproxy_utils.inc");
require_once("haproxy_socketinfo.inc");
require_once("haproxy_xmlrpcsyncclient.inc");

$d_haproxyconfdirty_path = $g['varrun_path'] . "/haproxy.conf.dirty";
#region Global haproxy array item definitions..
global $a_frontendmode;
$a_frontendmode = array();
$a_frontendmode['http'] = array('name' => "http / https(offloading)", 'shortname' => "http/https");
$a_frontendmode['https'] = array('name' => "ssl / https(TCP mode)", 'shortname' => "ssl/https");
$a_frontendmode['tcp'] = array('name' => "tcp", 'shortname' => "tcp");
/*
  if anyone actually used this what was it good for with recent package versions?
$a_frontendmode['health'] = array('name' => "health", 'shortname' => "health"); 
*/

global $a_acltypes;
$a_acltypes = array();
$a_acltypes["host_starts_with"] = array('name' => 'Host starts with:',
				'mode' => 'http', 'syntax' => 'hdr_beg(host) -i %1$s');
$a_acltypes["host_ends_with"] = array('name' => 'Host ends with:',
				'mode' =>'http', 'syntax' => 'hdr_end(host) -i %1$s');
$a_acltypes["host_matches"] = array('name' => 'Host matches:',
				'mode' =>'http', 'syntax' => 'hdr(host) -i %1$s');
$a_acltypes["host_regex"] = array('name' => 'Host regex:',
				'mode' =>'http', 'syntax' => 'hdr_reg(host) -i %1$s');
$a_acltypes["host_contains"] = array('name' => 'Host contains:',
				'mode' => 'http', 'syntax' => 'hdr_sub(host) -i %1$s');
$a_acltypes["path_starts_with"] = array('name' => 'Path starts with:',
				'mode' => 'http', 'syntax' => 'path_beg -i %1$s');
$a_acltypes["path_ends_with"] = array('name' => 'Path ends with:',
				'mode' => 'http', 'syntax' => 'path_end -i %1$s');
$a_acltypes["path_matches"] = array('name' => 'Path matches:',
				'mode' => 'http', 'syntax' => 'path -i %1$s');
$a_acltypes["path_regex"] = array('name' => 'Path regex:',
				'mode' => 'http', 'syntax' => 'path_reg -i %1$s');
$a_acltypes["path_contains"] = array('name' => 'Path contains:',
				'mode' => 'http', 'syntax' => 'path_sub -i %1$s');
$a_acltypes["url_parameter"] = array('name' => 'Url parameter contains:',
				'mode' => 'http', 'syntax' => 'url_param({parameter}) -i %1$s',
	'fields' => array(
		array('name'=>"parameter",'columnheader'=>"Parameter name",'type'=>"textbox",'size'=>"50",'mask'=>'urlparameter')
	));
$a_acltypes["ssl_c_verify_code"] = array('name' => 'SSL Client certificate verify error result:',
				'mode' => 'http', 'syntax' => 'ssl_c_verify %1$s', 'require_client_cert' => '1');
				// ssl_c_verify result codes: https://www.openssl.org/docs/apps/verify.html#DIAGNOSTICS
$a_acltypes["ssl_c_verify"] = array('name' => 'SSL Client certificate valid.',
				'mode' => 'http', 'syntax' => 'ssl_c_verify 0', 'novalue' => '1', 'require_client_cert' => '1');
$a_acltypes["ssl_c_ca_commonname"] = array('name' => 'SSL Client issued by CA common-name:',
				'mode' => 'http', 'syntax' => 'ssl_c_i_dn(CN) %1$s', 'require_client_cert' => '1');
$a_acltypes["source_ip"] = array('name' => 'Source IP matches IP or Alias:',
				'mode' => '', 'syntax' => 'src %1$s');
$a_acltypes["backendservercount"] = array('name' => 'Minimum count usable servers:',
				'mode' => '', 'syntax' => 'nbsrv({backend}) ge %1$d', 'parameters' => 'value,backendname',
	'fields' => array(
		'backend' => array('name'=>"backend",'columnheader'=>"Backend",'type'=>"select",'size'=>"50",'mask'=>'backend')
	));
$a_acltypes["traffic_is_http"] = array('name' => 'Traffic is http (no value needed):',  'inspect-delay' => '5',
				'mode' => 'tcp', 'syntax' => 'req.proto_http', 'advancedoptions' => "tcp-request content accept if { req.proto_http }");
$a_acltypes["traffic_is_ssl"] = array('name' => 'Traffic is ssl (no value needed):', 'inspect-delay' => '5',
				'mode' => 'tcp', 'syntax' => 'req.ssl_ver gt 0', 'advancedoptions' => "tcp-request content accept if { req.ssl_ver gt 0 }");
// 'ssl_sni_matches' was added in HAProxy1.5dev17
$a_acltypes["ssl_sni_matches"] = array('name' => 'Server Name Indication TLS extension matches:', 'inspect-delay' => '5',
				'mode' => 'https', 'syntax' => 'req.ssl_sni -i %1$s', 'advancedoptions' => "tcp-request content accept if { req.ssl_hello_type 1 }");
$a_acltypes["ssl_sni_contains"] = array('name' => 'Server Name Indication TLS extension contains:', 'inspect-delay' => '5',
				'mode' => 'https', 'syntax' => 'req.ssl_sni -m sub -i %1$s', 'advancedoptions' => "tcp-request content accept if { req.ssl_hello_type 1 }");
$a_acltypes["ssl_sni_starts_with"] = array('name' => 'Server Name Indication TLS extension starts with:', 'inspect-delay' => '5',
				'mode' => 'https', 'syntax' => 'req.ssl_sni -m beg -i %1$s', 'advancedoptions' => "tcp-request content accept if { req.ssl_hello_type 1 }");
$a_acltypes["ssl_sni_ends_with"] = array('name' => 'Server Name Indication TLS extension ends with:', 'inspect-delay' => '5',
				'mode' => 'https', 'syntax' => 'req.ssl_sni -m end -i %1$s', 'advancedoptions' => "tcp-request content accept if { req.ssl_hello_type 1 }");
$a_acltypes["ssl_sni_regex"] = array('name' => 'Server Name Indication TLS extension regex:', 'inspect-delay' => '5',
				'mode' => 'https', 'syntax' => 'req.ssl_sni -m reg -i %1$s', 'advancedoptions' => "tcp-request content accept if { req.ssl_hello_type 1 }");
$a_acltypes["custom"] = array('name' => 'Custom acl:',
				'mode' => '', 'syntax' => '%1$s');

global $a_checktypes;
$a_checktypes = array();
$a_checktypes['none'] = array('name' => 'none', 'syntax' => '',
	'descr' => 'No health checks will be performed.');
$a_checktypes['Basic'] = array('name' => 'Basic', 'syntax' => '',
	'descr' => 'Basic socket connection check');
$a_checktypes['HTTP'] = array('name' => 'HTTP', 'syntax' => 'httpchk',
	'descr' => 'HTTP protocol to check on the servers health, can also be used for HTTPS servers(requirs checking the SSL box for the servers).', 'parameters' => "uri,method,version");
// 'Agent' was added in HAProxy1.5dev18, and removed in 1.5dev20, in favor of the seperate agent-check option.
$a_checktypes['Agent'] = array('name' => 'Agent', 'syntax' => 'lb-agent-chk', 'usedifferenport' => 'yes', 
	'descr' => 'Use a TCP connection to read an ASCII string of the form 100%,75%,drain,down (others in haproxy manual)',
	'deprecated' => true);
$a_checktypes['LDAP'] = array('name' => 'LDAP', 'syntax' => 'ldap-check',
	'descr' => 'Use LDAPv3 health checks for server testing');
$a_checktypes['MySQL'] = array('name' => 'MySQL', 'syntax' => 'mysql-check',
	'descr' => 'Use MySQL health checks for server testing', 'parameters' => 'username');
$a_checktypes['PostgreSQL'] = array('name' => 'PostgreSQL', 'syntax' => 'pgsql-check',
	'descr' => 'Use PostgreSQL health checks for server testing', 'parameters' => 'username');
$a_checktypes['Redis'] = array('name' => 'Redis', 'syntax' => 'redis-check',
	'descr' => 'Test that the server correctly talks REDIS protocol.');
$a_checktypes['SMTP'] = array('name' => 'SMTP', 'syntax' => 'smtpchk HELO',
	'descr' => 'Use SMTP HELO health checks for server testing', 'parameters' => 'domain');
$a_checktypes['ESMTP'] = array('name' => 'ESMTP', 'syntax' => 'smtpchk EHLO',
	'descr' => 'Use ESMTP EHLO health checks for server testing', 'parameters' => 'domain');
$a_checktypes['SSL'] = array('name' => 'SSL', 'syntax' => 'ssl-hello-chk',
	'descr' => 'Use SSLv3 client hello health checks for server testing.');

global $a_httpcheck_method;
$a_httpcheck_method = array();
$a_httpcheck_method['OPTIONS'] = array('name' => 'OPTIONS', 'syntax' => 'OPTIONS');
$a_httpcheck_method['HEAD']    = array('name' => 'HEAD', 'syntax' => 'HEAD');
$a_httpcheck_method['GET']     = array('name' => 'GET', 'syntax' => 'GET');
$a_httpcheck_method['POST']    = array('name' => 'POST', 'syntax' => 'POST');
$a_httpcheck_method['PUT']     = array('name' => 'PUT', 'syntax' => 'PUT');
$a_httpcheck_method['DELETE']  = array('name' => 'DELETE', 'syntax' => 'DELETE');
$a_httpcheck_method['TRACE']   = array('name' => 'TRACE', 'syntax' => 'TRACE');

global $a_closetypes;
$a_closetypes = array();
//$a_closetypes['none'] = array('name' => 'none', 'syntax' => '',
//	'descr' => 'No close headers will be changed.');
$a_closetypes['http-keep-alive'] = array('name' => 'http-keep-alive (default)', 'syntax' => 'http-keep-alive',
	'descr' => 'By default HAProxy operates in keep-alive mode with regards to persistent connections: for each connection it processes each request and response, and leaves the connection idle on both sides between the end of a response and the start of a new request.');
$a_closetypes['http-tunnel'] = array('name' => 'http-tunnel', 'syntax' => 'http-tunnel',
	'descr' => 'Option "http-tunnel" disables any HTTP processing past the first request and the first response. This is the mode which was used by default in versions 1.0 to 1.5-dev21. It is the mode with the lowest processing overhead, which is normally not needed anymore unless in very specific cases such as when using an in-house protocol that looks like HTTP but is not compatible, or just to log one request per client in order to reduce log size. Note that everything which works at the HTTP level, including header parsing/addition, cookie processing or content switching will only work for the first request and will be ignored after the first response.');
$a_closetypes['httpclose'] = array('name' => 'httpclose', 'syntax' => 'httpclose',
	'descr' => 'The "httpclose" option removes any "Connection" header both ways, and adds a "Connection: close" header in each direction. This makes it easier to disable HTTP keep-alive than the previous 4-rules block.');
$a_closetypes['http-server-close'] = array('name' => 'http-server-close', 'syntax' => 'http-server-close',
	'descr' => 'By default, when a client communicates with a server, HAProxy will only analyze, log, and process the first request of each connection. Setting "option http-server-close" enables HTTP connection-close mode on the server side while keeping the ability to support HTTP keep-alive and pipelining on the client side.  This provides the lowest latency on the client side (slow network) and the fastest session reuse on the server side to save server resources.');
$a_closetypes['forceclose'] = array('name' => 'forceclose', 'syntax' => 'forceclose',
	'descr' => 'Some HTTP servers do not necessarily close the connections when they receive the "Connection: close" set by "option httpclose", and if the client does not close either, then the connection remains open till the timeout expires. This causes high number of simultaneous connections on the servers and shows high global session times in the logs. Note that this option also enables the parsing of the full request and response, which means we can close the connection to the server very quickly, releasing some resources earlier than with httpclose.');

global $a_servermodes;
$a_servermodes = array();
$a_servermodes["active"]['name'] = "active";
$a_servermodes["active"]['sign'] = "";
$a_servermodes["backup"]['name'] = "backup";
$a_servermodes["backup"]['sign'] = "*";
$a_servermodes["disabled"]['name'] = "disabled";
$a_servermodes["disabled"]['sign'] = "?";
$a_servermodes["inactive"]['name'] = "inactive";
$a_servermodes["inactive"]['sign'] = "-";

// http://www.exceliance.fr/sites/default/files/biblio/aloha_load_balancer_haproxy_cookie_persistence_methods_memo.pdf
global $a_cookiemode;
$a_cookiemode = array();
$a_cookiemode['passive'] 				= array('name' => 'Passive', 'syntax' => 'cookie <cookie name>',
	'descr' => 'Cookie is analysed on incoming request to choose server. HAProxy does not perform any insertion update or deletion on the Cookie or Set-Cookie. If the Cookie is not set, then the load-balancing algorithm is applied.');
$a_cookiemode['passive-silent']			= array('name' => 'Passive-silent', 'syntax' => 'cookie <cookie name> indirect',
	'descr' => 'Cookie is analysed on incoming request to choose server. HAProxy does not perform any insertion, update or deletion on the Cookie. Set-Cookie is removed from response if not required. If the Cookie is not set, then HAProxy applies the load-balancing algorithm.');
$a_cookiemode['reset']					= array('name' => 'Reset', 'syntax' => 'cookie <cookie name> rewrite',
	'descr' => 'Cookie is analysed on incoming request to choose server and Set-Cookie value is overwritten in response if present. If the Set-Cookie isn\'t sent by the server, then HAProxy won\'t set it.');
$a_cookiemode['set']					= array('name' => 'Insert', 'syntax' => 'cookie <cookie name> insert',
	'descr' => 'Cookie is analyzed on incoming request to choose server and Set-Cookie value is overwritten if present and set to an unknown value or inserted in response if not present.');
$a_cookiemode['set-silent'] 			= array('name' => 'Insert-silent', 'syntax' => 'cookie <cookie name> insert indirect',
	'descr' => 'Cookie is analyzed on incoming request to choose server and Set-Cookie value is overwritten if present, inserted in response if needed and removed if a valid Cookie was provided.');
$a_cookiemode['insert-only'] 			= array('name' => 'Insert-preserve', 'syntax' => 'cookie <cookie name> preserve insert',
	'descr' => 'Cookie is analyzed on incoming request to choose server. Set-Cookie value is set only if the server does not provide one or if the client came without the Cookie.');
$a_cookiemode['insert-only-silent'] 	= array('name' => 'Insert-preserve-silent', 'syntax' => 'cookie <cookie name> preserve insert indirect',
	'descr' => 'Cookie is analyzed on incoming request to choose server and Set-Cookie value is left untouched if present, inserted in response if needed or removed if not needed.');
$a_cookiemode['session-prefix'] 		= array('name' => 'Session-prefix', 'syntax' => 'cookie <cookie name> prefix',
	'descr' => 'Cookie is analyzed on incoming request to choose server whose Cookie Name prefix matches. Set Cookie value is prefixed using server line Cookie ID in response. Cookie is modified only between HAProxy and the client only');
$a_cookiemode['passive-session-prefix'] = array('name' => 'Passive-session-prefix', 'syntax' => 'cookie <cookie name> preserve prefix indirect',
	'descr' => 'Cookie is analysed on incoming request to choose server whose Cookie ID prefix matches.');
foreach($a_cookiemode as &$cookiemode) {
	$cookiemode['descr'] = $cookiemode['descr'] . "\n\n" . $cookiemode['syntax'] . "";
}
global $a_sticky_type;
$a_sticky_type = array();
$a_sticky_type['none'] = array('name' => 'none',
	'descr' => "No stick-table will be used");
$a_sticky_type['stick_sslsessionid'] = array('name' => 'Stick on SSL-Session-ID',
	'descr' => "Only used on https frontends. Uses the SSL-Session-ID to persist clients to a server.");
$a_sticky_type['stick_sourceipv4'] = array('name' => 'Stick on SourceIP IPv4',
	'descr' => "Stick on the client ip, drawback is that multiple clients behind a natted public ip will be balanced to the same server.");
$a_sticky_type['stick_sourceipv6'] = array('name' => 'Stick on SourceIP IPv6',
	'descr' => "Stick on the client ip, drawback is that multiple clients behind a natted public ip will be balanced to the same server.");
$a_sticky_type['stick_cookie_value'] = array('name' => 'Stick on existing Cookie value',
	'descr' => "Stick on the value of a session cookie",
	'cookiedescr' => "Enables SSL-session-id based persistence. (only use on 'https' and 'tcp' frontends that use SSL)<br/>EXAMPLE: JSESSIONID PHPSESSIONID ASP.NET_SessionId");
$a_sticky_type['stick_rdp_cookie'] = array('name' => 'Stick on RDP-cookie',
	'descr' => "Uses a RDP-Cookie send by the mstsc client, note that not all clients send this.",
	'cookiedescr' => 'EXAMPLE: msts or mstshash');


global $a_error;
$a_error = array();
$a_error['200'] = array('descr' => "stats or monitoring requests");
$a_error['400'] = array('descr' => "request invalid or too large");
$a_error['401'] = array('descr' => "authentication is required to perform the action");
$a_error['403'] = array('descr' => "request is forbidden");
$a_error['408'] = array('descr' => "timeout before the request is complete");
$a_error['500'] = array('descr' => "internal error");
$a_error['502'] = array('descr' => "server response invalid or blocked");
$a_error['503'] = array('descr' => "no server was available to handle the request");
$a_error['504'] = array('descr' => "timeout before the server responds");

global $a_sysloglevel;
$a_sysloglevel = array();
$a_sysloglevel['emerg'] = array('name' => "Emergency");
$a_sysloglevel['alert'] = array('name' => "Alert");
$a_sysloglevel['crit'] = array('name' => "Critical");
$a_sysloglevel['err'] = array('name' => "Error");
$a_sysloglevel['warning'] = array('name' => "Warning");
$a_sysloglevel['notice'] = array('name' => "Notice");
$a_sysloglevel['info'] = array('name' => "Informational");
$a_sysloglevel['debug'] = array('name' => "Debugging");

global $a_facilities;
$a_facilities = array();
$facilities = array("kern", "user", "mail", "daemon", "auth", "syslog", "lpr",
	"news", "uucp", "cron", "auth2", "ftp", "ntp", "audit", "alert", "cron2",
	"local0", "local1", "local2", "local3", "local4", "local5", "local6", "local7");
foreach($facilities as $facility) {
	$a_facilities[$facility] = array('name' => $facility);
}

global $a_filestype;
$a_filestype = array();
$a_filestype[''] = array('name' => "Errorfile");
$a_filestype['luascript'] = array('name' => "Lua script");
$a_filestype['writetodisk'] = array('name' => "Write to disk");

global $a_action;
$a_action = array();
//
$a_action["use_backend"] = array('name' => "Use Backend", 'mode' => '', 'syntax' => 'use_backend {backend}', 'usage' => 'frontend',
	'fields' => array(
		'backend' => array('name'=>"backend",'columnheader'=>"Backend",'type'=>"select",'size'=>"50",'mask'=>'backend')
	));
$a_action["use_server"] = array('name' => "Use Server", 'mode' => '', 'syntax' => 'use-server {server}', 'usage' => 'backend',
	'fields' => array(
		'server' => array('name'=>"server",'columnheader'=>"Server",'type'=>"select",'size'=>"50",'mask'=>'server')
	));
//
$a_action["custom"] = array('name' => "Custom", 'mode' => '', 'syntax' => '{customaction}',
	'fields' => array(
		array('name'=>"customaction",'columnheader'=>"Custom action",'type'=>"textbox",'size'=>"50",'mask'=>'freetext')
	));
//
$a_action["http-request_allow"] = array('name' => "http-request allow", 'mode'=> 'http', 'syntax' => 'http-request allow');
$a_action["http-request_deny"] = array('name' => "http-request deny", 'mode'=> 'http', 'syntax' => 'http-request deny');
$a_action["http-request_tarpit"] = array('name' => "http-request tarpit", 'mode'=> 'http', 'syntax' => 'http-request tarpit');
$a_action["http-request_auth"] = array('name' => "http-request auth", 'mode'=> 'http', 'syntax' => 'http-request auth {realm}',
	'fields' => array(
		array('name'=>"realm",'columnheader'=>"Realm",'type'=>"textbox",'size'=>"50",'mask'=>'freetext')
	)
);
$a_action["http-request_redirect"] = array('name' => "http-request redirect", 'mode'=> 'http', 'syntax' => 'http-request redirect {rule}',
	'fields' => array(
		array('name'=>"rule",'columnheader'=>"Rule",'type'=>"textbox",'size'=>"50",'mask'=>'logformat')
	)
);
if (haproxy_version() >= '1.6') {
	$a_action["http-request_lua"] = array('name' => "http-request lua action", 'mode'=> 'http', 'syntax' => 'http-request lua.{lua-function}',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
	$a_action["http-request_use-service"] = array('name' => "http-request lua service", 'mode'=> 'http', 'syntax' => 'http-request use-service lua.{lua-function}',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
}
$a_action["http-request_add-header"] = array('name' => "http-request header add", 'mode'=> 'http', 'syntax' => 'http-request add-header {name} {fmt}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"fmt",'columnheader'=>"New logformat value",'type'=>"textbox",'size'=>"50",'mask'=>'logformat')
	));
$a_action["http-request_set-header"] = array('name' => "http-request header set", 'mode'=> 'http', 'syntax' => 'http-request set-header {name} {fmt}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"fmt",'columnheader'=>"New logformat value",'type'=>"textbox",'size'=>"50",'mask'=>'logformat')
	));
$a_action["http-request_del-header"] = array('name' => "http-request header delete", 'mode'=> 'http', 'syntax' => 'http-request del-header {name}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername')
	));
$a_action["http-request_replace-header"] = array('name' => "http-request header replace", 'mode'=> 'http',  'syntax' => 'http-request replace-header {name} {find} {replace}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"find",'columnheader'=>"Find regex",'type'=>"textbox",'size'=>"50",'mask'=>'match-regex'),
		array('name'=>"replace",'columnheader'=>"Replace by",'type'=>"textbox",'size'=>"50",'mask'=>'replace-fmt')
	));
$a_action["http-request_replace-value"] = array('name' => "http-request header replace value", 'mode'=> 'http', 'syntax' => 'http-request replace-value {name} {find} {replace}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"find",'columnheader'=>"Find regex",'type'=>"textbox",'size'=>"50",'mask'=>'match-regex'),
		array('name'=>"replace",'columnheader'=>"Replace by",'type'=>"textbox",'size'=>"50",'mask'=>'replace-fmt')
	));
//
$a_action["http-response_allow"] = array('name' => "http-response allow", 'mode'=> 'http', 'syntax' => 'http-response allow');
$a_action["http-response_deny"] = array('name' => "http-response deny", 'mode'=> 'http', 'syntax' => 'http-response deny');
if (haproxy_version() >= '1.6') {
	$a_action["http-response_lua"] = array('name' => "http-response lua script", 'mode'=> 'http', 'syntax' => 'http-response lua.{lua-function}',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
}
$a_action["http-response_add-header"] = array('name' => "http-response header add", 'mode'=> 'http', 'syntax' => 'http-response add-header {name} {fmt}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"fmt",'columnheader'=>"New logformat value",'type'=>"textbox",'size'=>"50",'mask'=>'logformat')
	));
$a_action["http-response_set-header"] = array('name' => "http-response header set", 'mode'=> 'http', 'syntax' => 'http-response set-header {name} {fmt}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"fmt",'columnheader'=>"New logformat value",'type'=>"textbox",'size'=>"50",'mask'=>'logformat')
	));
$a_action["http-response_del-header"] = array('name' => "http-response header delete", 'mode'=> 'http', 'syntax' => 'http-response del-header {name}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername')
	));
$a_action["http-response_replace-header"] = array('name' => "http-response header replace", 'mode'=> 'http', 'syntax' => 'http-response replace-header {name} {find} {replace}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"find",'columnheader'=>"Find regex",'type'=>"textbox",'size'=>"50",'mask'=>'match-regex'),
		array('name'=>"replace",'columnheader'=>"Replace by",'type'=>"textbox",'size'=>"50",'mask'=>'replace-fmt')
	));
$a_action["http-response_replace-value"] = array('name' => "http-response header replace value", 'mode'=> 'http', 'syntax' => 'http-response replace-value {name} {find} {replace}',
	'fields' => array(
		array('name'=>"name",'columnheader'=>"Headername",'type'=>"textbox",'size'=>"50",'mask'=>'headername'),
		array('name'=>"find",'columnheader'=>"Find regex",'type'=>"textbox",'size'=>"50",'mask'=>'match-regex'),
		array('name'=>"replace",'columnheader'=>"Replace by",'type'=>"textbox",'size'=>"50",'mask'=>'replace-fmt')
	));
//
$a_action["tcp-request_connection_accept"] = array('name' => "tcp-request connection accept", 'mode'=> '', 'syntax' => 'tcp-request connection accept');
$a_action["tcp-request_connection_reject"] = array('name' => "tcp-request connection reject", 'mode'=> '', 'syntax' => 'tcp-request connection reject');
//
$a_action["tcp-request_content_accept"] = array('name' => "tcp-request content accept", 'mode'=> '', 'syntax' => 'tcp-request content accept');
$a_action["tcp-request_content_reject"] = array('name' => "tcp-request content reject", 'mode'=> '', 'syntax' => 'tcp-request content reject');
if (haproxy_version() >= '1.6') {
	$a_action["tcp-request_content_lua"] = array('name' => "tcp-request content lua script", 'mode'=> '', 'syntax' => 'tcp-request content lua.{lua-function}',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
	$a_action["tcp-request_content_use-service"] = array('name' => "tcp-request content use-service", 'mode'=> '', 'syntax' => 'tcp-request content use-service lua.{lua-function}',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
}
//
$a_action["tcp-response_content_accept"] = array('name' => "tcp-response content accept", 'mode'=> '', 'syntax' => 'tcp-response content accept');
$a_action["tcp-response_content_close"] = array('name' => "tcp-response content close", 'mode'=> '', 'syntax' => 'tcp-response content close');
$a_action["tcp-response_content_reject"] = array('name' => "tcp-response content reject", 'mode'=> '', 'syntax' => 'tcp-response content reject');
if (haproxy_version() >= '1.6') {
	$a_action["tcp-response_content_lua"] = array('name' => "tcp-response content lua script", 'mode'=> '', 'syntax' => 'tcp-response content lua.{lua-function}', 'usage' => 'backend',
	'fields' => array(
		'lua-function' => array('name'=>"lua-function",'columnheader'=>"lua function",'type'=>"textbox",'size'=>"50",'mask'=>'lua-function')
	));
}

#end


if (!function_exists('group_ports')) {
// function group_ports() is present in pfSense 2.2 in util.inc
/* create ranges of sequential port numbers (200:215) and remove duplicates */
function group_ports($ports) {
	if (!is_array($ports) || empty($ports)) {
		return;
	}

	$uniq = array();
	foreach ($ports as $port) {
		if (is_portrange($port)) {
			list($begin, $end) = explode(":", $port);
			if ($begin > $end) {
				$aux = $begin;
				$begin = $end;
				$end = $aux;
			}
			for ($i = $begin; $i <= $end; $i++)
				if (!in_array($i, $uniq))
					$uniq[] = $i;
		} elseif (is_port($port)) {
			if (!in_array($port, $uniq))
				$uniq[] = $port;
		}
	}
	sort($uniq, SORT_NUMERIC);

	$result = array();
	foreach ($uniq as $idx => $port) {
		if ($idx == 0) {
			$result[] = $port;
			continue;
		}

		$last = end($result);
		if (is_portrange($last)) {
			list($begin, $end) = explode(":", $last);
		} else {
			$begin = $end = $last;
		}

		if ($port == ($end+1)) {
			$end++;
			$result[count($result)-1] = "{$begin}:{$end}";
		} else {
			$result[] = $port;
		}
	}

	return $result;
}
}

global $haproxy_version;
function haproxy_version() {
	global $haproxy_version;
	if (empty($haproxy_version)) {
		$haproxy_version = shell_exec("haproxy -v | head -n 1 | awk '{ print $3 }'");
	}
	return $haproxy_version;
}

function haproxy_portoralias_to_list($port_or_alias) {
	// input: a port or aliasname: 80 https MyPortAlias
	// returns: a array of ports and portranges 80 443 8000:8010
	
	global $aliastable;
	$portresult = array();
	if (alias_get_type($port_or_alias) == "port") {
		$aliasports = $aliastable[$port_or_alias];
		$ports = explode(' ',$aliasports);
		foreach($ports as $port) {
			$portresults = haproxy_portoralias_to_list($port);
			$portresult = array_merge($portresult, $portresults);
		}
		return $portresult;
	} elseif (is_portrange($port_or_alias)) {
		return (array)$port_or_alias;
	} else {
		$ports = explode(",", $port_or_alias);
		foreach($ports as $port){
			if (is_port($port)) {
				if (getservbyname($port, "tcp")) {
					$port = getservbyname($port, "tcp");
				}
				if (getservbyname($port, "udp")) {
					$port = getservbyname($port, "udp");
				}
				$portresult[] = $port;
			}
		}
		return $portresult;
	}
}
function haproxy_addressoralias_to_list($address_or_alias) {
	global $aliastable;
	$result = array();
	$alias_type = alias_get_type($address_or_alias);
	if (!empty($alias_type)) {
		$alias = $aliastable[$address_or_alias];
		if ($alias_type == "url") {
			$result = explode(' ',$alias);
		} else
		if ($alias_type == "network") {
			//$result = explode(' ',$alias);
		} else
		if ($alias_type == "host") {
			$result = explode(' ',$alias);
		}
	} else {
		$result[] = $address_or_alias;
	}
	return $result;
}

function haproxy_hostoralias_to_list($host_or_alias) {
	if (is_alias($host_or_alias)){
		$result = filter_expand_alias_array($host_or_alias);
	} else {
		$result = array();
		$result[] = $host_or_alias;
	}
	return $result;
}

function haproxy_get_fileslist() {
	// returns the files array with 'keys'.
	$result = array();
	global $config;
	// create a copy to not modify the original 'keyless' array
	$a_files = $config['installedpackages']['haproxy']['files']['item'];
	if (!is_array($a_files)) {
		$a_files = array();	
	}
	foreach($a_files as $file) {
		$key = $file['name'];
		$result[$key] = $file;
	}
	return $result;
}

function haproxy_custom_php_deinstall_command() {
	global $static_output;
	$static_output .= "HAProxy, running haproxy_custom_php_deinstall_command()\n";
	update_output_window($static_output);
	$static_output .= "HAProxy, deleting haproxy webgui\n";
	update_output_window($static_output);
	unlink_if_exists("/usr/local/etc/rc.d/haproxy.sh");
	unlink_if_exists("/etc/rc.haproxy_ocsp.sh");
	$static_output .= "HAProxy, uninstalling cron job if needed\n";
	update_output_window($static_output);
	install_cron_job("/usr/local/etc/rc.d/haproxy.sh onecheck", false);
	install_cron_job("/etc/rc.haproxy_ocsp.sh", false);
	$static_output .= "HAProxy, running haproxy_custom_php_deinstall_command() DONE\n";
	update_output_window($static_output);
}

function haproxy_custom_php_install_command() {
	global $g, $static_output;
	$static_output .= "HAProxy, running haproxy_custom_php_install_command()\n";
	update_output_window($static_output);
	
	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version == "2.1" || $pf_version == "2.2") {
		$haproxy_binary = "/usr/pbi/haproxy-devel-" . php_uname("m") . "/sbin/haproxy";
	} else {
		$haproxy_binary = "/usr/local/sbin/haproxy";
	}

	$static_output .= "HAProxy, create '/usr/local/etc/rc.d/haproxy.sh'\n";
	update_output_window($static_output);
	$haproxy = <<<EOD
#!/bin/sh

# PROVIDE: haproxy
# REQUIRE: LOGIN
# KEYWORD: FreeBSD

. /etc/rc.subr

name="haproxy"
rcvar="\${name}_enable"
command="{$haproxy_binary}"
haproxy_enable=\${haproxy-"YES"}

start_cmd="haproxy_start"
stop_postcmd="haproxy_stop"
restart_cmd="haproxy_restart"
check_cmd="haproxy_check"
extra_commands="check"

load_rc_config \$name

haproxy_start () {
        echo "Starting haproxy."
        /usr/bin/env \
        PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
		/usr/local/bin/php-cgi -q -d auto_prepend_file=config.inc <<ENDOFF
<?php
			require_once("globals.inc");		
			require_once("functions.inc");
			require_once("haproxy/haproxy.inc");
			haproxy_configure();
?>
ENDOFF
}

haproxy_check () {
        echo "Checking haproxy."
        /usr/bin/env \
        PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
		/usr/local/bin/php-cgi -q -d auto_prepend_file=config.inc <<ENDOFF
<?php
			require_once("globals.inc");		
			require_once("functions.inc");
			require_once("haproxy/haproxy.inc");
			haproxy_check_run(0);
?>
ENDOFF
}

haproxy_stop () {
        echo "Stopping haproxy."
        killall haproxy
}

haproxy_restart () {
	echo "Restarting haproxy."
	haproxy_start
}

run_rc_command "\$1"

EOD;

	$fd = fopen("/usr/local/etc/rc.d/haproxy.sh", "w");
	fwrite($fd, $haproxy);
	fclose($fd);
	chmod("/usr/local/etc/rc.d/haproxy.sh", 0755);

	$haproxy_ocsp = <<<EOD
#!/usr/local/bin/php-cgi -f
<?php

/*
	Updates haproxy OCSP responses.
*/

require_once("globals.inc");		
require_once("functions.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_socketinfo.inc");
haproxy_updateocsp();

?>

EOD;
	// removing the \r prevents the "No input file specified." error..
	$haproxy_ocsp = str_replace("\r\n","\n", $haproxy_ocsp);
	$fd = fopen("/etc/rc.haproxy_ocsp.sh", "w");
	fwrite($fd, $haproxy_ocsp);
	fclose($fd);
	chmod("/etc/rc.haproxy_ocsp.sh", 0755);

	$static_output .= "HAProxy, update configuration\n";
	update_output_window($static_output);

	// call from external file, so it is surely from the newly downloaded version
	// this avoids a problem with php cache that loaded haproxy.inc during uninstalling the old version
	require_once('haproxy_upgrade_config.inc'); 
	haproxy_upgrade_config();
	
	$static_output .= "HAProxy, starting haproxy (if previously enabled)\n";
	update_output_window($static_output);
	haproxy_check_run(1);
	
	$static_output .= "HAProxy, running haproxy_custom_php_install_command() DONE\n";
	update_output_window($static_output);
}

function haproxy_find_backend($backendname) {
	global $config;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	foreach ($a_backends as &$backend) {
		if ($backend['name'] == $backendname) {
			return $backend;
		}
	}
	return null;
}

function haproxy_find_acl($name) {
	global $a_acltypes;
	if ($a_acltypes) {
		foreach ($a_acltypes as $key => $acl) {
			if ($key == $name) {
				return $acl;
			}
		}
	}
}

function write_backend($configpath, $fd, $name, $pool, $backendsettings) {
	global $config;
	$frontend = $backendsettings['frontend'];
	$ipversion = $backendsettings['ipversion'];
	$a_global = &$config['installedpackages']['haproxy'];
	$a_mailers = &$config['installedpackages']['haproxy']['email_mailers']['item'];
	$a_resolvers = $config['installedpackages']['haproxy']['dns_resolvers']['item'];

	if (!is_array($pool['ha_servers']['item']) && !$pool['stats_enabled']=='yes') {
		return;
	}
	global $a_checktypes, $a_cookiemode, $a_files_cache, $a_error;
	
	$server_options = "";
	$a_servers = &$pool['ha_servers']['item'];
	$frontendtype = $frontend['type'];
	
	fwrite ($fd, "backend " . $name . "\n");
	// https is an alias for tcp for clarity purposes
	if ($frontendtype == "https") {
		$backend_mode = "tcp";
	} else {
		$backend_mode = $frontendtype;
	}
	fwrite ($fd, "\tmode\t\t\t" . $backend_mode . "\n");
	fwrite ($fd, "\tlog\t\t\tglobal\n");

	$use_haproxyresolvers = false;
	if (haproxy_version() >= '1.6') {
		$use_mailers = is_array($a_mailers) && count($a_mailers) > 0;
		if ($use_mailers) {
			fwrite ($fd, "\t# use mailers\n");
			if (empty($pool['email_level'])) {
				$email_level = $a_global['email_level'];
			} else {
				$email_level = $pool['email_level'];
			}
		
			fwrite ($fd, "\t# level  $email_level \n");
			if (!empty($email_level) && $email_level != 'dontlog') {
				if (empty($pool['email_to'])) {
					$email_to = $a_global['email_to'];
				} else {
					$email_to = $pool['email_to'];
				}
			
				fwrite ($fd, "\temail-alert mailers\t\t\tglobalmailers\n");
				fwrite ($fd, "\temail-alert level\t\t\t{$email_level}\n");
				fwrite ($fd, "\temail-alert from\t\t\t{$a_global['email_from']}\n");
				fwrite ($fd, "\temail-alert to\t\t\t{$email_to}\n");
				if (!empty($a_global['email_myhostname'])) {
					fwrite ($fd, "\temail-alert myhostname\t\t\t{$a_global['email_myhostname']}\n");
				}
			}
		}
		
		$use_resolvers = is_array($a_resolvers) && count($a_resolvers) > 0;
		if ($use_resolvers) {
			$use_haproxyresolvers = true;
			//server s1 app1.domain.com:80 resolvers mydns resolve-prefer ipv6
			$resolverprefer = ($ipversion == "ipv4" || $ipversion == "ipv6") ? $resolverprefer = " resolve-prefer {$ipversion}" : "";
			$server_options .= " resolvers globalresolvers" . $resolverprefer;
		}
	}
	
	if ($pool['log-health-checks'] == 'yes') {
		fwrite ($fd, "\toption\t\t\tlog-health-checks\n");
	}
		
	if ($frontendtype == "http") {
		// actions that read/write http headers only work when 'mode http' is used
		if ($pool["persist_cookie_enabled"] == "yes") {
			$cookie_mode = $pool["persist_cookie_mode"];
			$cookie_cachable = $pool["persist_cookie_cachable"];
			$cookiesyntax = $a_cookiemode[$cookie_mode]["syntax"];
			$cookie = str_replace("<cookie name>", $pool["persist_cookie_name"], $cookiesyntax);
			$cookie .= $cookie_cachable == "yes" ? "" : " nocache";
			fwrite ($fd, "\t" . $cookie . "\n");
		}
		
		if ($pool["strict_transport_security"] && is_numeric($pool["strict_transport_security"])){
			fwrite ($fd, "\trspadd Strict-Transport-Security:\\ max-age={$pool["strict_transport_security"]};\n");
		}
		
		if ($pool["cookie_attribute_secure"] == 'yes'){
			fwrite ($fd, "\trspirep ^(Set-Cookie:((?!;\\ secure).)*)$ \\1;\ secure if { ssl_fc }\n");
		}
		
		if ($pool['stats_enabled'] == 'yes') {
			fwrite ($fd, "\tstats\t\t\tenable\n");
			if ($pool['stats_uri']) {
				fwrite ($fd, "\tstats\t\t\turi ".$pool['stats_uri']."\n");
			}
			if ($pool['stats_realm']) {
				fwrite ($fd, "\tstats\t\t\trealm " . haproxy_escapestring($pool['stats_realm']) . "\n");
			} else { 
				fwrite ($fd, "\tstats\t\t\trealm .\n");
			}
				
			if ($pool['stats_username'] && $pool['stats_password']) {
				fwrite ($fd, "\tstats\t\t\tauth " . haproxy_escapestring($pool['stats_username']).":". haproxy_escapestring($pool['stats_password'])."\n");
			}
			if ($pool['stats_admin'] == 'yes') {
				fwrite ($fd, "\tstats\t\t\tadmin if TRUE" . "\n");
			}
			if ($pool['stats_node']) {
				fwrite ($fd, "\tstats\t\t\tshow-node " . $pool['stats_node'] . "\n");
			}
			if ($pool['stats_desc']) {
				fwrite ($fd, "\tstats\t\t\tshow-desc " . haproxy_escapestring($pool['stats_desc']) . "\n");
			}
			if ($pool['stats_refresh']) {
				fwrite ($fd, "\tstats\t\t\trefresh " . $pool['stats_refresh'] . "\n");
			}
			
			if ($pool['stats_scope']) {
				$scope_items = explode(",", $pool['stats_scope']);
				foreach($scope_items as $scope_item) {
					fwrite ($fd, "\tstats\t\t\tscope " . $scope_item . "\n");
				}
			}
		}

		if (is_arrayset($pool,'errorfiles','item')) {
			foreach($pool['errorfiles']['item'] as $errorfile) {
				if (!is_array($a_files_cache)) {// load only once
					$a_files_cache = haproxy_get_fileslist();
				}
				$file = $errorfile['errorfile'];
				$errorcodes = explode(",",$errorfile['errorcode']);
				foreach($errorcodes as $errorcode) {
					$filename = "$configpath/errorfile_{$name}_{$errorcode}_{$file}";
					$content = base64_decode($a_files_cache[$file]['content']);
					$content = str_replace('{errormsg}', $a_error[$errorcode]['descr'], $content);
					$content = str_replace('{errorcode}', $errorcode, $content);
					file_put_contents($filename, $content);
					fwrite ($fd, "\terrorfile\t\t\t" . $errorcode ." " . $filename . "\n");
				}
			}
		}
	}
	
	switch($pool["persist_sticky_type"]) {
	case 'stick_sslsessionid':
		if ($frontendtype == "https") {
			fwrite ($fd, "\ttcp-request inspect-delay 5s\n");
			fwrite ($fd, "\tstick-table type binary len 32 size ".$pool["persist_stick_tablesize"]." expire ".$pool["persist_stick_expire"]."\n");
			fwrite ($fd, "\tacl clienthello req.ssl_hello_type 1\n");
			fwrite ($fd, "\tacl serverhello res.ssl_hello_type 2\n");
			fwrite ($fd, "\ttcp-request content accept if clienthello\n");
			fwrite ($fd, "\ttcp-response content accept if serverhello\n");
			fwrite ($fd, "\tstick on payload_lv(43,1) if clienthello\n");
			fwrite ($fd, "\tstick store-response payload_lv(43,1) if serverhello\n");
		}
		break;
	case 'stick_rdp_cookie':
		//tcp-request content accept if RDP_COOKIE
		//fwrite ($fd, "\tstick on req.rdp_cookie(msts)\n");
		fwrite ($fd, "\tstick-table type binary len 32 size ".$pool["persist_stick_tablesize"]." expire ".$pool["persist_stick_expire"]."\n");
		fwrite ($fd, "\tstick on req.rdp_cookie(mstshash)\n");
		break;
	case 'stick_sourceipv4':
		fwrite ($fd, "\tstick-table type ip size ".$pool["persist_stick_tablesize"]." expire ".$pool["persist_stick_expire"]."\n");
		fwrite ($fd, "\tstick on src\n");
		break;
	case 'stick_sourceipv6':
		fwrite ($fd, "\tstick-table type ip size ".$pool["persist_stick_tablesize"]." expire ".$pool["persist_stick_expire"]."\n");
		fwrite ($fd, "\tstick on src\n");
		break;
	case 'stick_cookie_value':
		if ($frontendtype == "http") {
			fwrite ($fd, "\tstick-table type string len {$pool["persist_stick_length"]} size ".$pool["persist_stick_tablesize"]." expire ".$pool["persist_stick_expire"]."\n");
			fwrite ($fd, "\tstick store-response res.cook({$pool["persist_stick_cookiename"]})\n");
			fwrite ($fd, "\tstick on req.cook({$pool["persist_stick_cookiename"]})\n");
		}
		break;
	}
	
	$check_type = $pool['check_type'];
	if ($check_type != 'none') {
		$optioncheck = $a_checktypes[$check_type]['syntax'];
		if ($check_type == "MySQL" || $check_type == "PostgreSQL") {
			$optioncheck .= " user " . $pool['monitor_username'];
		}
		if ($check_type == "SMTP" || $check_type == "ESMTP") {
			$optioncheck .= " " . $pool['monitor_domain'];
		}
		if ($check_type == "HTTP") {
			$uri = $pool['monitor_uri'];
			if (!$uri) {
				$uri = "/";
			}
			$optioncheck .= " {$pool['httpcheck_method']} {$uri} {$pool['monitor_httpversion']}";
		}
		if ($check_type == "Agent") {
			$checkport = " port " . $pool['monitor_agentport'];
		}
	}
	
	if ($pool['balance']) {
		$parameters = "";
		if ($pool['balance'] == 'uri') {
			if (!empty($pool['balance_urilen'])) {
				$parameters .= " len {$pool['balance_urilen']}";
			}
			if (!empty($pool['balance_uridepth'])) {
				$parameters .= " depth {$pool['balance_uridepth']}";
			}
			if ($pool['balance_uriwhole'] == 'yes') {
				$parameters .= " whole";
			}
			
		}
		fwrite ($fd, "\tbalance\t\t\t{$pool['balance']}{$parameters}\n");
	}
	if (empty($pool['connection_timeout'])) {
		$pool['connection_timeout'] = 30000;
	}
	fwrite ($fd, "\ttimeout connect\t\t" . $pool['connection_timeout'] . "\n");

	if (empty($pool['server_timeout'])) {
		$pool['server_timeout'] = 30000;
	}
	fwrite ($fd, "\ttimeout server\t\t" . $pool['server_timeout'] . "\n");

	if (empty($pool['retries'])) {
		$pool['retries'] = 3;
	}
	fwrite ($fd, "\tretries\t\t\t" . $pool['retries'] . "\n");

	$addrprefix = "";
	$dnsquerytype = "A,AAAA";
	if ($pool['transparent_clientip'] == 'yes') {
		if ($ipversion == "ipv6") {
			$addrprefix = "ipv6@";
			$dnsquerytype = "AAAA";
		}
		if ($ipversion == "ipv4") {
			$addrprefix = "ipv4@";
			$dnsquerytype = "A";
		}
		fwrite ($fd, "\tsource $addrprefix usesrc clientip\n");
	}

	$uri = $pool['monitor_uri'];
	if ($pool['monitor_uri']) {
		$uri = $pool['monitor_uri'];
	} else {
		$uri = "/";
	}
	
	if ($optioncheck) {
		fwrite ($fd, "\toption\t\t\t{$optioncheck}\n");
	}
	
	if ($pool['advanced_backend']) {
		$adv_be	= explode("\n", base64_decode($pool['advanced_backend']));
		foreach($adv_be as $adv_line) {
			if ($adv_line != "") {
				fwrite($fd, "\t" . str_replace("\r", "", $adv_line) . "\n");
			}
		}
	}

	global $a_action;
	$config_acls = array();

	$aclcrt_name = "";
	$a_acl = get_backend_acls($pool, $frontendtype);
	if (!is_array($a_acl)) {
		$a_acl = array();
	}
	// ACL's
	foreach ($a_acl as $entry) {
		$aclitem = $entry['ref'];
		$expression = $aclitem['expression'];
		
		$aclname = $aclitem['name'];
		$acltype = haproxy_find_acl($expression);
		if (!isset($acltype)) {
			continue;
		}
		
		// Filter out acls for different modes
		if ($acltype['mode'] != '' && $acltype['mode'] != strtolower($frontendtype)) {
			continue;
		}
		if ($acltype['inspect-delay'] != '') {
			$inspectdelay = $acltype['inspect-delay'];
		}
		if ($acltype['advancedoptions'] != '') {
			$advancedextra[$acltype['syntax']] = $acltype['advancedoptions']."\n";
		}
		if ($acltype['require_client_cert']) {
			$needs_clientcert[$aclname] = true;
		}
		if ($aclitem['certacl']) {
			$aclname = "aclcrt_{$frontend['name']}";
			$aclcrt_name = $aclname;
		}
		
		if (($expression == "source_ip") && is_alias($aclitem['value'])) {
			$filename = "$configpath/ipalias_{$aclitem['value']}.lst";
			$listitems = haproxy_hostoralias_to_list($aclitem['value']);
			$fd_alias = fopen("$filename", "w");
			foreach($listitems as $item) {
				fwrite($fd_alias, $item."\r\n");
			}
			fclose($fd_alias);
			$expr = "src -f $filename";
		} else {
			$expr = sprintf($acltype['syntax'], $aclitem['value']);
			if (is_array($acltype['fields'])) {
				foreach ($acltype['fields'] as $field) {
					$fieldname = $field['name'];
					$parameter = $aclitem[$expression . $fieldname];
					if ($fieldname == "backend") {
						$backendname = $parameter . "_" . strtolower($bind['type'])."_".$ipversion;
						$parameter = $backendname;
					}
					$expr = str_replace("{{$fieldname}}", $parameter, $expr);
				}
			}
		}
		$config_acls ["\tacl\t\t\t" . $aclname . "\t" .  $expr . "\n"] = 1;
	}
	// Write acl's first, so they may be used by advanced text options written by user.
	foreach($config_acls as $acl => $dummy) {
		fwrite ($fd, $acl);
	}

	$a_actionitems = $pool['a_actionitems']['item'];
	if (!is_array($a_actionitems)) {
		$a_actionitems = array();
	}
	foreach ($a_actionitems as $actionitem) {
		$actionid = $actionitem['action'];
		$action = $a_action[$actionid];
		
		$action_cfg = $action['syntax'];
		
		if (is_array($action['fields'])) {
			foreach ($action['fields'] as $field) {
				$fieldname = $field['name'];
				$parameter = $actionitem[$actionid . $field['name']];
				
				if ($fieldname == "backend") {
					$backendname = $parameter . "_" . strtolower($bind['type'])."_".$ipversion;
					/*
					$backend = $parameter;
					if (!isset($a_pendingpl[$backendname])) {
						$a_pendingpl[$backendname] = array();
						$a_pendingpl[$backendname]['name'] = $backendname;
						$a_pendingpl[$backendname]['backend'] = $backend;
						$a_pendingpl[$backendname]['frontend'] = $bind;
						$a_pendingpl[$backendname]['ipversion'] = $ipversion;
					}*/
					$parameter = $backendname;
				}
				$action_cfg = str_replace("{{$fieldname}}", $parameter, $action_cfg);
			}
		}
		$condition = "";
		if (!empty($actionitem['acl']) || !empty($systemacl)) {
			$useclientcert = "";
			$useracls = "";
			$aclnames = explode(' ', $actionitem['acl']);
			foreach($aclnames as $aclname) {
				if ($needs_clientcert[$aclname]) {
					$useclientcert = " aclsystem_ssl_c_used";
				}
				$not = "";
				foreach ($a_acl as $entry) {
					if ($entry['ref']['name'] == $aclname && $entry['ref']['not'] == 'yes') {
						$not = "!";
					}
				}
				$useracls .= " {$not}{$aclname}";
			}
			$condition = " if {$useracls}{$useclientcert} {$systemacl}";
		}
		
		$action = "\t{$action_cfg} {$condition}\n";
		
		if ($actionid == "use_backend") {							
			if (empty($condition)) {
				$config_usedefaultbackends .= "\tdefault_backend {$parameter}{$condition}\n";
			} else {
				if (!empty($actionitem['acl'])){
					$config_usebackends .= $action;
				} else {
					// add use_backend if ipv4/6 before default_backend if any exists..
					$config_usedefaultbackends .= $action;
				}
			}
		} else {
			$config_actions .= $action;
		}
	}
	fwrite ($fd, $config_actions);
	
	if ($pool['advanced']) {
		$advanced = base64_decode($pool['advanced']);
		$advanced_txt = " " . $advanced;
	} else {
		$advanced_txt = "";
	}
	
	if ($check_type != 'none') {
		if ($pool['checkinter']) {
			$checkinter = " check inter {$pool['checkinter']}";
		} else {
			$checkinter = " check inter 1000";
		}
	}
	
	//agent-check requires at least haproxy v1.5dev20
	if ($pool['agent_check']) {
		$agentcheck = " agent-check agent-inter {$pool['agent_inter']} agent-port {$pool['agent_port']}";
	}

	if (is_array($a_servers)) {
		foreach($a_servers as $be) {
			if ($be['status'] == "inactive") {
				continue;
			}
			if ($be['cookie'] && $frontendtype == "http") {
				$cookie = " cookie {$be['cookie']}";
			} else {
				$cookie = "";
			}
			
			if (!$be['name']) {
				$be['name'] = $be['address'];
			}
			if (!$be['status'] || $be['status'] != 'active') {
				$isbackup = $be['status'];
			} else {
				$isbackup = "";
			}
			$ssl = "";
			$cafile = "";
			$crlfile = "";
			$crtfile = "";
			$verifynone = "";
			$verifyhost = "";
			if ($be['ssl'] == 'yes') {
				$ssl = $frontendtype == "http" ? ' ssl' : ' check-ssl';
				
				if ($be['sslserververify'] != 'yes') {
					$verifynone = " verify none";
				} else {
					$verifyhost = isset($be['verifyhost']) && $be['verifyhost'] != "" ? " verifyhost {$be['verifyhost']}" : "";
					
					$ca = $be['ssl-server-ca'];
					$filename = "$configpath/ca_$ca.pem";
					haproxy_write_certificate_crt($filename, $ca);
					$cafile = " ca-file $filename";
					
					$crl = $be['ssl-server-crl'];
					if ($crl && $crl != "") {
						$filename = "$configpath/crl_$crl.pem";
						haproxy_write_certificate_crl($filename, $crl);
						$crlfile = " crl-file $filename";
					}
				}
				
				$server_clientcert = $be['ssl-server-clientcert'];
				if ($server_clientcert && $server_clientcert != "") {
					$filename = "$configpath/server_clientcert_$server_clientcert.pem";
					haproxy_write_certificate_crt($filename, $server_clientcert, true);
					$crtfile = " crt $filename";
				}
			}
			$weight = "";
			if (is_numeric($be['weight'])) {
				$weight = " weight " . $be['weight'];
			}
			$maxconn = "";
			if (is_numeric($be['maxconn'])) {
				$maxconn = " maxconn " . $be['maxconn'];
			}
			
			$unix_socket = false;
			$servers = array();
			if ($be['forwardto'] && $be['forwardto'] != "") {
				$unix_socket = true;
				$servers[] = "/{$be['forwardto']}.socket send-proxy-v2-ssl-cn";
			} else {
				if (is_ipaddr($be['address']) || $use_haproxyresolvers) {
					$servers[] = $be['address'];
				} elseif (is_hostname($be['address'])) {
					$dnsresult_servers = haproxy_utils::query_dns($be['address'], $dnsquerytype);
					foreach($dnsresult_servers as $dnsresult_server){
						$servers[] = $dnsresult_server['data'];
					}
				}
			}
			$counter = 0;
			foreach($servers as $server) {
				if (is_ipaddr($server)) {
					// skip ipv4 servers when using transparent client ip with ipv6 backend servers, and vice versa
					if ($ipversion == "ipv4" && !is_ipaddrv4($server)) {
						continue;
					}
					if ($ipversion == "ipv6" && !is_ipaddrv6($server)) {
						continue;
					}
				} else {
					if (!$unix_socket) {
						// place the ipv4@ or ipv6@ before the address, but not when using a unix socket
						$server = $addrprefix . $server;
					}
				}
				if (!empty($be['port'])) {
					$server = $server . ":" . $be['port'];
				}
				$servername = $be['name'];
				if (count($servers) > 1) {
					$servername .= "_" . $counter;
				}
				fwrite ($fd, "\tserver\t\t\t" . $servername . " " . $server . "$ssl$cookie$checkinter$checkport$agentcheck $isbackup$weight$maxconn$cafile$crlfile$verifynone$verifyhost$crtfile$server_options{$advanced_txt} {$be['advanced']}\n");
				$counter++;
			}
		}
	}
	fwrite ($fd, "\n");
}

function haproxy_configure() {
	// reload haproxy
	return haproxy_check_run(1);
}

function haproxy_check_and_run(&$messages, $reload) {
	global $g;
	$testpath = "{$g['varetc_path']}/haproxy_test";
	haproxy_writeconf($testpath);
	$retval = exec("haproxy -c -V -f $testpath/haproxy.cfg 2>&1", $output, $err);
	$messages = "";
	if ($err > 1) {
		$messages = "<h2><strong>FATAL ERROR CODE: $err while starting haproxy</strong></h2>";
	} elseif ($err == 1) {
		$messages = "Errors found while starting haproxy";
	}
		
	if ((count($output) > 1) && $output[0] != "Configuration file is valid")
	{
		$syslogmessage = "";
		foreach($output as $line) {
			$messages .= "<br/>" . htmlspecialchars($line) . "\n";
			$syslogmessage .= str_replace("\n"," ", $line) . " ";
		}
		syslog(LOG_NOTICE, "haproxy: check error output: {$syslogmessage}");
	}
	$ok = strstr($retval, "Configuration file is valid");
	if ($ok && $reload) {
		global $haproxy_run_message, $config;
		rmdir_recursive($testpath);
		$ok = haproxy_check_run(1) == 0;
		$messages = $haproxy_run_message;
		if ($ok && isset($config['installedpackages']['haproxy']['enablesync'])) {
			mwexec_bg("/usr/local/pkg/haproxy/haproxy_sync.sh");
		}
	}
	return $ok;
}

function haproxy_lookup_cert($certid) {
	$res = lookup_ca($certid);
	if (!$res) {
		$res = lookup_cert($certid);
	}
	return $res;
}

function haproxy_write_certificate_crt($filename, $certid, $include_psk = false, $append = false) {
	$cert = haproxy_lookup_cert($certid);
	$certcontent = base64_decode($cert['crt']);
	if ($include_psk && isset($cert['prv'])) {
		$certcontent .= "\r\n".base64_decode($cert['prv']);
	}
	$flags = $append ? FILE_APPEND : 0;
	file_put_contents($filename, $certcontent, $flags);
	unset($certcontent);
	unset($cert);
}

function haproxy_write_certificate_crl($filename, $crlid, $append = false) {
	$crl = lookup_crl($crlid);
	crl_update($crl);
	$content = base64_decode($crl['text']);
	$flags = $append ? FILE_APPEND : 0;
	file_put_contents($filename, $content, $flags);
	unset($content);
	unset($crl);
}

function haproxy_write_certificate_fullchain($filename, $certid, $append = false, $skiproot = true) {
	$cert = haproxy_lookup_cert($certid);

	$certcontent = base64_decode($cert['crt']);
	if (isset($cert['prv'])) {
		$certcontent .= "\r\n".base64_decode($cert['prv']);
	}
	
	$ca = $cert;
	while(!empty($ca['caref'])) {
		$ca = lookup_ca($ca['caref']);
		if ($ca) {
			if ($skiproot && (cert_get_subject($ca['crt']) == cert_get_issuer($ca['crt']))) {
				break;
			}
			$certcontent .= "\r\n" . base64_decode($ca['crt']);
		} else {
			break;
		}
	}
	$flags = $append ? FILE_APPEND : 0;
	file_put_contents($filename, $certcontent, $flags);
	unset($certcontent);
	unset($cert);
}

function haproxy_write_certificate_issuer($filename, $certid) {
	$cert = haproxy_lookup_cert($certid);
	$certchaincontent = ca_chain($cert);
	if ($certchaincontent != "") {
		$certcontent .= "\r\n" . $certchaincontent;
	}
	unset($certchaincontent);
	file_put_contents($filename, $certcontent, 0);
	unset($certcontent);
	unset($cert);
}

function haproxy_uses_ocsp() {
	global $config;
	$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	if (!is_array($a_frontends)) {
		return false;
	}
	
	foreach ($a_frontends as $frontend) {
		if ($frontend['sslocsp'] == 'yes') {
			return true;
		}
	}
	return false;
}

function haproxy_getocspurl($filename) {
	return exec("openssl x509 -noout -ocsp_uri -in $filename", $output, $err);
}

function haproxy_updateocsp_one($socketupdate, $filename, $name) {
	if (file_exists("{$filename}.ocsp")) {
		// If the .ocsp file exists we want to use ocsp
		syslog(LOG_NOTICE, "HAProxy Retrieving OCSP for frontend {$name}.. ");
		$ocsp_url = haproxy_getocspurl($filename);
		$ocsp_host = parse_url($ocsp_url, PHP_URL_HOST);
		if (empty($ocsp_url)) {
			// If cert does not have a ocsp_uri, it cannot be updated..
			syslog(LOG_ERR, "HAProxy OCSP ERROR Cert does not have a ocsp_uri");
		} else {
			$retval = exec("openssl ocsp -issuer {$filename}.issuer -verify_other {$filename}.issuer -cert {$filename} -url {$ocsp_url} -header Host {$ocsp_host} -respout {$filename}.ocsp 2>&1", $output, $err);
			if ($socketupdate) {
				$ocspresponse = base64_encode(file_get_contents("{$filename}.ocsp"));
				$r = haproxy_socket_command("set ssl ocsp-response $ocspresponse");
				if ($r[0] == "OCSP Response updated!\n") {
					syslog(LOG_NOTICE, "HAProxy OCSP socket update successful for frontend {$name}..result: ".$retval);
				} else {
					syslog(LOG_ERR, "HAProxy OCSP ERROR while performing haproxy socket update OCSP response for:  {$name}");
				}
			} else {
				syslog(LOG_NOTICE, "HAProxy Retrieving OCSP for frontend {$name}..result: ".$retval);
			}
		}
	}
}

function haproxy_updateocsp($socketupdate = true) {
	global $config, $g;
	$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	if (!is_array($a_frontends)) {
		return true;
	}
	
	$configpath = "{$g['varetc_path']}/haproxy";
	foreach ($a_frontends as $frontend) {
		$filename = "$configpath/{$frontend['name']}.pem";
		haproxy_updateocsp_one($socketupdate, $filename, $frontend['name']);
		
		$subfolder = "$configpath/{$frontend['name']}";
		if (is_arrayset($frontend, 'ha_certificates', 'item')) {
			$certs = $frontend['ha_certificates']['item'];
			foreach($certs as $cert) {
				$filename = "$subfolder/{$cert['ssl_certificate']}.pem";
				haproxy_updateocsp_one($socketupdate, $filename, $frontend['name']);
			}
		}
	}
}

function haproxy_writeconf($configpath) {
	global $config, $a_files_cache;
	global $aliastable;
	global $a_action, $a_error;
	if (!isset($aliastable)) {
		alias_make_table($config);
	}
	$chroot_dir = "/tmp/haproxy_chroot"; // can contain socket to forward connection from backend to frontend. "/var/empty"
	@mkdir($chroot_dir, 0755, true);

	$configfile = $configpath . "/haproxy.cfg";

	rmdir_recursive($configpath);
	@mkdir($configpath, 0755, true);
	
	$a_global = &$config['installedpackages']['haproxy'];
	$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$a_mailers = &$config['installedpackages']['haproxy']['email_mailers']['item'];
	$a_resolvers = &$config['installedpackages']['haproxy']['dns_resolvers']['item'];
	$a_files = &$config['installedpackages']['haproxy']['files']['item'];
	if (!is_array($a_frontends)) {
		$a_frontends = array();
	}
	if (!is_array($a_backends)) {
		$a_backends = array();
	}
	if (!is_array($a_mailers)) {
		$a_mailers = array();
	}
	if (!is_array($a_resolvers)) {
		$a_resolvers = array();
	}
	
	$fd = fopen($configfile, "w");
	fwrite ($fd, "# Automaticaly generated, dont edit manually.\n");
	$d = date("Y-m-d H:i");
	fwrite ($fd, "# Generated on: {$d}\n");
	if (is_array($a_global)) {
		fwrite ($fd, "global\n");
		if ($a_global['maxconn']) {
			fwrite ($fd, "\tmaxconn\t\t\t".$a_global['maxconn']."\n");
		}
		if ($a_global['remotesyslog']) {
			fwrite ($fd, "\tlog\t\t\t{$a_global['remotesyslog']}\t{$a_global['logfacility']}\t{$a_global['loglevel']}\n");
		}
		fwrite ($fd, "\tstats socket /tmp/haproxy.socket level admin\n");
		
		if(!use_transparent_clientip_proxying()) {
			fwrite ($fd, "\tuid\t\t\t80\n");
		}
		
		fwrite ($fd, "\tgid\t\t\t80\n");
		// Set numprocs if defined or use system default (#cores)
		$numprocs = $a_global['nbproc'] ? $a_global['nbproc'] : "1";
		fwrite ($fd, "\tnbproc\t\t\t$numprocs\n");
		fwrite ($fd, "\tchroot\t\t\t$chroot_dir\n");
		fwrite ($fd, "\tdaemon\n");
		
		if ($a_global['ssldefaultdhparam']) {
			fwrite ($fd, "\ttune.ssl.default-dh-param\t{$a_global['ssldefaultdhparam']}\n");
		}
		if ($a_global['log-send-hostname']) {
			fwrite ($fd, "\tlog-send-hostname\t\t{$a_global['log-send-hostname']}\n");
		}
		fwrite ($fd, "\tserver-state-file /tmp/haproxy_server_state\n");
		
		// lua-load
		if (is_array($a_files)) {
			foreach($a_files as $file) {
				if ($file['type'] == "luascript") {
					$luafile = $configpath . "/luascript_" . $file['name'];
					file_put_contents($luafile, base64_decode($file['content']), 0);
					fwrite ($fd, "\tlua-load\t\t{$luafile}\n");
				}
			}
		}

		// Keep the advanced options on the bottom of the global settings, to allow additional sections to be easely added
		if ($a_global['advanced']) {
			$adv = explode("\n", base64_decode($a_global['advanced']));
			foreach($adv as $adv_line) {
				fwrite($fd, "\t" . str_replace("\r", "", $adv_line) . "\n");

			}
		}
		fwrite ($fd, "\n");
		
		$localstatsport = $a_global['localstatsport'];
		if ($localstatsport){
			fwrite ($fd, "listen HAProxyLocalStats\n");
			fwrite ($fd, "\tbind 127.0.0.1:$localstatsport name localstats\n");
			fwrite ($fd, "\tmode http\n");
			fwrite ($fd, "\tstats enable\n");
			if (is_numeric($a_global['localstats_refreshtime'])) {
				fwrite ($fd, "\tstats refresh {$a_global['localstats_refreshtime']}\n");
			}
			fwrite ($fd, "\tstats admin if TRUE\n");
			fwrite ($fd, "\tstats uri /haproxy/haproxy_stats.php?haproxystats=1\n");
			fwrite ($fd, "\ttimeout client 5000\n");
			fwrite ($fd, "\ttimeout connect 5000\n");
			fwrite ($fd, "\ttimeout server 5000\n");
			fwrite ($fd, "\n");
		}
	}
	
	if (haproxy_version() >= '1.6') {
		$use_mailers = is_array($a_mailers) && count($a_mailers) > 0;
		if ($use_mailers) {
			fwrite ($fd, "mailers globalmailers\n");
			foreach($a_mailers as $mailer) {
				fwrite ($fd, "\tmailer {$mailer['name']} {$mailer['mailserver']}:{$mailer['mailserverport']}\n");
			}
			fwrite ($fd, "\n");
		}
		$use_resolvers = is_array($a_resolvers) && count($a_resolvers) > 0;
		if ($use_resolvers) {
			fwrite ($fd, "resolvers globalresolvers\n");
			foreach($a_resolvers as $resolver) {
				fwrite ($fd, "\tnameserver {$resolver['name']} {$resolver['server']}:{$resolver['port']}\n");
			}
			$tmp = !empty($a_global['resolver_retries']) ? $a_global['resolver_retries'] : '3';
			fwrite ($fd, "\tresolve_retries {$tmp}\n");
			$tmp = !empty($a_global['resolver_timeoutretry']) ? $a_global['resolver_timeoutretry'] : '1s';
			fwrite ($fd, "\ttimeout retry {$tmp}\n");
			$tmp = !empty($a_global['resolver_holdvalid']) ? $a_global['resolver_holdvalid'] : '10s';
			fwrite ($fd, "\thold valid {$tmp}\n");
			fwrite ($fd, "\n");
		}
	}

	// Try and get a unique array for address:port as frontends can duplicate
	$a_bind = array();
	if (is_array($a_frontends)) {
		foreach ($a_frontends as $frontend) {
			if ($frontend['status'] != 'active') {
				continue;
			}
			$primaryfrontend = get_primaryfrontend($frontend);

			$bname = $primaryfrontend['name'];
			if (!is_array($a_bind[$bname])) {
				$a_bind[$bname] = array();
				$a_bind[$bname] = $primaryfrontend;
				$a_bind[$bname]['config'] = array();
			}

			//check ssl info
			$ssl = get_frontend_uses_ssl($frontend);
			
			if ($ssl) {
				//ssl crt ./server.pem ca-file ./ca.crt verify optional crt-ignore-err all crl-file ./ca_crl.pem
				$filename = "$configpath/{$frontend['name']}.pem";
				$ssl_crt = " crt $filename";

				haproxy_write_certificate_fullchain($filename, $frontend['ssloffloadcert']);
				if ($frontend['sslocsp'] == 'yes') {
					$ocspurl = haproxy_getocspurl($filename);
					if (!empty($ocspurl)) {
						haproxy_write_certificate_issuer($filename . ".issuer", $frontend['ssloffloadcert']);
						touch($filename . ".ocsp");//create initial empty file. this will trigger updates, and inform haproxy it 'should' be using ocsp
					}
				}
				
				$subfolder = "$configpath/{$frontend['name']}";
				$certs = $frontend['ha_certificates']['item'];
				if (is_array($certs)) {
					if (count($certs) > 0) {
						@mkdir($subfolder, 0755, true);
						foreach($certs as $cert){
							$filenamefoldercert = "$subfolder/{$cert['ssl_certificate']}.pem";
							haproxy_write_certificate_fullchain($filenamefoldercert, $cert['ssl_certificate']);
							if ($frontend['sslocsp'] == 'yes') {
								$ocspurl = haproxy_getocspurl($filenamefoldercert);
								if (!empty($ocspurl)) {
									haproxy_write_certificate_issuer($filenamefoldercert . ".issuer", $cert['ssl_certificate']);
									touch($filenamefoldercert . ".ocsp");
								}
							}
						}
						$ssl_crt .= " crt $subfolder";
					}
				}
			} else {
				$ssl_crt="";
				unlink_if_exists("var/etc/{$frontend['name']}.{$frontend['port']}.crt");//cleanup for possible old haproxy package version
			}

			$b = &$a_bind[$bname];
			
			if (($frontend['secondary'] != 'yes') && ($frontend['name'] != $b['name'])) {
				// only 1 frontend can be the primary for a set of frontends that share 1 address:port.
					$input_errors[] = "Multiple primary frontends for $bname use the 'Shared Frontend' option instead";
			}
			
			if ($ssl_crt != "") {
				if ($b['ssl_info'] == "") {
					$b['ssl_info'] = "ssl {$frontend['dcertadv']}";
				}
				$b['ssl_info'] .= $ssl_crt;
			}

			// pointer to each frontend 
			$b['config'][] = $frontend;
		}
	}
	$a_pendingpl = array();

	// Construct and write out configuration for each "frontend"
	if (is_array($a_bind)) {
		foreach ($a_bind as $bind) {
			$frontendname = $bind['name'];
			if (count($bind['config']) > 1) {
				$frontendinfo = "frontend {$frontendname}-merged\n";
			} else {
				$frontendinfo = "frontend {$frontendname}\n";
			}

			fwrite ($fd, "{$frontendinfo}");
			
			$advancedextra = array();
			$ca_file = "";
			$first = true;
			if (is_array($bind['clientcert_ca']['item'])) {
				$filename = "$configpath/clientca_{$bind['name']}.pem";
				foreach($bind['clientcert_ca']['item'] as $ca) {
					if (!empty($ca['cert_ca'])) {
						haproxy_write_certificate_crt($filename, $ca['cert_ca'], false, !$first);
						$first = false;
					}
				}
				$verify = $bind['sslclientcert-none'] == 'yes' ? "verify optional" : "verify required";
				$ca_file = " ca-file $filename $verify";
			}
			$crl_file = "";
			$first = true;
			if (is_array($bind['clientcert_crl']['item'])) {
				$filename = "$configpath/clientcrl_{$bind['name']}.pem";
				foreach($bind['clientcert_crl']['item'] as $ca) {
					haproxy_write_certificate_crl($filename, $ca['cert_crl'], !$first);
					$first = false;
				}
				$crl_file = " crl-file $filename";
			}
			$advanced_bind = $bind['advanced_bind'];
			$ssl_info = $bind['ssl_info'];
			$ssl_info .= $ca_file . $crl_file;
			if ($bind['sslclientcert-invalid']) {
				$ssl_info .= " crt-ignore-err all";
			}
			
			$useipv4 = false;
			$useipv6 = false;
			// Process and add bind directives for ports
			$bindips = get_frontend_bindips($bind);
			$listenip = "";
			foreach($bindips as $bindip) {
				$ssl = $bindip['extaddr_ssl'] == 'yes' ? $ssl_info : "";
				$listenip .=  "\tbind\t\t\t{$bindip['addr']}:{$bindip['port']} name {$bindip['addr']}:{$bindip['port']} {$ssl} {$advanced_bind} {$bindip['extaddr_advanced']}\n";
				$useipv4 |= is_ipaddrv4($bindip['addr']);
				$useipv6 |= is_ipaddrv6($bindip['addr']);
			}
			fwrite ($fd, "{$listenip}");
			
			if (use_frontend_as_unixsocket($frontendname)) {
				fwrite ($fd, "\tbind /tmp/haproxy_chroot/{$frontendname}.socket name unixsocket uid 80 accept-proxy {$ssl_info} {$advanced_bind}\n");
			}

			// https is an alias for tcp for clarity purposes
			if ($bind['type'] == "https") {
				$backend_type = "tcp";
			} else {
				$backend_type = $bind['type'];
			}

			fwrite ($fd, "\tmode\t\t\t" . $backend_type . "\n");
			fwrite ($fd, "\tlog\t\t\tglobal\n");
			
			if ($bind['socket-stats'] == 'yes') {
				fwrite ($fd, "\toption\t\t\tsocket-stats\n");
			}
			if ($bind['dontlognull'] == 'yes') {
				fwrite ($fd, "\toption\t\t\tdontlognull\n");
			}
			if ($bind['dontlog-normal'] == 'yes') {
				fwrite ($fd, "\toption\t\t\tdontlog-normal\n");
			}
			if ($bind['log-separate-errors'] == 'yes') {
				fwrite ($fd, "\toption\t\t\tlog-separate-errors\n");
			}
			if ($bind['log-detailed'] == 'yes') {
				if ($backend_type == 'http') {
					fwrite ($fd, "\toption\t\t\thttplog\n");
				} else {
					fwrite ($fd, "\toption\t\t\ttcplog\n");
				}
			}

			if ($backend_type == 'http') {
				if ($bind['httpclose'] && $bind['httpclose'] != "none") {
					fwrite ($fd, "\toption\t\t\t{$bind['httpclose']}\n");
				}

				if ($bind['forwardfor']) {
					fwrite ($fd, "\toption\t\t\tforwardfor\n");
					fwrite ($fd, "\tacl https ssl_fc\n");
					fwrite ($fd, "\thttp-request set-header\t\tX-Forwarded-Proto http if !https\n");
					fwrite ($fd, "\thttp-request set-header\t\tX-Forwarded-Proto https if https\n");
				}
			}

			if ($bind['max_connections']) {
				fwrite ($fd, "\tmaxconn\t\t\t{$bind['max_connections']}\n");
			}

			if (!$bind['client_timeout']) {
				$bind['client_timeout'] = 30000;
			}
			
			fwrite ($fd, "\ttimeout client\t\t{$bind['client_timeout']}\n");

			if (is_arrayset($bind,'a_errorfiles','item')) {
				foreach($bind['a_errorfiles']['item'] as $errorfile) {
					if (!is_array($a_files_cache)) {// load only once
						$a_files_cache = haproxy_get_fileslist();
					}
					$file = $errorfile['errorfile'];
					$errorcodes = explode(",",$errorfile['errorcode']);
					foreach($errorcodes as $errorcode) {
						$filename = "$configpath/errorfile_{$frontendname}_{$errorcode}_{$file}";
						$content = base64_decode($a_files_cache[$file]['content']);
						$content = str_replace('{errormsg}', $a_error[$errorcode]['descr'], $content);
						$content = str_replace('{errorcode}', $errorcode, $content);
						file_put_contents($filename, $content);
						fwrite ($fd, "\terrorfile\t\t\t" . $errorcode ." " . $filename . "\n");
					}
				}
			}

			// Advanced pass thru
			if ($bind['advanced']) {
				$advanced = explode("\n", base64_decode($bind['advanced']));
				foreach ($advanced as $adv_line) {
					if ($adv_line != "") {
						fwrite($fd, "\t" . str_replace("\r", "", $adv_line) . "\n");
					}
				}
			}
	
			// Combine the rest of the frontend configs
			$default_backend = "";
			$config_acls = array();
			$config_actions = "";
			$config_usebackends = "";
			$config_usedefaultbackends = "";
			
			$transparent_clientip = false;
			foreach ($bind['config'] as $frontend) {
				if (frontend_usetransparentbackend($frontend)) {
					$transparent_clientip = true;
					break;
				}
			}
			if ($transparent_clientip && $useipv4 && $useipv6) {
				// set the src_is_ipv4 acl if needed.
				$acl = "\tacl\t\t\tsrc_is_ipv4\tsrc 0.0.0.0/0\n";
				$config_acls[$acl] = 1;
			}
			
			$inspectdelay = 0;
			$needs_clientcert = array();
			foreach ($bind['config'] as $frontend) {
				// loop through 'shared frontends' within one primary.
				
				$a_acl = get_frontend_acls($frontend);
				
				$a_actionitems = $frontend['a_actionitems']['item'];
				if (!is_array($a_actionitems)) {
					$a_actionitems = array();
				}
				if (!empty($frontend['backend_serverpool'])) {
					// insert extra use_backend action without a user-condition
					$item = array();
					$item['action'] = "use_backend";
					$item['use_backendbackend'] = $frontend['backend_serverpool'];
					$a_actionitems[] = $item;
				}
				$transparent_clientip = frontend_usetransparentbackend($frontend);
				
				$allowfordefaultbackend = true;
				$ipv = array();
				if ($transparent_clientip) {
					if ($useipv4 && $useipv6) {
						$ipv["ipv4"]['acl'] = " src_is_ipv4 ";
						$ipv["ipv4"]['aclnameadd'] = "_ipv4";
						$ipv["ipv6"]['acl'] = " !src_is_ipv4 ";
						$ipv["ipv6"]['aclnameadd'] = "_ipv6";
						$allowfordefaultbackend = false; // transparent backend must always match client-ip which is ipv4 v.s. ipv6 specific so there cannot be a default.
					} elseif ($useipv6) {
						$ipv["ipv6"]['acl'] = " ";
						$ipv["ipv6"]['aclnameadd'] = "";
					} else {
						$ipv["ipv4"]['acl'] = " ";
						$ipv["ipv4"]['aclnameadd'] = "";
					}
				} else {
					$ipv["ipvANY"]['acl'] = " ";
					$ipv["ipvANY"]['aclnameadd'] = "";
				}

				foreach($ipv as $ipversion => $ipversionoptions) {
					$aclcrt_name = "";
					
					// ACL's
					foreach ($a_acl as $entry) {
						$aclitem = $entry['ref'];
						$expression = $aclitem['expression'];
						
						$aclname = $aclitem['name'];
						$acltype = haproxy_find_acl($expression);
						if (!isset($acltype)) {
							continue;
						}
						
						// Filter out acls for different modes
						if ($acltype['mode'] != '' && $acltype['mode'] != strtolower($bind['type'])) {
							continue;
						}
						if ($acltype['inspect-delay'] != '') {
							$inspectdelay = $acltype['inspect-delay'];
						}
						if ($acltype['advancedoptions'] != '') {
							$advancedextra[$acltype['syntax']] = $acltype['advancedoptions']."\n";
						}
						if ($acltype['require_client_cert']) {
							$needs_clientcert[$aclname] = true;
						}
						if ($aclitem['certacl']) {
							$aclname = "aclcrt_{$frontend['name']}";
							$aclcrt_name = $aclname;
						}
						
						if (($expression == "source_ip") && is_alias($aclitem['value'])) {
							$filename = "$configpath/ipalias_{$aclitem['value']}.lst";
							$listitems = haproxy_hostoralias_to_list($aclitem['value']);
							$fd_alias = fopen("$filename", "w");
							foreach($listitems as $item) {
								fwrite($fd_alias, $item."\r\n");
							}
							fclose($fd_alias);
							$expr = "src -f $filename";
						} else {
							$expr = sprintf($acltype['syntax'], $aclitem['value']);
							if (is_array($acltype['fields'])) {
								foreach ($acltype['fields'] as $field) {
									$fieldname = $field['name'];
									$parameter = $aclitem[$expression . $fieldname];
									if ($fieldname == "backend") {
										$backendname = $parameter . "_" . strtolower($bind['type'])."_".$ipversion;
										$parameter = $backendname;
									}
									$expr = str_replace("{{$fieldname}}", $parameter, $expr);
								}
							}
						}
						$config_acls ["\tacl\t\t\t" . $aclname . "\t" .  $expr . "\n"] = 1;
					}

					$systemacl = trim("{$aclcrt_name}{$ipversionoptions['acl']}");
					
					foreach ($a_actionitems as $actionitem) {
						$actionid = $actionitem['action'];
						$action = $a_action[$actionid];
						
						$action_cfg = $action['syntax'];
						
						if (is_array($action['fields'])) {
							foreach ($action['fields'] as $field) {
								$fieldname = $field['name'];
								$parameter = $actionitem[$actionid . $field['name']];
								
								if ($fieldname == "backend") {
									$backend = $parameter;
									$backendname = $parameter . "_" . strtolower($bind['type'])."_".$ipversion;
									if (!isset($a_pendingpl[$backendname])) {
										$a_pendingpl[$backendname] = array();
										$a_pendingpl[$backendname]['name'] = $backendname;
										$a_pendingpl[$backendname]['backend'] = $backend;
										$a_pendingpl[$backendname]['frontend'] = $bind;
										$a_pendingpl[$backendname]['ipversion'] = $ipversion;
									}
									$parameter = $backendname;
								}
								$action_cfg = str_replace("{{$fieldname}}", $parameter, $action_cfg);
							}
						}
						$condition = "";
						if (!empty($actionitem['acl']) || !empty($systemacl)) {
							$useclientcert = "";
							$useracls = "";
							$aclnames = explode(' ', $actionitem['acl']);
							foreach($aclnames as $aclname) {
								if ($needs_clientcert[$aclname]) {
									$useclientcert = " aclsystem_ssl_c_used";
								}
								$not = "";
								foreach ($a_acl as $entry) {
									if ($entry['ref']['name'] == $aclname && $entry['ref']['not'] == 'yes') {
										$not = "!";
									}
								}
								$useracls .= " {$not}{$aclname}";
							}
							$condition = " if {$useracls}{$useclientcert} {$systemacl}";
						}
						
						$action = "\t{$action_cfg} {$condition}\n";
						
						if ($actionid == "use_backend") {							
							if (empty($condition)) {
								$config_usedefaultbackends .= "\tdefault_backend {$parameter}{$condition}\n";
							} else {
								if (!empty($actionitem['acl'])){
									$config_usebackends .= $action;
								} else {
									// add use_backend if ipv4/6 before default_backend if any exists..
									$config_usedefaultbackends .= $action;
								}
							}
						} else {
							$config_actions .= $action;
						}
					}
				}
			}
			
			if ($inspectdelay > 0) {
				fwrite ($fd, "\ttcp-request inspect-delay\t" . $inspectdelay . "s\n");
			}
			if (count($needs_clientcert) > 0) {
				fwrite ($fd, "\tacl\t\t\taclsystem_ssl_c_used\tssl_c_used\n");
			}
				
			// Write acl's first, so they may be used by advanced text options written by user.
			foreach($config_acls as $acl => $dummy) {
				fwrite ($fd, $acl);
			}
			
			foreach($advancedextra as $extra) {
				fwrite ($fd, "\t".$extra."\n");
			}

			fwrite ($fd, $config_actions);
			// Write backends after advanced options so custom use_backend rules can be applied first.
			fwrite ($fd, $config_usebackends);
			fwrite ($fd, $config_usedefaultbackends);
			if ($default_backend) {
				fwrite ($fd, "\tdefault_backend\t\t" . $default_backend . "\n");
			}
			
			fwrite ($fd, "\n");
		}
	}
	// Construct and write out configuration for each "backend"
	if (is_array($a_pendingpl) && is_array($a_backends)) {
		foreach ($a_pendingpl as $pending) {
			foreach ($a_backends as $pool) {
				if ($pending['backend'] == $pool['name']) {
					write_backend($configpath, $fd, $pending['name'], $pool, $pending);
				}
			}
		}
	}
	fwrite ($fd, "\n");

	// close config file
	fclose($fd);

	if ($input_errors) {
		require_once("guiconfig.inc");
		print_input_errors($input_errors);
	}
}

function haproxy_is_running() {
	$running = (shell_exec("/bin/pgrep -x haproxy") != '');
	return $running;
}

function haproxy_load_modules() {
	// On FreeBSD 8 ipfw is needed to allow 'transparent' proxying (getting reply's to a non-local ip to pass back to the client-socket).
	// On FreeBSD 9 and 10 it should have been possible to do the same with the pf(4) option "divert-reply" however that is not implemented.
	// FreeBSD 10 patch proposal: http://lists.freebsd.org/pipermail/freebsd-bugs/2014-April/055823.html
	
	mute_kernel_msgs();
	if (!is_module_loaded("ipfw.ko")) {
		mwexec("/sbin/kldload ipfw");
		/* make sure ipfw is not on pfil hooks */
		mwexec("/sbin/sysctl net.inet.ip.pfil.inbound=\"pf\" net.inet6.ip6.pfil.inbound=\"pf\"" .
				" net.inet.ip.pfil.outbound=\"pf\" net.inet6.ip6.pfil.outbound=\"pf\"");
	}
	
	/* Activate layer2 filtering */
	mwexec("/sbin/sysctl net.link.ether.ipfw=1 net.inet.ip.fw.one_pass=1");
	
	unmute_kernel_msgs();
}

function frontend_usetransparentbackend($frontend) {
	$backend = haproxy_find_backend($frontend['backend_serverpool']);
	if ($backend["transparent_clientip"] == 'yes') {
		return true;
	}
	if (is_array($frontend['a_actionitems']['item'])) {
		foreach($frontend['a_actionitems']['item'] as $action) {
			if ($action['action'] == "use_backend") {
				$backend = haproxy_find_backend($action['use_backendbackend']);
				if ($backend["transparent_clientip"] == 'yes') {
					return true;
				}
			}
		}
	}
	return false;
}

function use_transparent_clientip_proxying() {
	global $config;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	if (is_array($a_backends)) {
		foreach ($a_backends as $backend) {
			if ($backend["transparent_clientip"] == 'yes') {
				return true;
			}
		}
	}
	return false;
}

function haproxy_get_transparent_backends(){
	global $config;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$transparent_backends = array();
	if (!is_array($a_backends)) {
		return $transparent_backends;
	}
	foreach ($a_backends as $backend) {
		if ($backend["transparent_clientip"] != 'yes') {
			continue;
		}
		$real_if = get_real_interface($backend["transparent_interface"]);
		$a_servers = &$backend['ha_servers']['item'];
		if (is_array($a_servers)) {
			foreach($a_servers as $be) {
				if (!$be['status'] == "inactive" || !is_ipaddr($be['address'])){
					continue;
				}
				$item = array();
				$item['name'] = $be['name'];
				$item['interface'] = $real_if;
				$item['forwardto'] = $be['forwardto'];
				$item['address'] = $be['address'];
				$item['port'] = $be['port'];
				$transparent_backends[] = $item;
			}
		}
	}
	return $transparent_backends;
}

function haproxy_generate_rules_plugin($type) {
	$rules = "";
	switch($type) {
	case 'filter':
		// Sloppy pf rules are needed because of ipfw is used to 'catch' return traffic, and pf would otherwise terminate the connection after a few packets..
		$transparent_backends = haproxy_get_transparent_backends();
		if (count($transparent_backends) > 0) {
			$rules .= "# allow HAProxy transparent traffic\n";
			foreach($transparent_backends as $tb){
				if (is_ipaddrv4($tb['address'])) {
					$rules .= "pass  out  quick  on {$tb['interface']} inet proto tcp  from any to {$tb['address']} port {$tb['port']} flags S/SA keep state ( sloppy )  label \"HAPROXY_transparent_rule_{$tb['name']}\"\n";
				}
				if (is_ipaddrv6($tb['address'])) {
					list ($addr) = explode("%", $tb['address']);
					$rules .= "pass  out  quick  on {$tb['interface']} inet6 proto tcp  from any to {$addr} port {$tb['port']} flags S/SA keep state ( sloppy )  label \"HAPROXY_transparent_rule_{$tb['name']}\"\n";
				}
			}
		}
		break;
	}
	return $rules;
}

function load_ipfw_rules() {
	// On FreeBSD 8 pf does not support "divert-reply" so ipfw is needed.
	global $g, $config;
	if (haproxy_utils::$pf_version < 2.2) {
		$ipfw_zone_haproxy = "haproxy";
	} else {
		$ipfw_zone_haproxy = "4000"; // seems that 4000 is a safe zone number to avoid conflicts with captive portal.. and 4095 is the max?
	}
	
	haproxy_load_modules();

	$transparent_backends = haproxy_get_transparent_backends();
	
	$transparent_interfaces = array();
	foreach($transparent_backends as $transparent_backend){
		$interface = $transparent_backend['interface'];
		$transparent_interfaces[$interface] = 1;
	}
	
	if (haproxy_utils::$pf_version < 2.4) {
		$rulenum = 64000; // why that high? captiveportal.inc also does it...
		if (haproxy_utils::$pf_version < 2.2) {
			// pfSense 2.1 FreeBSD 8.3
			mwexec("/usr/local/sbin/ipfw_context -a $ipfw_zone_haproxy", true);

			foreach($transparent_interfaces as $transparent_if => $value) {
				mwexec("/usr/local/sbin/ipfw_context -a $ipfw_zone_haproxy -n $transparent_if", true);
			}
		} else {
			// pfSense 2.2 FreeBSD 10
			mwexec("/sbin/ipfw zone $ipfw_zone_haproxy create", true);
			foreach($transparent_interfaces as $transparent_if => $value) {
				mwexec("/sbin/ipfw zone $ipfw_zone_haproxy madd $transparent_if", true);
			}
		}
	} else {
		// pfSense 2.4, captive portal rules start at 1000
		$rulenum = 10;
	}
	
	$rules = "flush\n";
	foreach($transparent_backends as $transparent_be) {
		if (is_ipaddrv4($transparent_be["address"])) {
			$rules .= "add $rulenum fwd localhost tcp from {$transparent_be["address"]} {$transparent_be["port"]} to any in recv {$transparent_be["interface"]}\n";
		} elseif (is_ipaddrv6($transparent_be["address"])) {
			list ($addr, $scope) = explode("%", $transparent_be['address']);
			$rules .= "add $rulenum fwd ::1 tcp from {$addr} {$transparent_be["port"]} to any in recv {$transparent_be["interface"]}\n";
		}
		
		$rulenum++;
	}
	

	file_put_contents("{$g['tmp_path']}/ipfw_{$ipfw_zone_haproxy}.haproxy.rules", $rules);
	if (haproxy_utils::$pf_version < 2.2) {
		mwexec("/usr/local/sbin/ipfw_context -s $ipfw_zone_haproxy", true);
	}
	if (haproxy_utils::$pf_version < 2.4) {
		mwexec("/sbin/ipfw -x $ipfw_zone_haproxy -q {$g['tmp_path']}/ipfw_{$ipfw_zone_haproxy}.haproxy.rules", true);
	} else {
		mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$ipfw_zone_haproxy}.haproxy.rules", true);
	}
}

function haproxy_plugin_carp($pluginparams) {
	// called by pfSense when a CARP interface changes its state (called multiple times when multiple interfaces change state)
	// $pluginparams['type'] always 'carp'
	// $pluginparams['event'] either 'rc.carpmaster' or 'rc.carpbackup'
	// $pluginparams['interface'] contains the affected interface
	$type = $pluginparams['type'];
	$event = $pluginparams['event'];
	$interface = $pluginparams['interface'];
	haproxy_check_run(0);
}

function haproxy_plugin_certificates($pluginparams) {
	global $config;
	$result = array();
	if ($pluginparams['type'] == 'certificates' && $pluginparams['event'] == 'used_certificates') {
		$result['pkgname'] = "HAProxy";
		$result['certificatelist'] = array();
		// return a array of used certificates.
		if (is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
			foreach($config['installedpackages']['haproxy']['ha_backends']['item'] as &$frontend) {
				if (get_frontend_uses_ssl($frontend)) {
					if ($frontend['secondary'] != 'yes' || $frontend['ssloffload']) {
						// primary or secondary with additional certs checkbox
						if ($frontend['ssloffloadcert']){
							$item = array();
							$cert = $frontend['ssloffloadcert'];
							$item['usedby'] = $frontend['name'];
							$result['certificatelist'][$cert][] = $item;
						}
						if ($frontend['ha_certificates'] && is_array($frontend['ha_certificates']['item'])) {
							foreach($frontend['ha_certificates']['item'] as $certref) {
								if ($certref['ssl_certificate']) {
									$item = array();
									$cert = $certref['ssl_certificate'];
									$item['usedby'] = $frontend['name'];
									$result['certificatelist'][$cert][] = $item;
								}
							}
						}
					}
				}
			}
		}
		
		$a_backends = $config['installedpackages']['haproxy']['ha_pools']['item'];
		if (is_array($a_backends)) {
			foreach ($a_backends as &$backend) {
				if (is_array($backend['ha_servers']['item'])) {
					foreach($backend['ha_servers']['item'] as $srv) {
						if ($srv['ssl-server-clientcert']) {
							$item = array();
							$cert = $srv['ssl-server-clientcert'];
							$item['usedby'] = $backend['name']."/".$srv['name'];
							$result['certificatelist'][$cert][] = $item;
						}
					}
				}
			}
		}
	}
	return $result;
}

function haproxy_carpipismaster($ip) {
	global $config;
	foreach($config['virtualip']['vip'] as $carp) {
		if ($carp['mode'] != "carp") {
			continue;
		}
		$ipaddress = $carp['subnet'];
		if ($ipaddress != $ip) {
			continue;
		}

		$carp_int = "_vip{$carp['uniqid']}";
		$status = get_carp_interface_status($carp_int);
		return ($status == "MASTER");
	}
	return null;
}

function haproxy_check_run($reload) {
	global $config, $g, $haproxy_run_message;
	$haproxylock = lock("haproxy", LOCK_EX);
	$a_global = &$config['installedpackages']['haproxy'];
	$configpath = "{$g['varetc_path']}/haproxy";
	
	if ($reload) {
		if (haproxy_is_running()) {
			$r = haproxy_socket_command("show servers state");
			file_put_contents("/tmp/haproxy_server_state", $r);
		}
				
		haproxy_writeconf($configpath);
		haproxy_updateocsp(false);
		
		if (isset($a_global['carpdev'])) {
			install_cron_job("/usr/local/etc/rc.d/haproxy.sh onecheck", true, "*/2");
		} else {
			install_cron_job("/usr/local/etc/rc.d/haproxy.sh onecheck", false);
		}
		$useocsp = haproxy_uses_ocsp();
		if ($useocsp == "true") {
			install_cron_job("/etc/rc.haproxy_ocsp.sh", true, "*/120");
		} else {
			install_cron_job("/etc/rc.haproxy_ocsp.sh", false);
		}
	}

	if (isset($a_global['enable'])) {
		if (isset($a_global['carpdev'])) {
			$status = haproxy_carpipismaster($a_global['carpdev']);
			if (!$status) {
				if (haproxy_is_running()) {
					log_error("Stopping haproxy on CARP backup.");
					//exec("/bin/pkill -F /var/run/haproxy.pid haproxy");//doesnt work for multiple pid's in a pidfile
					haproxy_kill();
				}
				unlock($haproxylock);
				return (0);
			} elseif (haproxy_is_running() && $reload == 0) { 
				unlock($haproxylock);
				return (0);
			}
			log_error("Starting haproxy on CARP master.");
			/* fallthrough */
		} elseif ($reload == 0) {
			unlock($haproxylock);
			return (0);
		}

		if (use_transparent_clientip_proxying()) {
			filter_configure();
			load_ipfw_rules();
		} else {
			if (haproxy_utils::$pf_version < 2.2) {
				mwexec("/usr/local/sbin/ipfw_context -d haproxy", true);
			} else {			
				$ipfw_zone_haproxy = 4000;
				mwexec("/sbin/ipfw zone $ipfw_zone_haproxy destroy", true);
			}
		}
		
		if (file_exists('/var/run/haproxy.pid')){
			$old_pid = file_get_contents('/var/run/haproxy.pid');
		} else {
			$old_pid = 'none';
		}
		
		if (haproxy_is_running()) {
			if (isset($a_global['terminate_on_reload'])) {
				$sf_st = "-st";//terminate old process as soon as the new process is listening
			} else {
				$sf_st = "-sf";//finish serving existing connections exit when done, and the new process is listening
			}
			
			syslog(LOG_NOTICE, "haproxy: reload old pid:$old_pid");
			exec("/usr/local/sbin/haproxy -f {$configpath}/haproxy.cfg -p /var/run/haproxy.pid $sf_st `cat /var/run/haproxy.pid` 2>&1", $output, $errcode);
		} else {
			syslog(LOG_NOTICE, "haproxy: starting old pid:$old_pid");
			exec("/usr/local/sbin/haproxy -f {$configpath}/haproxy.cfg -p /var/run/haproxy.pid -D 2>&1", $output, $errcode);
		}
		if (file_exists('/var/run/haproxy.pid')){
			$new_pid = file_get_contents('/var/run/haproxy.pid');
		} else {
			$new_pid = 'none';
		}
		syslog(LOG_NOTICE, "haproxy: started new pid:$new_pid");
		
		$syslogmessage = "";
		foreach($output as $line) {
			$haproxy_run_message .= "<br/>" . htmlspecialchars($line) . "\n";
			$syslogmessage .= str_replace("\n"," ",$line);
		}
		if (!empty($syslogmessage)) {
			syslog(LOG_NOTICE, "haproxy: startup error output!: {$syslogmessage}");
		}
					
	} else {
		if ($reload && haproxy_is_running()) {
			//exec("/bin/pkill -F /var/run/haproxy.pid haproxy");//doesnt work for multiple pid's in a pidfile
			haproxy_kill();
		}
		$errcode = 0;
	}
	unlock($haproxylock);
	return ($errcode);
}

function haproxy_kill($killimmediately = true) {
	if ($killimmediately) {
		$signal = "KILL"; // stop now
	} else {
		$signal = "USR1"; // stop when all connections are closed
	}
	killprocesses("haproxy", "/var/run/haproxy.pid", $signal);
}

function killprocesses($processname, $pidfile, $signal = "KILL") {
	exec("kill -$signal `pgrep -x $processname | grep -w -f $pidfile`");
}

function haproxy_sync_xmlrpc_settings() {
	global $config;
	if (!is_array($config['installedpackages']['haproxysyncpkg'])) {
		return false;
	}
	// preserve 'old' sync settings, that should not be overwritten by xmlrpc-sync.
	$old_config = $config['installedpackages']['haproxy'];
	$enable = isset($config['installedpackages']['haproxy']['enablesync']);
	$config['installedpackages']['haproxy'] = $config['installedpackages']['haproxysyncpkg'];
	unset($config['installedpackages']['haproxysyncpkg']);
	$new_config = &$config['installedpackages']['haproxy'];
	
	// restore 'old' settings.
	$config['installedpackages']['haproxy']['enablesync'] = $enable ? true : false;
	$new_config['log-send-hostname'] = $old_config['log-send-hostname'];
	
	write_config("haproxy, xmlrpc config synced"); // Write new 'merged' configuration
}

function haproxy_do_xmlrpc_sync() {
	$syncinfo = array();
	$syncinfo['sync_logname'] = "HAProxy";
	$syncinfo['data'] = haproxy_xmlrpc_sync_prepare_config();
	$syncinfo['sync_include'] = "/usr/local/pkg/haproxy/haproxy.inc";	
	$syncinfo['sync_done_execute'] = "haproxy_xmlrpc_sync_configure";	
	xmlrpc_sync_execute($syncinfo);
}

function haproxy_xmlrpc_sync_prepare_config() {
	/* xml will hold the sections to sync */
	global $config;
	$xml = array();
	$xml['haproxysyncpkg'] = $config['installedpackages']['haproxy'];
	return $xml;
}

function haproxy_xmlrpc_sync_configure() {
	// this function is called by xmlrpc after config has been synced.
	global $config;
	
	haproxy_sync_xmlrpc_settings();
	haproxy_configure(); // Configure HAProxy config files to use the new configuration.
	
	// sync 2nd and further nodes in the chain if applicable.
	if (isset($config['installedpackages']['haproxy']['enablesync'])) {
		haproxy_do_xmlrpc_sync();
	}
}

function get_frontend_id($name) {
	global $config;
	$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	$i = 0;
	foreach($a_frontend as $frontend)
	{
		if ($frontend['name'] == $name) {
			return $i;
		}
		$i++;
	}
	return null;
}

function haproxy_is_frontendname($name) {
	if ($name[0] == '!') {
		$name = substr($name, 1);
	}
	return get_frontend_id($name) != null;
}

function get_primaryfrontend($frontend) {
	global $config;
	$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	if ($frontend['secondary'] == 'yes') {
		$mainfrontend = $a_frontend[get_frontend_id($frontend['primary_frontend'])];
	} else {
		$mainfrontend = $frontend;
	}
	return $mainfrontend;
}

function get_frontend_ipport($frontend, $userfriendly=false) {
	$mainfrontend = get_primaryfrontend($frontend);
	$result = array();
	if (!is_arrayset($mainfrontend,"a_extaddr","item")) {
		return $result;
	}
	foreach($mainfrontend['a_extaddr']['item'] as $extaddr) {
		if ($extaddr['extaddr'] == 'custom'){
			$addr = $extaddr['extaddr_custom'];
		} else {
			$addr = haproxy_interface_ip($extaddr['extaddr'], $userfriendly);
		}
		if ($userfriendly and is_ipaddrv6($addr)) {
			$addr = "[{$addr}]";
		}
		
		$port = $extaddr['extaddr_port'];
		$newitem = array();
		$newitem['addr'] = $addr;
		$newitem['port'] = $port;
		$newitem['ssl'] = $extaddr['extaddr_ssl'];
		$result[$addr.$port] = $newitem;
	}
	ksort($result);
	return $result;
}

function get_frontend_bindips($frontend) {
	$mainfrontend = get_primaryfrontend($frontend);
	$result = array();
	if (!is_arrayset($mainfrontend,"a_extaddr","item")) {
		return $result;
	}
	foreach($mainfrontend['a_extaddr']['item'] as $extaddr) {
		$a_ip = array();
		if (isset($extaddr['extaddr']) && $extaddr['extaddr'] != "custom") {
			$a_ip[] = haproxy_interface_ip($extaddr['extaddr']);
		} else {						
			$iporalias = $extaddr['extaddr_custom'];
			$a_ip = haproxy_addressoralias_to_list($iporalias);
		}
		foreach($a_ip as $ip) {
			$portsnumeric = group_ports(haproxy_portoralias_to_list($extaddr['extaddr_port']));
			if (is_array($portsnumeric)) {
				foreach($portsnumeric as $portnumeric) {
					$portnumeric = str_replace(":","-",$portnumeric);
					$newitem = array();
					$newitem['addr'] = $ip;
					$newitem['port'] = $portnumeric;
					$newitem['extaddr_ssl'] = $extaddr['extaddr_ssl'];
					$newitem['extaddr_advanced'] = $extaddr['extaddr_advanced'];
					$result[] = $newitem;
				}
			}
		}
	}
	return $result;
}

function haproxy_check_config() {
	global $config;
	$a_backends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	$result = false;
	$activefrontends = array();
	$issues = array();
	
	foreach ($a_backends as $frontend) {
		if (($frontend['status'] != 'active') || ($frontend['secondary'] == 'yes')) {
			continue;
		}
		$ipports = get_frontend_ipport($frontend);
		foreach($ipports as $ipport) {
			$id = "{$ipport['addr']}:{$ipport['port']}";
			if (isset($activefrontends[$id])) {
				$activefrontends[$id] = $activefrontends[$id].", ".$frontend['name'];
				$issues['P_'.$id] = "Multiple primary frontends ({$activefrontends[$id]}) with IP:Port \"$id\", use Shared-Frontends instead.";
			} else {
				$activefrontends[$id] = $frontend['name'];
			}
		}
	}
	foreach ($a_backends as $frontend) {
		if (($frontend['status'] != 'active') || ($frontend['secondary'] != 'yes')) {
			continue;
		}
		$mainfrontend = get_primaryfrontend($frontend);
		if (!isset($mainfrontend)) {
			$issues['S_'.$frontend['name']] = "Secondary frontend \"{$frontend['name']}\" without active primary frontend.";
		}
	}
	foreach ($issues as $item) {
		$result .= ($result == false ? "" : "<br/>") . $item;	
	}
	return $result;
}

function get_haproxy_backends() {
	global $config;
	$a_backend = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$result = array();
	if (!is_array($a_backend)) {
		return $result;
	}
	foreach ($a_backend as &$backend) {
		$result[$backend['name']]['name'] = "{$backend['name']}";
		$result[$backend['name']]['ref'] = &$backend;
	}
	uasort($result, haproxy_compareByName);
	return $result;
}

function get_haproxy_frontends($excludeitem = "") {
	global $config;
	$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	$result = array();
	if (!is_array($a_frontend)) {
		return $result;
	}
	foreach ($a_frontend as &$frontend) {
		if ($frontend['secondary'] || $frontend['name'] == $excludeitem) {
			continue;
		}
		
		$serveraddress = get_frontend_ipport($frontend, true);
		$serveradresstext = null;
		foreach ($serveraddress as $addr) {
			$serveradresstext .=($serveradresstext == null ? "" : ", ") . "{$addr['addr']}:{$addr['port']}";
		}
		$result[$frontend['name']]['name'] = "{$frontend['name']} - {$frontend['type']}";
		$result[$frontend['name']]['ref'] = &$frontend;
	}
	uasort($result, haproxy_compareByName);
	return $result;
}

function get_frontend_uses_ssl($frontend) {
	$mainfrontend = get_primaryfrontend($frontend);
	$ssl = false;
	if (is_arrayset($mainfrontend,'a_extaddr','item')) {
		foreach($mainfrontend['a_extaddr']['item'] as $extaddr) {
			if ($extaddr['extaddr_ssl'] == 'yes') {
				$ssl = true;
				break;
			}
		}
	}
	if ($mainfrontend['name'] != $frontend['name']) {
		$ssl = $ssl && $frontend['ssloffload'] == 'yes';
	}
	return $ssl;
}

function get_frontend_uses_ssl_only($frontend) {
	$mainfrontend = get_primaryfrontend($frontend);
	if (is_arrayset($mainfrontend,'a_extaddr','item')) {
		foreach($mainfrontend['a_extaddr']['item'] as $extaddr) {
			if ($extaddr['extaddr_ssl'] != 'yes') {
				return false;
			}
		}
	}
	return true;
}

function haproxy_get_cert_acls($cert, $usealternativenames = false) {
	$result = array();
	
	if (!$usealternativenames) {
		$cert_cns = array();
		$cert_cns[] = cert_get_cn($cert['crt']);
	} else {
		$cert_cns = haproxy_get_certificate_subjectAltNames($cert['crt']);
	}
	$descr = haproxy_escape_acl_name($cert['descr']);
	unset($cert);
	//$i = 1;
	foreach ($cert_cns as $cert_cn) {
		$acl_item = array();
		$is_wildcard = substr($cert_cn, 0, 2) == "*.";
		$cert_cn_regex = str_replace(".", "\.", $cert_cn); // escape '.' in regex.
		if ($is_wildcard) {
			$cert_cn_regex = "([^\.]*)" . substr($cert_cn_regex, 1);// match only subdomains directly under the wildcard
		}
		$cert_cn_regex = "^{$cert_cn_regex}(:([0-9]){1,5})?$";// match both with and without port.
		
		$acl_item['descr'] = "Certificate ACL matches: {$cert_cn}";
		//$aclname_add = $usealternativenames ? "_{$i}" : "";
		$acl_item['ref'] = array('name' => "{$aclname}_{$descr}{$aclname_add}",'expression' => 'host_regex', 'value' => $cert_cn_regex, 'certacl' => true);
		
		//$i++;
		$result[] = $acl_item;
	}
	return $result;
}

function get_frontend_acls($frontend) {
	$mainfrontend = get_primaryfrontend($frontend);
	$result = array();
	$a_acl = &$frontend['ha_acls']['item'];
	if (is_array($a_acl))
	{
		foreach ($a_acl as $entry) {
			$acl = haproxy_find_acl($entry['expression']);
			if (!$acl) {
				continue;
			}

			// Filter out acls for different modes
			if ($acl['mode'] != '' && $acl['mode'] != strtolower($mainfrontend['type'])) {
				continue;
			}
			$not = $entry['not'] == "yes" ? "not: " : "";
			$acl_item = array();
			$acl_item['descr'] = $acl['name'] . " " . (isset($acl['novalue']) ? "" : $not . $entry['value']);
			$acl_item['ref'] = $entry;
			
			$result[] = $acl_item;
		}
	}
	
	if (get_frontend_uses_ssl($frontend)) {
		$a_acl = &$frontend['ha_acls']['item'];
		if (!is_array($a_acl)) {
			$a_acl = array();
		}
	
		//$poolname = $frontend['backend_serverpool'] . "_" . strtolower($frontend['type']);
		//$aclname = "SNI_" . $poolname;
		
		if (ifset($frontend['ssloffloadacl']) == 'yes') {
			$cert = lookup_cert($frontend['ssloffloadcert']);
			$result = array_merge($result, haproxy_get_cert_acls($cert));
		}
		if (ifset($frontend['ssloffloadacl_an']) == 'yes') {
			$cert = lookup_cert($frontend['ssloffloadcert']);
			$result = array_merge($result, haproxy_get_cert_acls($cert, true));
		}
		if (ifset($frontend['ssloffloadacladditional']) == 'yes') {
			$certs = $frontend['ha_certificates']['item'];
			if (is_array($certs)) {
				foreach ($certs as $certref) {
					$cert = lookup_cert($certref['ssl_certificate']);
					$result = array_merge($result, haproxy_get_cert_acls($cert));
				}
			}
		}
		if (ifset($frontend['ssloffloadacladditional_an']) == 'yes') {
			$certs = $frontend['ha_certificates']['item'];
			if (is_array($certs)) {
				foreach ($certs as $certref) {
					$cert = lookup_cert($certref['ssl_certificate']);
					$result = array_merge($result, haproxy_get_cert_acls($cert, true));
				}
			}
		}
	}
	return $result;
}

function get_backend_acls($backend, $type) {
	$result = array();
	$a_acl = &$backend['a_acl']['item'];
	if (is_array($a_acl))
	{
		foreach ($a_acl as $entry) {
			$acl = haproxy_find_acl($entry['expression']);
			if (!$acl) {
				continue;
			}

			// Filter out acls for different modes
			if ($acl['mode'] != '' && $acl['mode'] != $type) {
				continue;
			}
			$not = $entry['not'] == "yes" ? "not: " : "";
			$acl_item = array();
			$acl_item['descr'] = $acl['name'] . " " . (isset($acl['novalue']) ? "" : $not . $entry['value']);
			$acl_item['ref'] = $entry;
			
			$result[] = $acl_item;
		}
	}
	return $result;
}

function get_backend_id($name) {
	global $config;
	$a_backend = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$i = 0;
	if (is_array($a_backend)) {
		foreach ($a_backend as $key => $backend) {
			if ($backend['name'] == $name) {
				return $i;
			}
			$i++;
		}
	}
	return null;
}

function get_backend($name) {
	global $config;
	$a_backend = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$id = get_backend_id($name);
	if (is_numeric($id)) {
		return $a_backend[$id];
	}
	return null;
}

function use_frontend_as_unixsocket($name) {
	global $config;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	foreach ($a_backends as $backend) {
		$a_servers = &$backend['ha_servers']['item'];
		if (is_array($a_servers)) {
			foreach($a_servers as $server) {	
				if ($server['forwardto'] && $server['forwardto'] == $name) {
					return true;
				}
			}
		}
	}
	return false;
}

function haproxy_escapestring($configurationsting) {
	$result = str_replace('\\', '\\\\', $configurationsting);
	$result = str_replace(' ', '\\ ', $result);
	return str_replace('#', '\\#', $result);
}

function haproxy_escape_acl_name($aclname) {
	return preg_replace_callback('([^A-Za-z0-9\._\-\:])', function($match){return "_".dechex(ord($match[0]));}, $aclname);
}

function haproxy_find_create_certificate($certificatename) {
	$cert = lookup_cert_by_name($certificatename);
	if (is_array($cert)) {
		return $cert;
	}
	global $config;
	$a_cert =& $config['cert'];	
	$cert = array();
	$cert['refid'] = uniqid();
	$cert['descr'] = gettext($certificatename);
	
	$new_cert = array();
	$dn = array(
		"organizationName" => "haproxy-pfsense",
		"commonName"       => "haproxy-pfsense"
	);
	$new_cert = array();
	ca_create($new_cert, 1024, 2000, $dn);
	$crt = base64_decode($new_cert['crt']);
	$prv = base64_decode($new_cert['prv']);
	cert_import($cert, $crt, $prv);
	$a_cert[] = $cert;
	return $cert;
}
