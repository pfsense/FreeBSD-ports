<?php

// This is the template used to generate the suricata.yaml
// configuration file for the interface.  The contents of
// this file are written to the suricata.yaml file for
// the interface.  Key parameters are provided by the
// included string variables.

	$suricata_conf_text = <<<EOD
%YAML 1.1
---

max-pending-packets: {$max_pend_pkts}

# Runmode the engine should use.
runmode: {$runmode}

# If set to auto, the variable is internally switched to 'router' in IPS 
# mode and 'sniffer-only' in IDS mode.
host-mode: auto

# Specifies the kind of flow load balancer used by the flow pinned autofp mode.
autofp-scheduler: {$autofp_scheduler}

# Daemon working directory
daemon-directory: {$suricatacfgdir}

default-packet-size: 1514

# The default logging directory.
default-log-dir: {$suricatalogdir}suricata_{$if_real}{$suricata_uuid}

# global stats configuration
stats:
  enabled: {$stats_collection_enabled}
  interval: {$stats_upd_interval}
  decoder-events: true
  decoder-events-prefix: "decoder.event"
  stream-events: false

# Configure the type of alert (and other) logging.
outputs:

  # alert-pf custom blocking plugin for pfSense only
  - alert-pf:
      enabled: {$suri_blockoffenders}
      kill-state: {$suri_killstates}
      block-drops-only: {$suri_blockdrops}
      pass-list: {$suri_passlist}
      block-ip: {$suri_blockip}
      pf-table: {$suri_pf_table}
      passlist-debugging: {$suri_passlist_debugging}   # Do not enable debugging on production systems!

  # a line based alerts log similar to Snort's fast.log
  - fast:
      enabled: yes
      filename: alerts.log
      append: yes
      filetype: regular

  - http-log:
      enabled: {$http_log_enabled}
      filename: {$http_log_filename}
      filetype: {$http_log_filetype}
      append: {$http_log_append}
      extended: {$http_log_extended}
      filetype: regular

  # a PCAP format packet capture facility
  - pcap-log:
      enabled: {$pcap_log_enabled}
      filename: log.pcap
      dir: {$pcap_log_dir}/
      limit: {$pcap_log_limit_size}mb
      max-files: {$pcap_log_max_files}
      mode: normal
      compression: none
      ts-format: sec
      use-stream-depth: {$pcap_use_stream_depth}
      honor-pass-rules: {$pcap_honor_pass_rules}
      conditional: {$pcap_log_conditional}

  - tls-log:
      enabled: {$tls_log_enabled}
      filename: {$tls_log_filename}
      filetype: {$tls_log_filetype}
      append: {$tls_log_append}
      extended: {$tls_log_extended}
      session-resumption: {$tls_session_resumption}

  - tls-store:
      enabled: {$tls_store_enabled}
      certs-log-dir: {$tls_certs_dir}

  - stats:
      enabled: {$stats_log_enabled}
      filename: stats.log
      append: {$stats_log_append}
      totals: yes
      threads: no
      null-values: yes

  - syslog:
      enabled: {$alert_syslog}
      identity: suricata
      facility: {$alert_syslog_facility}
      level: {$alert_syslog_priority}

  - file-store:
      version: 2
      enabled: {$file_store_enabled}
      length: 0
      dir: {$file_store_logdir}

  - eve-log:
      enabled: {$enable_eve_log}
      filetype: {$eve_output_type}
      filename: {$eve_output_filename}
      ethernet: {$eve_ethernet_output}
      redis: {$eve_redis_output}
      identity: "suricata"
      facility: {$eve_systemlog_facility}
      level: {$eve_systemlog_priority}
      xff:
        enabled: {$eve_xff_enabled}
        mode: {$eve_xff_mode}
        deployment: {$eve_xff_deployment}
        header: {$eve_xff_header}
      types: {$eve_out_types}

  - eve-log:
      enabled: {$enable_telegraf_eve}
      filetype: unix_stream
      filename: {$telegraf_eve_sockname}
      types:
        - stats:
            threads: yes

# Magic file. The extension .mgc is added to the value here.
magic-file: /usr/share/misc/magic

# GeoLite2 IP geo-location database file path and filename.
geoip-database: /usr/local/share/suricata/GeoLite2/GeoLite2-Country.mmdb

# Specify a threshold config file
threshold-file: {$suricatacfgdir}/threshold.config

