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
runmode: autofp

# If set to auto, the variable is internally switched to 'router' in IPS 
# mode and 'sniffer-only' in IDS mode.
host-mode: auto

# Specifies the kind of flow load balancer used by the flow pinned autofp mode.
autofp-scheduler: active-packets

# Daemon working directory
daemon-directory: {$suricatacfgdir}

default-packet-size: 1514

# The default logging directory.
default-log-dir: {$suricatalogdir}suricata_{$if_real}{$suricata_uuid}

# Configure the type of alert (and other) logging.
outputs:

  # alert-pf blocking plugin
  - alert-pf:
      enabled: {$suri_blockoffenders}
      kill-state: {$suri_killstates}
      block-drops-only: {$suri_blockdrops}
      pass-list: {$suri_passlist}
      block-ip: {$suri_blockip}
      pf-table: {$suri_pf_table}

  # a line based alerts log similar to Snort's fast.log
  - fast:
      enabled: yes
      filename: alerts.log
      append: yes
      filetype: regular

  # alert output for use with Barnyard2
  - unified2-alert:
      enabled: {$barnyard2_enabled}
      filename: unified2.alert
      limit: {$unified2_log_limit}
      sensor-id: {$unified2_sensor_id}
      {$unified2_xff_output}

  - http-log:
      enabled: {$http_log_enabled}
      filename: http.log
      append: {$http_log_append}
      extended: {$http_log_extended}
      filetype: regular

  - pcap-log:
      enabled: {$pcap_log_enabled}
      filename: log.pcap
      limit: {$pcap_log_limit_size}mb
      max-files: {$pcap_log_max_files}
      mode: normal

  - tls-log:
      enabled: {$tls_log_enabled}
      filename: tls.log
      extended: {$tls_log_extended}

  - tls-store:
      enabled: {$tls_store_enabled}
      certs-log-dir: certs

  - stats:
      enabled: {$stats_log_enabled}
      filename: stats.log
      interval: {$stats_upd_interval}
      append: {$stats_log_append}

  - syslog:
      enabled: {$alert_syslog}
      identity: suricata
      facility: {$alert_syslog_facility}
      level: {$alert_syslog_priority}

  - drop:
      enabled: no
      filename: drop.log
      append: yes
      filetype: regular

  - file-store:
      enabled: {$file_store_enabled}
      log-dir: files
      force-magic: {$json_log_magic}
      {$json_log_hash}
      {$file_store_waldo}

  - file-log:
      enabled: {$json_log_enabled}
      filename: files-json.log
      append: {$json_log_append}
      filetype: regular
      force-magic: {$json_log_magic}
      {$json_log_hash}

  - dns-log:
      enabled: {$dns_log_enabled}
      filename: dns.log
      append: {$dns_log_append}
      filetype: regular

  - eve-log:
      enabled: {$enable_eve_log}
      type: {$eve_output_type}
      redis: {$eve_redis_output}
      filename: eve.json
      identity: "suricata"
      facility: {$eve_systemlog_facility}
      level: {$eve_systemlog_priority}
      types: {$eve_out_types}

# Magic file. The extension .mgc is added to the value here.
magic-file: /usr/share/misc/magic

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
spm-algo: auto

# Defrag settings:
defrag:
  memcap: {$frag_memcap}
  hash-size: {$frag_hash_size}
  trackers: {$ip_max_trackers}
  max-frags: {$ip_max_frags}
  prealloc: yes
  timeout: {$ip_frag_timeout}

# Flow settings:
flow:
  memcap: {$flow_memcap}
  hash-size: {$flow_hash_size}
  prealloc: {$flow_prealloc}
  emergency-recovery: {$flow_emerg_recovery}
  prune-flows: {$flow_prune}

# This option controls the use of vlan ids in the flow (and defrag)
# hashing.
vlan:
  use-for-tracking: true

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
  checksum-validation: no
  inline: auto
  prealloc-sessions: {$stream_prealloc_sessions}
  midstream: {$stream_enable_midstream}
  async-oneside: {$stream_enable_async}
  max-synack-queued: {$max_synack_queued}
  reassembly:
    memcap: {$reassembly_memcap}
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
  protocols:
    tls:
      enabled: {$tls_parser}
      detection-ports:
        dp: 443
      #no-reassemble: yes
    dcerpc:
      enabled: {$dcerpc_parser}
    ftp:
      enabled: {$ftp_parser}
    ssh:
      enabled: {$ssh_parser}
    smtp:
      enabled: {$smtp_parser}
    imap:
      enabled: {$imap_parser}
    msn:
      enabled: {$msn_parser}
    smb:
      enabled: {$smb_parser}
      detection-ports:
        dp: 139
    dns:
      global-memcap: {$dns_global_memcap}
      state-memcap: {$dns_state_memcap}
      request-flood: {$dns_request_flood_limit}

      tcp:
        enabled: {$dns_parser_tcp}
        detection-ports:
          dp: 53
      udp:
        enabled: {$dns_parser_udp}
        detection-ports:
          dp: 53
    http:
      enabled: {$http_parser}
      memcap: {$http_parser_memcap}

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
