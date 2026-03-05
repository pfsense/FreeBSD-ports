<?php
/*
 * pkg_dnscrypt-proxy.inc
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

global $shortcuts;

$shortcuts['dnscrypt-proxy']            = [];
$shortcuts['dnscrypt-proxy']['main']    = '/pkg_edit.php?xml=dnscrypt-proxy.xml';
$shortcuts['dnscrypt-proxy']['log']     = '/status_logs.php?logfile=system&filtertext=dnscrypt-proxy';
$shortcuts['dnscrypt-proxy']['service'] = 'dnscrypt_proxy';
