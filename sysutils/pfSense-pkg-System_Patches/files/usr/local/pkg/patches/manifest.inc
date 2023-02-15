<?php
/*
 * patches/manifest.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022 Rubicon Communications, LLC (Netgate)
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

global $recommended_patches;
/*
 * Keep each patch block separate, do not attempt to combine/overlap entries to
 * save space.
 */
$recommended_patches = [
[
  'uniqid' => '528e53e8bb86642e38aa098226f5b74951d6a7bb',
  'versions' => ['2.6.0'],
  'descr' => 'Fix potential XSS from URL and URL Table alias URLs',
  'links' => [
      [ 'text' => 'pfSense-SA-22_05.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-22_05.webgui', ],
      [ 'text' => 'Redmine #13060',
         'url' => 'https://redmine.pfsense.org/issues/13060', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '611de84ae2d1e65217dbccb7e9db32a019bd8d97',
  'versions' => ['2.6.0', '22.05'],
  'descr' => 'Fix potential XSS on browser.php from unencoded filenames',
  'links' => [
      [ 'text' => 'pfSense-SA-23_01.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_01.webgui', ],
      [ 'text' => 'Redmine #13262',
         'url' => 'https://redmine.pfsense.org/issues/13262', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'e9c53ad9b35ff152d2b76b8fcbb0a94adc809124',
  'versions' => ['22.05.1'],
  'descr' => 'Fix potential XSS on browser.php from unencoded filenames',
  'links' => [
      [ 'text' => 'pfSense-SA-23_01.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_01.webgui', ],
      [ 'text' => 'Redmine #13262',
         'url' => 'https://redmine.pfsense.org/issues/13262', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '7c54d26e60fe524df63277da77c07e995bc3b351',
  'versions' => ['2.6.0', '22.05'],
  'descr' => 'Fix potential XSS from CA and Certificate descriptions',
  'links' => [
      [ 'text' => 'pfSense-SA-23_02.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_02.webgui', ],
      [ 'text' => 'CVE-2022-37417', ],
      [ 'text' => 'Redmine #13387',
         'url' => 'https://redmine.pfsense.org/issues/13387', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '6dc0750874b69373f8adbae9b0af223af13f5f4a',
  'versions' => ['2.6.0', '22.05'],
  'descr' => 'Fix potential arbitrary file create from invalid URL Table alias names',
  'links' => [
      [ 'text' => 'pfSense-SA-23_03.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_03.webgui', ],
      [ 'text' => 'Redmine #13425',
         'url' => 'https://redmine.pfsense.org/issues/13425', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '208134113d92d809d4f0e686f3817274854ff264',
  'versions' => ['22.05.1'],
  'descr' => 'Fix PHP error in URL Table Alias validation',
  'links' => [
      [ 'text' => 'pfSense-SA-23_03.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_03.webgui', ],
      [ 'text' => 'Redmine #13425',
         'url' => 'https://redmine.pfsense.org/issues/13425', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '22f7276c167210f644cf586704ec21da22002bb2',
  'versions' => ['2.6.0', '22.05'],
  'descr' => 'Fix potential arbitrary command execution via status.php from unencoded filenames',
  'links' => [
      [ 'text' => 'pfSense-SA-23_04.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_04.webgui', ],
      [ 'text' => 'Redmine #13426',
         'url' => 'https://redmine.pfsense.org/issues/13426', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '70bfd0fda18012a01547a4eeff17c22c0adf8503',
  'versions' => ['2.6.0', '22.05', '22.05.1'],
  'descr' => 'Fix sshguard login protection bypass from GUI authentication attempts with proxy headers',
  'links' => [
      [ 'text' => 'pfSense-SA-23_05.sshguard',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-23_05.sshguard', ],
      [ 'text' => 'Redmine #13574',
         'url' => 'https://redmine.pfsense.org/issues/13574', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'a3c1589086ea67d25a28ec14ab95d7fd9ab25fa2',
  'versions' => ['2.6.0', '22.01', '22.05'],
  'descr' => 'Fix for CRL expiration lifetime default and maximum values',
  'links' => [
      [ 'text' => 'Redmine #13424',
         'url' => 'https://redmine.pfsense.org/issues/13424', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '8ddf2b5a999772754080825f07acf9b6326f1f04',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Add user preference to choose password hash algorithm',
  'links' => [
      [ 'text' => 'Redmine #12855',
         'url' => 'https://redmine.pfsense.org/issues/12855', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '3b50f7656967fbb4daa869a7ae6d18bc5ab6eec3',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Add UPnP NAT Anchors to fix outbound NAT for multiple consoles.',
  'links' => [
      [ 'text' => 'Redmine #7727',
         'url' => 'https://redmine.pfsense.org/issues/7727#note-75', ],
      [ 'text' => 'Forum Thread',
         'url' => 'https://forum.netgate.com/topic/169837/upnp-fix-for-multiple-clients-consoles-playing-the-same-game', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '12827workaround',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Disable pf counter data preservation to temporarily work around latency when reloading large rulesets',
  'links' => [
      [ 'text' => 'Redmine #12827',
         'url' => 'https://redmine.pfsense.org/issues/12827', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'fd30b9eda98900eb5f1a0c37d600ef4deaf582ad',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Fix Captive Portal handling of non-TCP traffic after login',
  'links' => [
      [ 'text' => 'Redmine #12834',
         'url' => 'https://redmine.pfsense.org/issues/12834', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'bdffb77d1aa21770b23ef408ad9fba79d0825ec5',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Fix Dynamic DNS hang on Save and Force Update',
  'links' => [
      [ 'text' => 'Redmine #12870',
         'url' => 'https://redmine.pfsense.org/issues/12870', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '4612721800a1b25bb1fb2d4d7c4ceea6f44f208e',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Fix Namecheap Dynamic DNS response processing',
  'links' => [
      [ 'text' => 'Redmine #12816',
         'url' => 'https://redmine.pfsense.org/issues/12816', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'b5360f49fb3c1fdc36ebf13c20b68d4ff1e15fe6',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Fix Google Domains Dynamic DNS response processing',
  'links' => [
      [ 'text' => 'Redmine #12754',
         'url' => 'https://redmine.pfsense.org/issues/12754', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '3ade222beb2cae2c0681ed69d4e5a0c82c6303f9',
  'versions' => ['2.6.0', '22.01'],
  'descr' => 'Fix OpenVPN dashboard widget client termination',
  'links' => [
      [ 'text' => 'Redmine #12817',
         'url' => 'https://redmine.pfsense.org/issues/12817', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'b2fce958b8a2e80754b49eb3d17795757df6a76c',
  'versions' => ['22.01'],
  'descr' => 'Fix Netgate 5100 status LED',
  'links' => [
      [ 'text' => 'NG 7538' ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'c416f6fab10f149b15a352dfb609f86a98f6103d',
  'versions' => ['2.5.2'],
  'descr' => 'Additional fix for XSS in captive portal redirect URL handling',
  'links' => [
      [ 'text' => 'pfSense-SA-21_02.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-21_02.captiveportal.asc', ],
      [ 'text' => 'Redmine #11843',
         'url' => 'https://redmine.pfsense.org/issues/11843', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'bf21f67bbe2d1694ad1ad72728623dded9ace426',
  'versions' => ['2.5.2', '21.05.2'],
  'descr' => 'Fix file overwrite vulnerability in services_ntpd_gps.php',
  'links' => [
      [ 'text' => 'pfSense-SA-22_01.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-22_01.webgui.asc', ],
      [ 'text' => 'Redmine #12191',
         'url' => 'https://redmine.pfsense.org/issues/12191', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '72ea2b69cc111d4bc8ebf1ccf1e1529923c5b88a',
  'versions' => ['2.5.2', '21.05.2'],
  'descr' => 'Fix multiple vulnerabilities in diag_routes.php data collection',
  'links' => [
      [ 'text' => 'pfSense-SA-22_02.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-22_02.webgui.asc', ],
      [ 'text' => 'Redmine #12257',
         'url' => 'https://redmine.pfsense.org/issues/12257', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => '78ce96a9af3b2ab5159ef6623078bfc4b15f8a89',
  'versions' => ['2.5.2', '21.05.2'],
  'descr' => 'Fix OpenVPN form validation',
  'links' => [
      [ 'text' => 'pfSense-SA-22_03.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-22_03.webgui.asc', ],
      [ 'text' => 'Redmine #12677',
         'url' => 'https://redmine.pfsense.org/issues/12677', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'a9bdbd97984ff2ddefbceb2fe062fbe3a1c42d88',
  'versions' => ['2.5.2', '21.05.2'],
  'descr' => 'Fix potential XSS in pkg.php',
  'links' => [
      [ 'text' => 'pfSense-SA-22_04.webgui',
         'url' => 'https://docs.netgate.com/downloads/pfSense-SA-22_04.webgui.asc', ],
      [ 'text' => 'Redmine #12725',
         'url' => 'https://redmine.pfsense.org/issues/12725', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
],
[
  'uniqid' => 'afcc0e9c97c1993ae6b95f886665fcb4375d26c7',
  'versions' => ['2.5.2', '21.05.2'],
  'descr' => 'Disable pcscd service by default',
  'links' => [
      [ 'text' => 'Redmine #11933',
         'url' => 'https://redmine.pfsense.org/issues/11933', ],
  ],
  'basedir' => '/',
  'pathstrip' => 2,
  'ignorewhitespace' => true,
]
];
