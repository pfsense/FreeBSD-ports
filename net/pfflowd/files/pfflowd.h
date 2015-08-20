/*
 * Copyright (c) 2003,2004 Damien Miller <djm@mindrot.org>
 *
 * Permission to use, copy, modify, and distribute this software for any
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

#ifndef _PFFLOWD_H
#define _PFFLOWD_H

#define PROGNAME                "pfflowd"
#define PROGVER                 "0.8"

#ifndef PRIVDROP_USER
# define PRIVDROP_USER          "nobody"
#endif   

#define PRIVDROP_CHROOT_DIR     "/var/empty"

#define DEFAULT_INTERFACE       "pfsync0"
#define LIBPCAP_SNAPLEN         2020    /* Default MTU */
 
/*
 * This is the Cisco Netflow(tm) version 1 packet format
 * Based on:
 * http://www.cisco.com/univercd/cc/td/doc/product/rtrmgmt/nfc/nfc_3_0/nfc_ug/nfc
form.htm
 */
struct NF1_HEADER {
        u_int16_t version, flows;
        u_int32_t uptime_ms, time_sec, time_nanosec;
};
struct NF1_FLOW {
        u_int32_t src_ip, dest_ip, nexthop_ip;
        u_int16_t if_index_in, if_index_out;
        u_int32_t flow_packets, flow_octets;
        u_int32_t flow_start, flow_finish;
        u_int16_t src_port, dest_port;
        u_int16_t pad1;
        u_int8_t protocol, tos, tcp_flags;
        u_int8_t pad2, pad3, pad4;
        u_int32_t reserved1;
#if 0
        u_int8_t reserved2; /* XXX: no longer used */
#endif
};
/* Maximum of 24 flows per packet */
#define NF1_MAXFLOWS            24
#define NF1_MAXPACKET_SIZE      (sizeof(struct NF1_HEADER) + \
                                 (NF1_MAXFLOWS * sizeof(struct NF1_FLOW)))

/*
 * This is the Cisco Netflow(tm) version 5 packet format
 * Based on:
 * http://www.cisco.com/univercd/cc/td/doc/product/rtrmgmt/nfc/nfc_3_0/nfc_ug/nfc
form.htm
 */
struct NF5_HEADER {
   u_int16_t version, flows;
   u_int32_t uptime_ms, time_sec, time_nanosec, flow_sequence;
   u_int8_t engine_type, engine_id, reserved1, reserved2;
};
struct NF5_FLOW {
   u_int32_t src_ip, dest_ip, nexthop_ip;
   u_int16_t if_index_in, if_index_out;
   u_int32_t flow_packets, flow_octets;
   u_int32_t flow_start, flow_finish;
   u_int16_t src_port, dest_port;
   u_int8_t pad1;
   u_int8_t tcp_flags, protocol, tos;
   u_int16_t src_as, dest_as;
   u_int8_t src_mask, dst_mask;
   u_int16_t pad2;
};
/* Maximum of 30 flows per packet */
#define NF5_MAXFLOWS    30
#define NF5_MAXPACKET_SIZE (sizeof(struct NF5_HEADER) + \
             (NF5_MAXFLOWS * sizeof(struct NF5_FLOW)))

#endif /* _PFFLOWD_H */
