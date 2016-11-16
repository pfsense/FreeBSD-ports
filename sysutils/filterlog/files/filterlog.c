
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/sbuf.h>
#include <sys/file.h>

#include <net/if.h>
#include <pcap/pcap.h>

#include <net/pfvar.h>
#include <net/if_pflog.h>

#include <netinet/ip.h>

#include <stdlib.h>
#include <syslog.h>
#include <stdarg.h>
#include <time.h>
#include <string.h>
#include <unistd.h>

#include "common.h"

static pcap_t *tap = NULL;
static char *filterlog_pcap_file = NULL;
static char errbuf[PCAP_ERRBUF_SIZE];
static struct sbuf sbuf;
static u_char *sbuf_buf;
static char *pidfile;

static const struct tok pf_reasons[] = {
	{ 0,	"match" },
	{ 1,	"bad-offset" },
	{ 2,	"fragment" },
	{ 3,	"short" },
	{ 4,	"normalize" },
	{ 5,	"memory" },
	{ 6,	"bad-timestamp" },
	{ 7,	"congestion" },
	{ 8,	"ip-option" },
	{ 9,	"proto-cksum" },
	{ 10,	"state-mismatch" },
	{ 11,	"state-insert" },
	{ 12,	"state-limit" },
	{ 13,	"src-limit" },
	{ 14,	"synproxy" },
	{ 0,	NULL }
};

static const struct tok pf_actions[] = {
	{ PF_PASS,		"pass" },
	{ PF_DROP,		"block" },
	{ PF_SCRUB,		"scrub" },
	{ PF_NAT,		"nat" },
	{ PF_NONAT,		"nat" },
	{ PF_BINAT,		"binat" },
	{ PF_NOBINAT,		"binat" },
	{ PF_RDR,		"rdr" },
	{ PF_NORDR,		"rdr" },
	{ PF_SYNPROXY_DROP,	"synproxy-drop" },
	{ 0,			NULL }
};

static const struct tok pf_directions[] = {
	{ PF_INOUT,	"in/out" },
	{ PF_IN,	"in" },
	{ PF_OUT,	"out" },
	{ 0,		NULL }
};

const char *
code2str(const struct tok *trans, const char *unknown, int action)
{
	int i = 0;

	for (;;) {
		if (trans[i].descr == NULL)
			return unknown;

		if (action == trans[i].action)
			return (trans[i].descr);

		i++;
	}

	return (unknown);
}

static void
decode_packet(u_char *user __unused, const struct pcap_pkthdr *pkthdr, const u_char *packet)
{
	const struct pfloghdr *hdr;
	const struct ip *ip;
	u_int length = pkthdr->len;
	u_int hdrlen;
	u_int caplen = pkthdr->caplen;
	u_int32_t subrulenr;

	/* check length */
	if (caplen < sizeof(u_int8_t)) {
		sbuf_printf(&sbuf, "[|pflog]");
		goto printsbuf;
	}

#define MIN_PFLOG_HDRLEN	45
	hdr = (const struct pfloghdr *)packet;
	if (hdr->length < MIN_PFLOG_HDRLEN) {
		sbuf_printf(&sbuf, "[pflog: invalid header length!]");
		goto printsbuf;
	}
	hdrlen = BPF_WORDALIGN(hdr->length);

	if (caplen < hdrlen) {
		sbuf_printf(&sbuf, "[|pflog]");
		goto printsbuf;
	}

	/* print what we know */
	sbuf_printf(&sbuf, "%u,", EXTRACT_32BITS(&hdr->rulenr));
	subrulenr = EXTRACT_32BITS(&hdr->subrulenr);
	if (subrulenr == (u_int32_t)-1)
		sbuf_printf(&sbuf, ",,");
	else
		sbuf_printf(&sbuf, "%u,%s,", subrulenr, hdr->ruleset); 
	sbuf_printf(&sbuf, "%u,%s,", hdr->ridentifier, hdr->ifname);
	sbuf_printf(&sbuf, "%s,", code2str(pf_reasons, "unkn(%u)", hdr->reason));
	sbuf_printf(&sbuf, "%s,", code2str(pf_actions, "unkn(%u)", hdr->action));
	sbuf_printf(&sbuf, "%s,", code2str(pf_directions, "unkn(%u)", hdr->dir));

	/* skip to the real packet */
	length -= hdrlen;
	packet += hdrlen;
	ip = (const struct ip *)packet;

        if (length < 4) {
                sbuf_printf(&sbuf, "%d, IP(truncated-ip %d) ", IP_V(ip), length);
		goto printsbuf;
        }
        switch (IP_V(ip)) {
        case 4:
                ip_print(&sbuf, packet, length);
		break;
        case 6:
                ip6_print(&sbuf, packet, length);
		break;
        default:
                ip_print(&sbuf, packet, length);
                sbuf_printf(&sbuf, "%d", IP_V(ip));
                break;
        }

printsbuf:
	sbuf_finish(&sbuf);
	if (filterlog_pcap_file != NULL)
		printf("%s\n", sbuf_data(&sbuf));
	else
		syslog(LOG_INFO, "%s", sbuf_data(&sbuf));
	memset(sbuf_data(&sbuf), 0, sbuf_len(&sbuf));
	sbuf_clear(&sbuf);
	return;
}

