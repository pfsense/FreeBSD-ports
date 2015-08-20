#ifndef _NF9_H
# define _NF9_H

/*Egress support*/

# if defined(NF9) && defined(IPV6)

#  define NF9_IPV6

# endif /*defined(NF9) && defined(EGRESS)*/

/*Define counter*/
# if defined(NF9) && defined(ULL)

#  define NF9_COUNTER u_int64_t
#  define NF9_ULL

# else

#  define NF9_COUNTER u_int32_t

# endif /*defined(NF9) && defined(ULL)*/

/*Egress support*/
# if defined(NF9) && defined(EGRESS)

#  define NF9_EGRESS

# endif /*defined(NF9) && defined(EGRESS)*/

/*Counter size*/
# define NF9_COUNTER_SIZE sizeof(NF9_COUNTER)

/*
 * This is the Cisco Netflow(tm) version 9 packet format
 * Based on RFC 3954 : http://www.faqs.org/rfcs/rfc3954.html
 */

/*
 * RFC 3954:8  Field Type Definitions
 */
# define NF9_IN_BYTES 1 /*N (default is 4) Incoming counter with length N x 8 bits for number of bytes associated with an IP Flow. */
# define NF9_IN_PKTS 2 /*N (default is 4) Incoming counter with length N x 8 bits for the number of packets associated with an IP Flow */
# define NF9_FLOWS 3 /*N Number of flows that were aggregated; default for N is 4 */
# define NF9_PROTOCOL 4 /*1 IP protocol byte */
# define NF9_SRC_TOS 5 /*1 Type of Service byte setting when entering incoming interface */
# define NF9_TCP_FLAGS 6 /*1 Cumulative of all the TCP flags seen for this flow */
# define NF9_L4_SRC_PORT 7 /*2 TCP/UDP source port number e.g. FTP, Telnet, or equivalent */
# define NF9_IPV4_SRC_ADDR 8 /*4 IPv4 source address */
# define NF9_SRC_MASK 9 /*1 The number of contiguous bits in the source address subnet mask i.e. the submask in slash notation #define NF9_*/
# define NF9_INPUT_SNMP 10 /*N Input interface index; default for N is 2 but higher values could be used */
# define NF9_L4_DST_PORT 11 /*2 TCP/UDP destination port number e.g. FTP, Telnet, or equivalent */
# define NF9_IPV4_DST_ADDR 12 /*4 IPv4 destination address */
# define NF9_DST_MASK 13 /*1 The number of contiguous bits in the destination address subnet mask i.e. the submask in slash notation */
# define NF9_OUTPUT_SNMP 14 /*N Output interface index; default for N is 2 but higher values could be used */
# define NF9_IPV4_NEXT_HOP 15 /*4 IPv4 address of next-hop router */
# define NF9_RC_AS 16 /*N (default is 2) Source BGP autonomous system number where N could be 2 or 4 */
# define NF9_DST_AS 17 /*N (default is 2) Destination BGP autonomous system number where N could be 2 or 4 */
# define NF9_BGP_IPV4_NEXT_HOP 18 /*4 Next-hop router's IP in the BGP domain */
# define NF9_MUL_DST_PKTS 19 /*N (default is 4) IP multicast outgoing packet counter with length N x 8 bits for packets associated with the IP Flow */
# define NF9_MUL_DST_BYTES 20 /*N (default is 4) IP multicast outgoing byte counter with length N x 8 bits for bytes associated with the IP Flow */
# define NF9_LAST_SWITCHED 21 /*4 System uptime at which the last packet of this flow was switched */
# define NF9_FIRST_SWITCHED 22 /*4 System uptime at which the first packet of this flow was switched */
# define NF9_OUT_BYTES 23 /*N (default is 4) Outgoing counter with length N x 8 bits for the number of bytes associated with an IP Flow */
# define NF9_OUT_PKTS 24 /*N (default is 4) Outgoing counter with length N x 8 bits for the number of packets associated with an IP Flow. */
# define NF9_MIN_PKT_LNGTH 25 /*2 Minimum IP packet length on incoming packets of the flow */
# define NF9_MAX_PKT_LNGTH 26 /*2 Maximum IP packet length on incoming packets of the flow */
# define NF9_IPV6_SRC_ADDR 27 /*16 IPv6 Source Address */
# define NF9_IPV6_DST_ADDR 28 /*16 IPv6 Destination Address */
# define NF9_IPV6_SRC_MASK 29 /*1 Length of the IPv6 source mask in contiguous bits */
# define NF9_IPV6_DST_MASK 30 /*1 Length of the IPv6 destination mask in contiguous bits */
# define NF9_IPV6_FLOW_LABEL 31 /*3 IPv6 flow label as per RFC 2460 definition */
# define NF9_ICMP_TYPE 32 /*2 Internet Control Message Protocol (ICMP) packet type; reported as ((ICMP Type * 256) + ICMP code) */
# define NF9_MUL_IGMP_TYPE 33 /*1 Internet Group Management Protocol (IGMP) packet type */
# define NF9_SAMPLING_INTERVAL 34 /*4 When using sampled NetFlow, the rate at which packets are sampled e.g. a value of 100 indicates that one of every 100 packets is sampled */
# define NF9_SAMPLING_ALGORITHM 35 /*1 The type of algorithm used for sampled NetFlow: 0x01 Deterministic Sampling ,0x02 Random Sampling */
# define NF9_FLOW_ACTIVE_TIMEOUT 36 /*2 Timeout value (in seconds) for active flow entries in the NetFlow cache */
# define NF9_FLOW_INACTIVE_TIMEOUT 37 /*2 Timeout value (in seconds) for inactive flow entries in the NetFlow cache */
# define NF9_ENGINE_TYPE 38 /*1 Type of flow switching engine: RP = 0, VIP/Linecard = 1 */
# define NF9_ENGINE_ID 39 /*1 ID number of the flow switching engine */
# define NF9_TOTAL_BYTES_EXP 40 /*N (default is 4) Counter with length N x 8 bits for bytes for the number of bytes exported by the Observation Domain */
# define NF9_TOTAL_PKTS_EXP 41 /*N (default is 4) Counter with length N x 8 bits for bytes for the number of packets exported by the Observation Domain */
# define NF9_TOTAL_FLOWS_EXP 42 /*N (default is 4) Counter with length N x 8 bits for bytes for the number of flows exported by the Observation Domain */
/* Vendor Proprietary* 43  */
# define NF9_IPV4_SRC_PREFIX 44 /*4 IPv4 source address prefix (specific for Catalyst architecture) */
# define NF9_IPV4_DST_PREFIX 45 /*4 IPv4 destination address prefix (specific for Catalyst architecture) */
# define NF9_MPLS_TOP_LABEL_TYPE 46 /*1 MPLS Top Label Type: 0x00 UNKNOWN 0x01 TE-MIDPT 0x02 ATOM 0x03 VPN 0x04 BGP 0x05 LDP */
# define NF9_MPLS_TOP_LABEL_IP_ADDR 47 /*4 Forwarding Equivalent Class corresponding to the MPLS Top Label */
# define NF9_FLOW_SAMPLER_ID 48 /*1 Identifier shown in "show flow-sampler" */
# define NF9_FLOW_SAMPLER_MODE 49 /*1 The type of algorithm used for sampling data: 0x02 random sampling. Use in connection with FLOW_SAMPLER_MODE */
# define NF9_FLOW_SAMPLER_RANDOM_INTERVAL 50 /*4 Packet interval at which to sample. Use in connection with FLOW_SAMPLER_MODE */
/* Vendor Proprietary* 51     */
# define NF9_MIN_TTL 52 /*1 Minimum TTL on incoming packets of the flow */
# define NF9_MAX_TTL 53 /*1 Maximum TTL on incoming packets of the flow */
# define NF9_IPV4_IDENT 54 /*2 The IP v4 identification field */
# define NF9_ST_TOS 55 /*1 Type of Service byte setting when exiting outgoing interface */
# define NF9_IN_SRC_MAC 56 /*6 Incoming source MAC address */
# define NF9_OUT_DST_MAC 57 /*6 Outgoing destination MAC address */
# define NF9_SRC_VLAN 58 /*2 Virtual LAN identifier associated with ingress interface */
# define NF9_DST_VLAN 59 /*2 Virtual LAN identifier associated with egress interface */
# define NF9_IP_PROTOCOL_VERSION 60 /*1 Internet Protocol Version Set to 4 for IPv4, set to 6 for IPv6. If not present in the template, then version 4 is assumed. */
# define NF9_DIRECTION 61 /*1 Flow direction: 0 - ingress flow, 1 - egress flow */
# define NF9_IPV6_NEXT_HOP 62 /*16 IPv6 address of the next-hop router */
# define NF9_BPG_IPV6_NEXT_HOP /*63 16 Next-hop router in the BGP domain */
# define NF9_IPV6_OPTION_HEADERS /*64 4 Bit-encoded field identifying IPv6 option headers found in the flow */
/* Vendor Proprietary* 65     */
/* Vendor Proprietary* 66     */
/* Vendor Proprietary* 67     */
/* Vendor Proprietary* 68     */
/* Vendor Proprietary* 69     */
# define NF9_MPLS_LABEL_1 70 /*3 MPLS label at position 1 in the stack */
# define NF9_MPLS_LABEL_2 71 /*3 MPLS label at position 2 in the stack */
# define NF9_MPLS_LABEL_3 72 /*3 MPLS label at position 3 in the stack */
# define NF9_MPLS_LABEL_4 73 /*3 MPLS label at position 4 in the stack */
# define NF9_MPLS_LABEL_5 74 /*3 MPLS label at position 5 in the stack */
# define NF9_MPLS_LABEL_6 75 /*3 MPLS label at position 6 in the stack */
# define NF9_MPLS_LABEL_7 76 /*3 MPLS label at position 7 in the stack */
# define NF9_MPLS_LABEL_8 77 /*3 MPLS label at position 8 in the stack */
# define NF9_MPLS_LABEL_9 78 /*3 MPLS label at position 9 in the stack */
# define NF9_MPLS_LABEL_10 79 /*3 MPLS label at position 10 in the stack */
# define NF9_IN_DST_MAC 80 /*6 Incoming destination MAC address */
# define NF9_OUT_SRC_MAC 81 /*6 Outgoing source MAC address */
# define NF9_IF_NAME 82 /*N (default specified in template) Shortened interface name e.g. "FE1/0" */
# define NF9_IF_DESC 83 /*N (default specified in template) Full interface name e.g. "FastEthernet 1/0" */
# define NF9_SAMPLER_NAME 84 /*N (default specified in template) Name of the flow sampler */
# define NF9_IN_PERMANENT_BYTES 85 /*N (default is 4) Running byte counter for a permanent flow */
# define NF9_IN_PERMANENT_PKTS 86 /*N (default is 4) Running packet counter for a permanent flow */
/* Vendor Proprietary* 87    */

