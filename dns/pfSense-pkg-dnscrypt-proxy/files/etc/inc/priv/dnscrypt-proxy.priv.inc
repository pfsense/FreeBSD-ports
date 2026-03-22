<?php
/*
 * dnscrypt-proxy.priv.inc
 *
 * for pfSense
 * Copyright (c) 2026 nopoz (https://github.com/nopoz)
 * SPDX-License-Identifier: ISC
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

global $priv_list;

$priv_list['page-services-dnscrypt-proxy'] = array();
$priv_list['page-services-dnscrypt-proxy']['name'] = "WebCfg - Services: DNSCrypt Proxy";
$priv_list['page-services-dnscrypt-proxy']['descr'] = "Allow access to DNSCrypt Proxy settings";
$priv_list['page-services-dnscrypt-proxy']['match'] = array();
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy-servers.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy-cache.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy-logging.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy-lists.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "pkg_edit.php?xml=dnscrypt-proxy-advanced.xml*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "dnscrypt-proxy-querylog.php*";
$priv_list['page-services-dnscrypt-proxy']['match'][] = "dnscrypt-proxy-config.php*";

?>