detect-engine:
  - profile: {$detect_eng_profile}
  - sgh-mpm-context: {$sgh_mpm_ctx}
  - inspection-recursion-limit: {$inspection_recursion_limit}
  - delayed-detect: {$delayed_detect}

# Suricata is multi-threaded. Here the threading can be influenced.
threading:
  set-cpu-affinity: no
  detect-thread-ratio: 1.0

# Luajit has a strange memory requirement, it's 'states' need to be in the
# first 2G of the process' memory.
#
# 'luajit.states' is used to control how many states are preallocated.
# State use: per detect script: 1 per detect thread. Per output script: 1 per
# script.
luajit:
  states: 128

# Multi pattern algorithm
# The default mpm-algo value of "auto" will use "hs" if Hyperscan is
# available, "ac" otherwise.
mpm-algo: {$mpm_algo}

# Single pattern algorithm
# The default of "auto" will use "hs" if available, otherwise "bm".
spm-algo: {$spm_algo}

# Defrag settings:
defrag:
  memcap: {$frag_memcap}
  memcap-policy: {$defrag_memcap_policy}
  hash-size: {$frag_hash_size}
  trackers: {$ip_max_trackers}
  max-frags: {$ip_max_frags}
  prealloc: yes
  timeout: {$ip_frag_timeout}

# Flow settings:
flow:
  memcap: {$flow_memcap}
  memcap-policy: {$flow_memcap_policy}
  hash-size: {$flow_hash_size}
  prealloc: {$flow_prealloc}
  emergency-recovery: {$flow_emerg_recovery}
  prune-flows: {$flow_prune}

# This option controls the use of vlan ids in the flow (and defrag)
# hashing.
vlan:
  use-for-tracking: true

# This option controls the use of livedev ids in the flow (and defrag)
# hashing. This is enabled by default and should be disabled if
# netmap is used with a host stack interface.
livedev:
  use-for-tracking: {$livedev_tracking}

# Specific timeouts for flows.
flow-timeouts:
  default:
    new: 30
    established: 300
    closed: 0
    emergency-new: 10
    emergency-established: 100
    emergency-closed: 0
  tcp:
    new: {$flow_tcp_new_timeout}
    established: {$flow_tcp_established_timeout}
    closed: {$flow_tcp_closed_timeout}
    emergency-new: {$flow_tcp_emerg_new_timeout}
    emergency-established: {$flow_tcp_emerg_established_timeout}
    emergency-closed: {$flow_tcp_emerg_closed_timeout}
  udp:
    new: {$flow_udp_new_timeout}
    established: {$flow_udp_established_timeout}
    emergency-new: {$flow_udp_emerg_new_timeout}
    emergency-established: {$flow_udp_emerg_established_timeout}
  icmp:
    new: {$flow_icmp_new_timeout}
    established: {$flow_icmp_established_timeout}
    emergency-new: {$flow_icmp_emerg_new_timeout}
    emergency-established: {$flow_icmp_emerg_established_timeout}

stream:
  memcap: {$stream_memcap}
  memcap-policy: {$stream_memcap_policy}
  checksum-validation: {$stream_checksum_validation}
  inline: auto
  prealloc-sessions: {$stream_prealloc_sessions}
  midstream: {$stream_enable_midstream}
  midstream-policy: {$midstream_policy}
  async-oneside: {$stream_enable_async}
  max-synack-queued: {$max_synack_queued}
  bypass: {$stream_bypass_enable}
  drop-invalid: {$stream_drop_invalid_enable}
  reassembly:
    memcap: {$reassembly_memcap}
    memcap-policy: {$reassembly_memcap_policy}
    depth: {$reassembly_depth}
    toserver-chunk-size: {$reassembly_to_server_chunk}
    toclient-chunk-size: {$reassembly_to_client_chunk}

# Host table is used by tagging and per host thresholding subsystems.
host:
  hash-size: {$host_hash_size}
  prealloc: {$host_prealloc}
  memcap: {$host_memcap}

# Host specific policies for defragmentation and TCP stream reassembly.
host-os-policy:
  {$host_os_policy}