# define NF9_VERSION 9

# define NF9_IN 0

# define NF9_OUT 1

# define NF9_TEMPLATE_FLOWSET_ID 0

/*Generate template id from  {AF_INET, AF_INET6} and {NF9_IN, NF9_OUT}*/
# define TID(af,dir) ((af)<<8 | (dir) )

/*Converts {PF_IN , PF_OUT } -> {NF9_IN, NF9_OUT } */
# define CONVDIR(dir) ((dir == PF_IN) ? (NF9_IN): (NF9_OUT))

/*Converts  {PF_IN , PF_OUT } and {0,1} -> {NF9_IN, NF9_OUT }  */
# define INDEXDIR( dir , index)  ( index ?( NF9_IN == CONVDIR(dir)? NF9_OUT :NF9_IN  ):(CONVDIR(dir)))          

/*Converts tid -> {NF9_IN, NF9_OUT } */
# define TID2DIR(tid)( 0x0FF & (tid) )

# define NF9_FIELD_COUNT ( 12 )


/*
 * No messages from the routing socket should exceed this.
 */
# define RTBUFLEN   1024
/*
 * RFC 3954:5.1  Export Packet Header
 */
struct NF9_PACKET_HEADER
{
   u_int16_t version, count;
   u_int32_t uptime_ms, time_sec, export_sequence, source_id;
};

