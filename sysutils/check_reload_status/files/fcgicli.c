
#include <sys/types.h>
#include <sys/sbuf.h>
#include <sys/socket.h>
#include <sys/un.h>

#include <netinet/in.h>
#include <arpa/inet.h>

#include <err.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <unistd.h>
#include <libgen.h>
#include <time.h>

#include "fastcgi.h"
#include "common.h"

#define FCGI_NV_SLEN (1<<7)
#define FCGI_NV_LLEN (1UL<<31)

static int fcgisock = -1;
static struct sockaddr_un sun;
static struct sockaddr_in sin;
static int keepalive = 0;

static int
build_nvpair(struct sbuf *sb, const char *key, const char *svalue)
{
	uint32_t lkey, lvalue, ntmp;

	lkey = strlen(key);
	lvalue = strlen(svalue);

	/* FastCGI nvpair key/value cannot encode a length in more than 31 bits */
	if (!(lkey < FCGI_NV_LLEN && lvalue < FCGI_NV_LLEN)) {
		return (-1);
	}

	/* Encode name and value lengths in single or four byte fields */
	if (lkey < FCGI_NV_SLEN) {
		sbuf_putc(sb, lkey);
	} else {
		ntmp = htonl(FCGI_NV_LLEN | lkey);
		sbuf_bcat(sb, &ntmp, sizeof(ntmp));
	}
	if (lvalue < FCGI_NV_SLEN) {
		sbuf_putc(sb, lvalue);
	} else {
		ntmp = htonl(FCGI_NV_LLEN | lvalue);
		sbuf_bcat(sb, &ntmp, sizeof(ntmp));
	}

	if (lkey > 0)
		sbuf_printf(sb, "%s", key);
	if (lvalue > 0)
		sbuf_printf(sb, "%s", svalue);

	return (0);
}

static int
prepare_packet(FCGI_Header *header, int type, int lcontent, int requestId)
{
	header->version = (unsigned char)FCGI_VERSION_1;
	header->type = (unsigned char)type;
	header->requestIdB1 = (unsigned char)((requestId >> 8) & 0xFF);
	header->requestIdB0 = (unsigned char)(requestId & 0xFF);
	header->contentLengthB1 = (unsigned char)((lcontent >> 8) & 0xFF);
	header->contentLengthB0 = (unsigned char)(lcontent & 0xFF);

	return (0);
}

static char *
read_packet(FCGI_Header *header, int sockfd)
{
	uint16_t req = 0;
	uint16_t len = 0;
	ssize_t read = 0;

	char *buf = NULL;

	/* Read the header */
	len = (ssize_t)sizeof(*header);

	while (read < len) {
		ssize_t r_read = recv(sockfd, header + read, len - read, 0);
		if (r_read >= 0) {
			read += r_read;
		} else if (errno != EINTR){
			printf("Failed to read %zd header bytes: %s\n",
			    (ssize_t)len - read, strerror(errno));
			goto out;
		}
	}

	/* We only manage one req per fcgicli proc */
	req = (header->requestIdB1 << 8) + header->requestIdB0;
	if (req != 1) {
		printf("Invalid requestId %u\n", req);
		goto out;
	}

	len = (header->contentLengthB1 << 8) + header->contentLengthB0;
	len += header->paddingLength;
	buf = calloc(sizeof(*buf), len+1);
	if (buf == NULL) {
		printf("Could not allocate buffer of size %zu: %s\n",
		    (size_t)len, strerror(errno));
		goto out;
	}

	/* Read the content of the packet */
	read = 0;
	while (read < len) {
		ssize_t r_read = recv(sockfd, buf + read, len - read, 0);
		if (r_read >= 0) {
			read += r_read;
		} else if (errno != EINTR){
			printf("Failed to read %zd payload bytes: %s\n",
			    (ssize_t)len - read, strerror(errno));
			free(buf);
			buf = NULL;
			break;
		}
	}
out:
	return (buf);
}

static void
usage()
{
	printf("Usage: fcgicli [-d key=value] -f phpfiletocall -s phpfcgisocket -o [POST|GET]\n");
	exit(-10);
}

