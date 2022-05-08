
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
#include <sys/utsname.h>
#include <time.h>

#include "fastcgi.h"
#include "common.h"

static int fcgisock = -1;
static struct sockaddr_un sun;
static struct sockaddr_in sin;
static int keepalive = 0;

static int
build_nvpair(struct sbuf *sb, const char *key, const char *svalue)
{
	int lkey, lvalue;

	lkey = strlen(key);
	lvalue = strlen(svalue);

	if (lkey < 128)
		sbuf_putc(sb, lkey);
	else
		sbuf_printf(sb, "%c%c%c%c", (u_char)((lkey >> 24) | 0x80), (u_char)((lkey >> 16) & 0xFF), (u_char)((lkey >> 16) & 0xFF), (u_char)(lkey & 0xFF));

	if (lvalue < 128 || lvalue > 65535)
		sbuf_putc(sb, lvalue);
	else
		sbuf_printf(sb, "%c%c%c%c", (u_char)((lvalue >> 24) | 0x80), (u_char)((lvalue >> 16) & 0xFF), (u_char)((lvalue >> 16) & 0xFF), (u_char)(lvalue & 0xFF));

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
	char *buf = NULL;
	int len, err;

	memset(header, 0, sizeof(*header));
	if (recv(sockfd, header, sizeof(*header), 0) > 0) {
		len = (header->requestIdB1 << 8) + header->requestIdB0;
		if (len != 1) {
			return (NULL);
		}
		len = (header->contentLengthB1 << 8) + header->contentLengthB0;
		len += header->paddingLength;
		//printf("LEN: %d, %d, %d: %s\n", len, header->type, (header->requestIdB1 << 8) + header->requestIdB0, (char *)header);
		if (len > 0) {
			buf = calloc(1, len);
			if (buf == NULL)
				return (NULL);

			err = recv(sockfd, buf, len, 0);
			if (err < 0) {
				free(buf);
				return (NULL);
			}
		}
	}

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
	struct utsname uts;
	int ch, ispost = 0, len, result, end_header = 0;
	char *data = NULL, *script = NULL, *buf, *linebuf;
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

	uname(&uts);

	sbtmp2 = sbuf_new_auto();
	if (sbtmp2 == NULL)
		errx(-3, "Could not allocate memory\n");
	build_nvpair(sbtmp2, "GATEWAY_INTERFACE", "FastCGI/1.0");
	build_nvpair(sbtmp2, "REQUEST_METHOD", "GET");
	build_nvpair(sbtmp2, "NO_HEADERS", "1");
	sbtmp = sbuf_new_auto();
	sbuf_printf(sbtmp, "/%s", basename(script));
	sbuf_finish(sbtmp);
	build_nvpair(sbtmp2, "SCRIPT_FILENAME", script);
	build_nvpair(sbtmp2, "SCRIPT_NAME", sbuf_data(sbtmp));
	if (data == NULL) {
		build_nvpair(sbtmp2, "REQUEST_URI", sbuf_data(sbtmp));
	}
	build_nvpair(sbtmp2, "DOCUMENT_URI", sbuf_data(sbtmp));
	sbuf_delete(sbtmp);
	if (data) {
		build_nvpair(sbtmp2, "QUERY_STRING", data); 
		sbtmp = sbuf_new_auto();
		sbuf_printf(sbtmp, "/%s?%s", basename(script), data);
		sbuf_finish(sbtmp);
		build_nvpair(sbtmp2, "REQUEST_URI", sbuf_data(sbtmp));
		sbuf_delete(sbtmp);
	}
	sbuf_finish(sbtmp2);

	len = (3 * sizeof(FCGI_Header)) + sizeof(FCGI_BeginRequestRecord) + sbuf_len(sbtmp2);
	buf = calloc(1, len);
	if (buf == NULL)
		errx(-4, "Cannot allocate memory");

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
		result = write(fcgisock, buf, len);
		if (result < 0) {
			printf("Something wrong happened while sending request\n");
			free(buf);
			close(fcgisock);
			exit(-2);
		}
		len -= result;
		buf += result;
	}
	free(buf);

	do {
		buf = read_packet(&rHeader, fcgisock);
		if (buf == NULL) {
			printf("Something wrong happened while reading request\n");
			close(fcgisock);
			exit(-1);
		}
		switch (rHeader.type) {
		case FCGI_DATA:
		case FCGI_STDOUT:
		case FCGI_STDERR:
			if (end_header == 0) {
				while ((linebuf = strsep(&buf, "\n")) != NULL) {
					if (end_header == 0) {
						if (strlen(linebuf) == 1)
							end_header = 1;
						continue;
					}
					if (*linebuf == '#' &&
					    *(linebuf+1) == '!')
						continue;

					printf("%s", linebuf);
					if (buf != NULL)
						printf("\n");
					break;
				}
			} else if (buf != NULL)
				printf("%s", buf);
			free(buf);
			break;
		case FCGI_ABORT_REQUEST:
			printf("Request aborted\n");
			free(buf);
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
			free(buf);
			goto endprog;
			break;
		}
	} while (rHeader.type != FCGI_END_REQUEST);

endprog:
	close(fcgisock);

	return (0);
}