# Logging configuration.  This is not about logging IDS alerts, but
# IDS output about what its doing, errors, etc.
logging:

  # This value is overriden by the SC_LOG_LEVEL env var.
  default-log-level: info
  default-log-format: "%t - <%d> -- "

  # Define your logging outputs.
  outputs:
  - console:
      enabled: yes
  - file:
      enabled: yes
      filename: {$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/suricata.log
  - syslog:
      enabled: {$suricata_use_syslog}
      facility: {$suricata_use_syslog_facility}
      level: {$suricata_use_syslog_priority}
      format: "[%i] <%d> -- "

# IPS Mode Configuration
{$suricata_ips_mode}

legacy:
  uricontent: enabled

default-rule-path: {$suricatacfgdir}/rules
rule-files:
 - {$rules_files}

classification-file: {$suricatacfgdir}/classification.config
reference-config-file: {$suricatacfgdir}/reference.config

# Holds variables that would be used by the engine.
vars:

  # Holds the address group vars that would be passed in a Signature.
  address-groups:
    HOME_NET: "[{$home_net}]"
    EXTERNAL_NET: "{$external_net}"
    {$addr_vars}

  # Holds the port group vars that would be passed in a Signature.
  port-groups:
    {$port_vars}

# Set the order of alerts based on actions
action-order:
  - pass
  - drop
  - reject
  - alert

{$iprep_config}

# Limit for the maximum number of asn1 frames to decode (default 256)
asn1-max-frames: {$asn1_max_frames}

engine-analysis:
  rules-fast-pattern: yes
  rules: yes

#recursion and match limits for PCRE where supported
pcre:
  match-limit: 3500
  match-limit-recursion: 1500

# Holds details on the app-layer. The protocols section details each protocol.
app-layer:
  error-policy: {$app_layer_error_policy}
  protocols:
    bittorrent-dht:
      enabled: {$bittorrent_parser}
    dcerpc:
      enabled: {$dcerpc_parser}
    dhcp:
      enabled: {$dhcp_parser}
    dnp3:
      enabled: yes
      detection-ports:
        dp: 20000
    dns:
      global-memcap: {$dns_global_memcap}
      state-memcap: {$dns_state_memcap}
      request-flood: {$dns_request_flood_limit}
      tcp:
        enabled: {$dns_parser_tcp}
        detection-ports:
          dp: {$dns_parser_tcp_port}
      udp:
        enabled: {$dns_parser_udp}
        detection-ports:
          dp: {$dns_parser_udp_port}
    enip:
      enabled: {$enip_parser}
    ftp:
      enabled: {$ftp_parser}
    ftp-data:
      enabled: {$ftp_data_parser}
    http:
      enabled: {$http_parser}
      memcap: {$http_parser_memcap}
    http2:
      enabled: {$http2_parser}
    ike:
      enabled: {$ikev2_parser}
    imap:
      enabled: {$imap_parser}
    krb5:
      enabled: {$krb5_parser}
    modbus:
      enabled: yes
      request-flood: 500
      detection-ports:
        dp: 502
      stream-depth: 0
    mqtt:
      enabled: {$mqtt_parser}
    msn:
      enabled: {$msn_parser}
    nfs:
      enabled: {$nfs_parser}
    ntp:
      enabled: {$ntp_parser}
    pgsql:
      enabled: {$pgsql_parser}
    quic:
      enabled: {$quic_parser}
    rdp:
      enabled: {$rdp_parser}
    rfb:
      enabled: {$rfb_parser}
      detection-ports:
        dp: 5900, 5901, 5902, 5903, 5904, 5905, 5906, 5907, 5908, 5909
    sip:
      enabled: {$sip_parser}
    smb:
      enabled: {$smb_parser}
      detection-ports:
        dp: 139, 445
    smtp:
      enabled: {$smtp_parser}
      mime:
        decode-mime: {$smtp_decode_mime}
        decode-base64: {$smtp_decode_base64}
        decode-quoted-printable: {$smtp_decode_quoted_printable}
        header-value-depth: 2000
        extract-urls: {$smtp_extract_urls}
        body-md5: {$smtp_body_md5}
      inspected-tracker:
        content-limit: 100000
        content-inspect-min-size: 32768
        content-inspect-window: 4096
    ssh:
      enabled: {$ssh_parser}
    telnet:
      enabled: {$telnet_parser}
    tftp:
      enabled: {$tftp_parser}
    tls:
      enabled: {$tls_parser}
      detection-ports:
        dp: {$tls_detect_port}
      ja3-fingerprints: {$tls_ja3}
      encrypt-handling: {$tls_encrypt_handling}

###########################################################################
# Configure libhtp.
libhtp:
   default-config:
     {$http_hosts_default_policy}

   {$http_hosts_policy}

coredump:
  max-dump: unlimited

# Suricata user pass through configuration
{$suricata_config_pass_thru}

EOD;

?>
