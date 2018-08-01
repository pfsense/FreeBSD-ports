<?php

// This is the template used to generate the snort.conf
// configuration file for the interface.  The contents of
// this file are written to the snort.conf file for
// the interface.  Key parameters are provided by the
// included string variables which are populated by the
// snort_generate_conf.php file.

$snort_conf_text = <<<EOD
# snort configuration file
# generated automatically by the pfSense subsystems do not modify manually

# Define Local Network #
ipvar HOME_NET [{$home_net}]
ipvar EXTERNAL_NET [{$external_net}]

# Define Rule Path #
var RULE_PATH {$snortcfgdir}/rules

# Define Servers #
{$ipvardef}

# Define Server Ports #
{$portvardef}

# Configure the snort decoder #
config checksum_mode: {$cksumcheck}
config snaplen: {$snaplen}
config disable_decode_alerts
config disable_tcpopt_experimental_alerts
config disable_tcpopt_obsolete_alerts
config disable_ttcp_alerts
config disable_tcpopt_alerts
config disable_ipopt_alerts
config disable_decode_drops

# Enable the GTP decoder #
config enable_gtp

# Configure PCRE match limitations
config pcre_match_limit: 3500
config pcre_match_limit_recursion: 1500

# Configure the detection engine #
config detection: {$cfg_detect_settings}
config event_queue: max_queue 8 log 5 order_events content_length

# Configure to show year in timestamps
config show_year

# Configure IPv6 address logging in unified2 extra data
config log_ipv6_extra_data

# Configure protocol aware flushing #
# For more information see README.stream5 #
{$paf_max_pdu_config}

# Configure dynamically loaded libraries
dynamicpreprocessor directory {$snort_dirs['dynamicpreprocessor']}
dynamicengine directory {$snort_dirs['dynamicengine']}
dynamicdetection directory {$snort_dirs['dynamicrules']}

# Inline packet normalization. For more information, see README.normalize
# Disabled since we do not use "inline" mode with pfSense
# preprocessor normalize_ip4
# preprocessor normalize_tcp: ips ecn stream
# preprocessor normalize_icmp4
# preprocessor normalize_ip6
# preprocessor normalize_icmp6

# Flow and stream #
{$frag3_global}

{$frag3_engine}

{$stream5_global}

{$stream5_tcp_engine}

{$stream5_udp_engine}

{$stream5_icmp_engine}

# HTTP Inspect #
{$http_inspect_global}

{$http_inspect_servers}
{$snort_preprocessors}
{$host_attrib_config}

# Snort Output Logs #
output alert_csv: alert timestamp,sig_generator,sig_id,sig_rev,msg,proto,src,srcport,dst,dstport,id,classification,priority {$alert_log_limit_size}
{$alertsystemlog_type}
{$snortunifiedlog_type}
{$spoink_type}
						
# Misc Includes #
{$snort_misc_include_rules}

{$suppress_file_name}

# Snort user pass through configuration
{$snort_config_pass_thru}

# Rules Selection #
{$selected_rules_sections}
EOD;

// End of snort.conf template code

?>