int
main(int argc, char **argv)
{
	FCGI_BeginRequestRecord *bHeader;
	FCGI_Header *tmpl, rHeader;
	struct sbuf *sbtmp2, *sbtmp;
	int ch, ispost = 0, len, result, end_header = 0;
	char *data = NULL, *script = NULL, *buf, *w_buf, *linebuf, *n_linebuf;
	const char *socketpath;

	tzset();

	socketpath = FCGI_SOCK_PATH;

	while ((ch = getopt(argc, argv, "d:f:ks:o:")) != -1) {
		switch (ch) {
		case 'd':
			data = optarg;
			break;
		case 'f':
			script = optarg;
			break;
		case 'k':
			keepalive = 1;
			break;
		case 's':
			socketpath = optarg;
			break;
		case 'o':
			if (!strcasecmp(optarg, "POST")) {
				ispost = 1;
				printf("POST mode is not yet implemented\n");
				exit(-2);
			} else if (!strcasecmp(optarg, "GET")) {
				ispost = 0;
			} else {
				usage();
			}

			break;
		}
	}
	argc -= optind;
        argv += optind;

	if (socketpath == NULL) {
		printf("-s option is mandatory\n");
		usage();
	}
	if (script == NULL) {
		printf("-f option is mandatory\n");
		usage();
	}
	if (data != NULL && ispost) {
		printf("-d option is useful only with POST operation\n");
		usage();
	}

	if (strstr(socketpath, "/")) {
		fcgisock = socket(PF_UNIX, SOCK_STREAM, 0);
		if (fcgisock < 0)
			err(-2, "could not create socket.");

		bzero(&sun, sizeof(sun));
		sun.sun_family = AF_LOCAL;
		strlcpy(sun.sun_path, socketpath, sizeof(sun.sun_path));
		len = sizeof(sun);

		//alarm(3); /* Wait 3 seconds to complete a connect. More than enough?! */
		if (connect(fcgisock, (struct sockaddr *)&sun, len) < 0)
			errx(errno, "Could not connect to server(%s).", socketpath);
	} else {
		const char *host;
		char *port;
		if (!(port = strstr(socketpath, ":")))
			errx(-1, "Need the port specified as host:port");

		*port++ = '\0';
		host = socketpath;

		fcgisock = socket(PF_INET, SOCK_STREAM, 0);
		if (fcgisock < 0)
			err(-2, "could not create socket.");

		bzero(&sin, sizeof(sin));
		sin.sin_family = AF_INET;
		inet_pton(AF_INET, host, &sin.sin_addr); 
		sin.sin_port = htons(atoi(port));
		len = sizeof(sin);

		//alarm(3); /* Wait 3 seconds to complete a connect. More than enough?! */
		if (connect(fcgisock, (struct sockaddr *)&sin, len) < 0)
			errx(errno, "Could not connect to server(%s:%s).", host, port);
	}

	sbtmp2 = sbuf_new_auto();
	if (sbtmp2 == NULL)
		errx(-3, "Could not allocate memory\n");
	build_nvpair(sbtmp2, "GATEWAY_INTERFACE", "FastCGI/1.0");
	build_nvpair(sbtmp2, "REQUEST_METHOD", "GET");
	build_nvpair(sbtmp2, "NO_HEADERS", "1");
	sbtmp = sbuf_new_auto();
	sbuf_printf(sbtmp, "/%s", basename(script));
	sbuf_finish(sbtmp);
	if (build_nvpair(sbtmp2, "SCRIPT_FILENAME", script) < 0) {
		errx(-5, "SCRIPT_FILENAME is too large\n");
	}
	if (build_nvpair(sbtmp2, "SCRIPT_NAME", sbuf_data(sbtmp)) < 0) {
		errx(-5, "SCRIPT_NAME is too large\n");
	}
	if (data == NULL) {
		if (build_nvpair(sbtmp2, "REQUEST_URI", sbuf_data(sbtmp)) < 0) {
			errx(-5, "REQUEST_URI is too large\n");
		}
	}
	if (build_nvpair(sbtmp2, "DOCUMENT_URI", sbuf_data(sbtmp)) < 0) {
		errx(-5, "DOCUMENT_URI is too large\n");
	}
	sbuf_delete(sbtmp);

	if (data) {
		if (build_nvpair(sbtmp2, "QUERY_STRING", data) < 0) {
			errx(-5, "QUERY_STRING is too large\n");
		}
		sbtmp = sbuf_new_auto();
		sbuf_printf(sbtmp, "/%s?%s", basename(script), data);
		sbuf_finish(sbtmp);
		if (build_nvpair(sbtmp2, "REQUEST_URI", sbuf_data(sbtmp)) < 0) {
			errx(-5, "REQUEST_URI is too large\n");
		}
		sbuf_delete(sbtmp);
	}
	sbuf_finish(sbtmp2);

	len = (3 * sizeof(FCGI_Header)) + sizeof(FCGI_BeginRequestRecord) + sbuf_len(sbtmp2);
	buf = calloc(1, len);
	if (buf == NULL)
		errx(-4, "Cannot allocate memory");
	w_buf = buf;
	bHeader = (FCGI_BeginRequestRecord *)buf;
	prepare_packet(&bHeader->header, FCGI_BEGIN_REQUEST, sizeof(bHeader->body), 1);
	bHeader->body.roleB0 = (unsigned char)FCGI_RESPONDER;
	bHeader->body.flags = (unsigned char)(keepalive ? FCGI_KEEP_CONN : 0);
	bHeader++;
	tmpl = (FCGI_Header *)bHeader;
	prepare_packet(tmpl, FCGI_PARAMS, sbuf_len(sbtmp2), 1);
	tmpl++;
	memcpy((char *)tmpl, sbuf_data(sbtmp2), sbuf_len(sbtmp2));
	tmpl = (FCGI_Header *)(((char *)tmpl) + sbuf_len(sbtmp2));
	sbuf_delete(sbtmp2);
	prepare_packet(tmpl, FCGI_PARAMS, 0, 1);
	tmpl++;
	prepare_packet(tmpl, FCGI_STDIN, 0, 1);
	while (len > 0) {
		result = write(fcgisock, w_buf, len);
		if (result < 0) {
			printf("Something wrong happened while sending request\n");
			free(buf);
			close(fcgisock);
			exit(-2);
		}
		len -= result;
		w_buf += result;
	}

	do {
		free(buf);
		buf = read_packet(&rHeader, fcgisock);
		if (buf == NULL) {
			close(fcgisock);
			exit(-1);
		}
		switch (rHeader.type) {
		case FCGI_DATA:
		case FCGI_STDOUT:
		case FCGI_STDERR:
			if (end_header == 0) {
				n_linebuf = buf;
				while ((linebuf = strsep(&n_linebuf, "\n")) != NULL) {
					if (end_header == 0) {
						if (strlen(linebuf) == 1)
							end_header = 1;
						continue;
					}
					if (*linebuf == '#' &&
					    *(linebuf+1) == '!')
						continue;

					printf("%s", linebuf);
					if (n_linebuf != NULL)
						printf("\n");
					break;
				}
			} else {
				printf("%s", buf);
			}
			break;
		case FCGI_ABORT_REQUEST:
			printf("Request aborted\n");
			goto endprog;
			break;
		case FCGI_END_REQUEST:
			switch (((FCGI_EndRequestBody *)buf)->protocolStatus) {
			case FCGI_CANT_MPX_CONN:
				printf("The FCGI server cannot multiplex\n");
				break;
			case FCGI_OVERLOADED:
				printf("The FCGI server is overloaded\n");
				break;
			case FCGI_UNKNOWN_ROLE:
				printf("FCGI role is unknown\n");
				break;
			case FCGI_REQUEST_COMPLETE:
				break;
			}
			goto endprog;
			break;
		default:
			; /*nop*/
		}
	} while (rHeader.type != FCGI_END_REQUEST);

endprog:
	free(buf);
	close(fcgisock);

	return (0);
}