int
main(int argc, char **argv)
{
	int perr, ch;
	char *interface;

	pidfile = NULL;
	interface = filterlog_pcap_file = NULL;
	tzset();

	while ((ch = getopt(argc, argv, "i:p:P:")) != -1) {
		switch (ch) {
		case 'i':
			interface = optarg;
			break;
		case 'p':
			pidfile = optarg;
			break;
		case 'P':
			filterlog_pcap_file = optarg;
			break;
		default:
			printf("Unknown option specified\n");
			return (-1);
		}
	}

	if (interface == NULL && filterlog_pcap_file == NULL) {
		printf("Should specify an interface or a pcap file\n");
		exit(-1);
	}

	closefrom(3);
	if (filterlog_pcap_file == NULL)
		daemon(0, 0);

	if (pidfile) {
		FILE *pidfd;

                /* write PID to file */
                pidfd = fopen(pidfile, "w");
                if (pidfd) {
                        while (flock(fileno(pidfd), LOCK_EX) != 0)
                                ;
                        fprintf(pidfd, "%d\n", getpid());
                        flock(fileno(pidfd), LOCK_UN);
                        fclose(pidfd);
                } else
                        syslog(LOG_WARNING, "could not open pid file");
        }

	do {
		sbuf_buf = calloc(1, 2048);
	} while (sbuf_buf == NULL);

	sbuf_new(&sbuf, sbuf_buf, 2048, SBUF_AUTOEXTEND);

	openlog("filterlog", LOG_NDELAY, LOG_LOCAL0);

	while (1) {
		if (tap != NULL)
			pcap_close(tap);

		if (filterlog_pcap_file != NULL)
			tap = pcap_open_offline(filterlog_pcap_file, errbuf);
		else
			tap = pcap_open_live(interface, MAXIMUM_SNAPLEN, 1, 1000, errbuf);
		if (tap == NULL) {
			syslog(LOG_ERR, "Failed to initialize: %s(%m)", errbuf);
			return (-1);
		}

		if (pcap_datalink(tap) != DLT_PFLOG) {
			syslog(LOG_ERR, "Invalid datalink type");
			pcap_close(tap);
			tap = NULL;
			return (-1);
		}

		perr = pcap_loop(tap, -1, decode_packet, NULL);
		if (perr == -1) {
			syslog(LOG_ERR, "An error occured while reading device %s: %m", interface);
		} else if (perr == -2) {
			pcap_close(tap);
			break;
		} else if (perr == 0) {
			pcap_close(tap);
			tap = NULL;
		}

		if (filterlog_pcap_file != NULL)
			break;
	}

	closelog();

	return (0);
}