/*
 * Flowset Header
 */
struct NF9_FLOWSET_HEADER
{
   u_int16_t id , length;
};

/*
 * Data Record for Internet Protocol version 4 (IPv4)
 */

struct NF9_IPV4_DATA
{
   NF9_COUNTER octets , packets;
   u_int32_t flow_start, flow_finish;
   u_int32_t src_ip, dst_ip;
   u_int16_t src_port, dst_port;
   u_int16_t src_index, dst_index;
   u_int8_t protocol, direction;
} __attribute__ ((packed));

/*
 * Data Record for Internet Protocol version 6 (IPv6)
 */

struct NF9_IPV6_DATA
{
   NF9_COUNTER octets , packets;
   u_int32_t flow_start, flow_finish;
   u_int8_t src_ip[16];
   u_int8_t dst_ip[16];
   u_int16_t src_port, dst_port;
   u_int16_t src_index, dst_index;
   u_int8_t protocol, direction;

} __attribute__ ((packed));

/*Ethernet MTU is 1500, IPv6 header is 40 octets(no options) ,UDP header is 8 octets*/

#define NF9_MAX_PACKET_SIZE  1500 - 5 -8
/* min(ipv4) 324 ,  min ipv6(376) */


# define NF9_PACKET_HEADER_SIZE  sizeof( struct NF9_PACKET_HEADER)

# define NF9_FLOWSET_HEADER_SIZE sizeof(struct  NF9_FLOWSET_HEADER)

# define NF9_IPV4_DATA_SIZE    sizeof( struct  NF9_IPV4_DATA)

# define NF9_IPV6_DATA_SIZE     sizeof( struct  NF9_IPV6_DATA)

# ifdef NF9_IPV6
#  define NF9_MAX_STATE_REQUIREMENT (2 *(NF9_FLOWSET_HEADER_SIZE +  NF9_IPV6_DATA_SIZE + 3))
# else
#  define NF9_MAX_STATE_REQUIREMENT (2 * (NF9_FLOWSET_HEADER_SIZE +  NF9_IPV4_DATA_SIZE + 3))
# endif

/*
 * Ingress Template Record for Internet Protocol version 4 (IPv4)
 */

extern u_int16_t NF9_IPV4_INGRESS[];

/*
 * Egress Template Record for Internet Protocol version 4 (IPv4)
 */

extern u_int16_t NF9_IPV4_EGRESS[];

/*
 * Ingress Template Record for Internet Protocol version 6 (IPv6)
 *
 */

extern u_int16_t NF9_IPV6_INGRESS[];

/*
 * Egress Template Record for Internet Protocol version 6 (IPv6)
 */

extern u_int16_t NF9_IPV6_EGRESS[];

/*
 * Send states as NetFlow V9 datagrams
 */
int resolve_interface( struct pf_addr *host , int af);

int send_netflow_v9(const struct pfsync_state *, u_int , u_int *, int ,int ,struct timeval, int, int, int, int );

#endif /* _NF9_H */
