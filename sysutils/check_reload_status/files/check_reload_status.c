/*
 * check_reload_status.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2024 Rubicon Communications, LLC (Netgate)
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

#include <sys/types.h>
#include <sys/event.h>
#include <sys/sbuf.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <sys/queue.h>

#include <ctype.h>

#include <stdio.h>
#include <errno.h>
#include <err.h>
#include <fcntl.h>
#include <stdlib.h>
#include <signal.h>
#include <syslog.h>
#include <unistd.h>
#include <strings.h>
#include <string.h>

#include <event.h>

#include "server.h"
#include "common.h"

#include <sys/utsname.h>

#include "fastcgi.h"

/*
 * Internal representation of a packet.
 */
struct runq {
	TAILQ_ENTRY(runq) rq_link;
	struct event ev;
	char   command[2048];
	char   params[256];
	int requestId;
	int aggregate;
	int dontexec;
	int socket;
	struct event socket_ev;
};
TAILQ_HEAD(runqueue, runq) cmds = TAILQ_HEAD_INITIALIZER(cmds);;

/* function definitions */
static void			handle_signal(int);
static void			handle_signal_act(int, siginfo_t *, void *);
static void			run_command(struct command *, char *);
static void			set_blockmode(int socket, int cmd);
struct command *	match_command(struct command *target, char *wordpassed);
struct command *	parse_command(int fd, int argc, char **argv);
static void			socket_read_command(int socket, short event, void *arg);
static void			show_command_list(int fd, const struct command *list);
static void			socket_accept_command(int socket, short event, void *arg);
static void			socket_close_command(int fd, struct event *ev);
static void			socket_read_fcgi(int, short, void *);
static void			fcgi_send_command(int, short, void *);
static int			fcgi_open_socket(struct runq *);

static pid_t ppid = -1;
static struct utsname uts;
static int keepalive = 0;
static const char *fcgipath = FCGI_SOCK_PATH;

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

static int
build_nvpair(struct sbuf *sb, int lkey, int lvalue, const char *key, const char *svalue)
{
        if (lkey < 128)
                sbuf_putc(sb, lkey);
        else
                sbuf_printf(sb, "%c%c%c%c", (u_char)((lkey >> 24) | 0x80), (u_char)((lkey >> 16) & 0xFF), (u_char)((lkey >> 8) & 0xFF), (u_char)(lkey & 0xFF));

        if (lvalue < 128 || lvalue > 65535)
                sbuf_putc(sb, lvalue);
        else
                sbuf_printf(sb, "%c%c%c%c", (u_char)((lvalue >> 24) | 0x80), (u_char)((lvalue >> 16) & 0xFF), (u_char)((lvalue >> 8) & 0xFF), (u_char)(lvalue & 0xFF));

        if (lkey > 0)
                sbuf_printf(sb, "%s", key);
        if (lvalue > 0)
                sbuf_printf(sb, "%s", svalue);

        return (0);
}

static int
fcgi_open_socket(struct runq *cmd)
{
        struct sockaddr_un sun;
	int fcgifd;

	fcgifd = socket(PF_UNIX, SOCK_STREAM, 0);
	if (fcgifd < 0) {
		syslog(LOG_ERR, "Could not socket\n");
		return (-1);
	}

	bzero(&sun, sizeof(sun));
	sun.sun_family = PF_UNIX;
        strlcpy(sun.sun_path, fcgipath, sizeof(sun.sun_path));
        if (connect(fcgifd, (struct sockaddr *)&sun, sizeof(sun)) < 0) {
                syslog(LOG_ERR, "Could not connect to %s\n", fcgipath);
                close(fcgifd);
                return (-1);
        }

        set_blockmode(fcgifd, O_NONBLOCK | FD_CLOEXEC);

	event_set(&cmd->socket_ev, fcgifd, EV_READ | EV_PERSIST, socket_read_fcgi, cmd);
	event_add(&cmd->socket_ev, NULL);

	return (fcgifd);
}

static void
show_command_list(int fd, const struct command *list)
{
        int     i;
	char	value[2048];

	if (list == NULL)
		return;

        for (i = 0; list[i].action != NULLOPT; i++) {
                switch (list[i].type) {
                case NON:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <cr>\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
                case COMPOUND:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
                case ADDRESS:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <address>\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
                case PREFIX:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <address>[/len]\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
		case INTEGER:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <number>\n", list[i].keyword);
                        write(fd, value, strlen(value));
			break;
                case IFNAME:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <interface>\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
                case STRING:
			bzero(value, sizeof(value));
			snprintf(value, sizeof(value), "\t%s <string>\n", list[i].keyword);
                        write(fd, value, strlen(value));
                        break;
                }
        }
}

struct command *
parse_command(int fd, int argc, char **argv)
{
	struct command	*start = first_level;
	struct command	*match = NULL;
	const char *errstring = "ERROR:\tvalid commands are:\n";

	while (argc >= 0) {
		match = match_command(start, *argv);
		if (match == NULL) {
			errstring = "ERROR:\tNo match found.\n";
			goto error3;
		}

		argc--;
		argv++;

		if (argc > 0 && match->next == NULL) {
			errstring = "ERROR:\textra arguments passed.\n";
			goto error3;
		}
		if (argc < 0 && match->type != NON) {
			if (match->next != NULL)
				start = match->next;
			errstring = "ERROR:\tincomplete command.\n";
			goto error3;
		}
		if (argc == 0 && *argv == NULL && match->type != NON) {
			if (match->next != NULL)
				start = match->next;
			errstring = "ERROR:\tincomplete command.\n";
			goto error3;
		}

		if ( match->next == NULL)
			break;

		start = match->next;	
	}

	return (match);
error3:
	write(fd, errstring, strlen(errstring));
	show_command_list(fd, start);
	return (NULL);
}

struct command *
match_command(struct command *target, char *wordpassed)
{
	int i;

	if (wordpassed == NULL)
		return NULL;

	for (i = 0; target[i].action != NULLOPT; i++) {
		if (strcmp(target[i].keyword, wordpassed) == 0)
			return &target[i];
	}

	return (NULL);
}

static void
handle_signal_act(int sig, siginfo_t *unused1 __unused, void *unused2 __unused)
{
	handle_signal(sig);
}

static void
handle_signal(int sig)
{
        switch(sig) {
        case SIGHUP:
        case SIGTERM:
#if 0
		if (child)
			exit(0);
#endif
                break;
        }
}

static void
fcgi_send_command(int fd __unused , short event __unused, void *arg)
{
	FCGI_BeginRequestRecord *bHeader;
        FCGI_Header *tmpl;
        struct sbuf sb;
	struct runq *cmd;
	struct timeval tv = { 8, 0 };
	static int requestId = 0;
	int len, result;
	char *p, sbuf[4096], buf[4096], *bufptr;

	cmd = arg;
	if (cmd == NULL)
		return;

	if (cmd->dontexec) {
		TAILQ_REMOVE(&cmds, cmd, rq_link);
		timeout_del(&cmd->ev);
		event_del(&cmd->socket_ev);
		if (cmd->socket)
			close(cmd->socket);
		free(cmd);
		return;
	}

	requestId++;
	cmd->requestId = requestId;

	memset(sbuf, 0, sizeof(sbuf));
	sbuf_new(&sb, sbuf, 4096, 0);
	/* TODO: Use hardcoded length instead of strlen allover later on */
	/* TODO: Remove some env variables since might not be needed at all!!! */
	build_nvpair(&sb, strlen("GATEWAY_INTERFACE"), strlen("FastCGI/1.0"), "GATEWAY_INTERFACE", "FastCGI/1.0");
	build_nvpair(&sb, strlen("REQUEST_METHOD"), strlen("GET"), "REQUEST_METHOD", "GET");
	build_nvpair(&sb, strlen("NO_HEADERS"), strlen("1"), "NO_HEADERS", "1");
	build_nvpair(&sb, strlen("SCRIPT_FILENAME"), strlen(cmd->command), "SCRIPT_FILENAME", cmd->command);
	p = strrchr(cmd->command, '/');
	build_nvpair(&sb, strlen("SCRIPT_NAME"), strlen(p), "SCRIPT_NAME", p);
	build_nvpair(&sb, strlen("DOCUMENT_URI"), strlen(p), "DOCUMENT_URI", p);
	if (!cmd->params[0])
		build_nvpair(&sb, strlen("REQUEST_URI"), strlen(p), "REQUEST_URI", p);
	else {
		build_nvpair(&sb, strlen("QUERY_STRING"), strlen(cmd->params), "QUERY_STRING", cmd->params);
		/* XXX: Hack in sight to avoid using another sbuf */
		/* + 2 is for the / and ? added chars */
		build_nvpair(&sb, strlen("REQUEST_URI"), strlen(p) + strlen(cmd->params) + 2, "REQUEST_URI", "/");
		sbuf_printf(&sb, "%s?%s", p, cmd->params);
	}
	sbuf_finish(&sb);

	len = (3 * sizeof(FCGI_Header)) + sizeof(FCGI_BeginRequestRecord) + sbuf_len(&sb);
#if 0
	if (len > 4096) {
		buf = calloc(1, len);
		if (buf == NULL) {
			tv.tv_sec = 1;
			timeout_add(&cmd->ev, &tv);
			sbuf_delete(sbtmp2);
			return;
		}
	} else
#endif
		memset(buf, 0, sizeof(buf));

	bufptr = buf;
	bHeader = (FCGI_BeginRequestRecord *)buf;
	prepare_packet(&bHeader->header, FCGI_BEGIN_REQUEST, sizeof(bHeader->body), requestId);
	bHeader->body.roleB0 = (unsigned char)FCGI_RESPONDER;
	bHeader->body.flags = (unsigned char)(keepalive ? FCGI_KEEP_CONN : 0);

	bufptr += sizeof(FCGI_BeginRequestRecord);
	tmpl = (FCGI_Header *)bufptr;
	prepare_packet(tmpl, FCGI_PARAMS, sbuf_len(&sb), requestId);

	bufptr += sizeof(FCGI_Header);
	memcpy(bufptr, sbuf_data(&sb), sbuf_len(&sb));

	bufptr += sbuf_len(&sb);
	tmpl = (FCGI_Header *)bufptr;
        prepare_packet(tmpl, FCGI_PARAMS, 0, requestId);

	bufptr += sizeof(FCGI_Header);
	tmpl = (FCGI_Header *)bufptr;

        prepare_packet(tmpl, FCGI_STDIN, 0, requestId);
	if (cmd->socket <= 0) {
		if ((cmd->socket = fcgi_open_socket(cmd)) < 0) {
			/* Reschedule */
			tv.tv_sec = 1;
			timeout_add(&cmd->ev, &tv);
			return;
		}
	}

	result = write(cmd->socket, buf, len);
	if (result < 0) {
		if (cmd->socket) {
			event_del(&cmd->socket_ev);
			close(cmd->socket);
		}
		cmd->socket = -1;
		syslog(LOG_ERR, "Something wrong happened while sending request: %m\n");
		timeout_add(&cmd->ev, &tv);
	} else if (cmd->aggregate > 0) {
		cmd->dontexec = 1;
		timeout_add(&cmd->ev, &tv);
	}
#if 0
	} else {
		TAILQ_REMOVE(&cmds, cmd, rq_link);
		timeout_del(&cmd->ev);
		if (cmd->socket)
			close(cmd->socket);
		free(cmd);
	}
#endif
}

static void
run_command_detailed(int fd __unused, short event __unused, void *arg) {
	struct runq *cmd;
	struct timeval tv = { 8, 0 };

	cmd = (struct runq *)arg;

	if (cmd == NULL)
		return;

	if (cmd->dontexec) {
		TAILQ_REMOVE(&cmds, cmd, rq_link);
		timeout_del(&cmd->ev);
		if (cmd->socket)
			close(cmd->socket);
		free(cmd);
		return;
	}


	switch (vfork()) {
	case -1:
		syslog(LOG_ERR, "Could not vfork() error %d - %s!!!", errno, strerror(errno));
		break;
	case 0:
		/* Possibly optimize by creating argument list and calling execve. */
		if (cmd->params[0])
			execl("/bin/sh", "/bin/sh", "-c", cmd->command, cmd->params, (char *)NULL);
		else
			execl("/bin/sh", "/bin/sh", "-c", cmd->command, (char *)NULL);
		syslog(LOG_ERR, "could not run: %s", cmd->command);
		_exit(127); /* Protect in case execl errors out */
		break;
	default:
		if (cmd->aggregate > 0) {
			cmd->dontexec = 1;
			timeout_add(&cmd->ev, &tv);
		} else {
			TAILQ_REMOVE(&cmds, cmd, rq_link);
			timeout_del(&cmd->ev);
			if (cmd->socket)
				close(cmd->socket);
			free(cmd);
		}
		break;
	}
}

static void
run_command(struct command *cmd, char *argv) {
	struct runq *command, *tmpcmd;
	struct timeval tv = { 1, 0 };
	int aggregate = 0;

	TAILQ_FOREACH(tmpcmd, &cmds, rq_link) {
		if (cmd->cmd.flags & AGGREGATE && !strcmp(tmpcmd->command, cmd->cmd.command)) {
			aggregate += tmpcmd->aggregate;
			if (aggregate > 1) {
				/* Rexec the command so the event is not lost. */
				if (tmpcmd->dontexec && aggregate < 3) {
					//syslog(LOG_ERR, "Rescheduling command %s", tmpcmd->command);
					syslog(LOG_NOTICE, cmd->cmd.syslog, argv);
					tmpcmd->dontexec = 0;
					tv.tv_sec = 5;
					timeout_del(&tmpcmd->ev);
					timeout_add(&tmpcmd->ev, &tv);
				}
				return;
			}
		}
	}

	command = calloc(1, sizeof(*command));
	if (command == NULL) {
		syslog(LOG_ERR, "Calloc failure for command %s", argv);
		return;
	}

	command->aggregate = aggregate + 1;
	//memcpy(command->command, cmd->cmd.command, sizeof(command->command));
	strlcpy(command->command, cmd->cmd.command, sizeof(command->command));
	if (cmd->cmd.params)
		snprintf(command->params, sizeof(command->params), cmd->cmd.params, argv);

	if (!(cmd->cmd.flags & AGGREGATE))
		command->aggregate = 0;

	switch (cmd->type) {
	case NON:
		syslog(LOG_NOTICE, "%s", cmd->cmd.syslog);
		break;
	case COMPOUND: /* XXX: Should never happen. */
		syslog(LOG_ERR, "trying to execute COMPOUND entry!!! Please report it.");
		free(command);
		return;
		/* NOTREACHED */
		break;
	case ADDRESS:
	case PREFIX:
	case INTEGER:
	case IFNAME:
	case STRING:
		if (argv != NULL)
			syslog(LOG_NOTICE, cmd->cmd.syslog, argv);
		else
			syslog(LOG_NOTICE, "%s", cmd->cmd.syslog);
		break;
	}

	TAILQ_INSERT_HEAD(&cmds, command, rq_link);

	if (cmd->cmd.flags & FCGICMD) {
		timeout_set(&command->ev, fcgi_send_command, command);
	} else {
		timeout_set(&command->ev, run_command_detailed, command);
	}
	timeout_add(&command->ev, &tv);

	return;
}

static void
socket_close_command(int fd, struct event *ev)
{
	event_del(ev);
	free(ev);
        close(fd);
}

static void
socket_read_fcgi(int fd, short event, void *arg)
{
	struct runq *tmpcmd = arg;
	FCGI_Header header;
	char buf[4096];
        int len, terr, success = 0;
	struct timeval tv = { 1, 0 };

	if (event == EV_TIMEOUT) {
		close(fd);
		syslog(LOG_ERR, "Rescheduling command %s due to timeout waiting for response", tmpcmd->command);
		tmpcmd->socket = fcgi_open_socket(tmpcmd);
		timeout_set(&tmpcmd->ev, fcgi_send_command, tmpcmd);
		timeout_add(&tmpcmd->ev, &tv);
		return;
	}

	len = 0;
	memset(&header, 0, sizeof(header));
	if (recv(fd, &header, sizeof(header), 0) > 0) {
		len = (header.requestIdB1 << 8) | header.requestIdB0;
		len = (header.contentLengthB1 << 8) | header.contentLengthB0;
		len += header.paddingLength;
		
		//syslog(LOG_ERR, "LEN: %d, %d, %d\n", len, header.type, (header.requestIdB1 << 8) | header.requestIdB0);
		if (len > 0) {
			memset(buf, 0, sizeof(buf));

			/* XXX: Should check if len > sizeof(buf)? */
			terr = recv(fd, buf, len, 0);
			if (terr < 0) {
				syslog(LOG_ERR, "Something happened during recv of data: %m");
				return;
			}
		}
	} else 
		return;

	switch (header.type) {
	case FCGI_DATA:
	case FCGI_STDOUT:
	case FCGI_STDERR:
		break;
	case FCGI_ABORT_REQUEST:
		syslog(LOG_ERR, "Request aborted\n");
		break;
	case FCGI_END_REQUEST:
		if (len >= (int)sizeof(FCGI_EndRequestBody)) {
			switch (((FCGI_EndRequestBody *)buf)->protocolStatus) {
			case FCGI_CANT_MPX_CONN:
				syslog(LOG_ERR, "The FCGI server cannot multiplex\n");
				success = 0;
				break;
			case FCGI_OVERLOADED:
				syslog(LOG_ERR, "The FCGI server is overloaded\n");
				success = 0;
				break;
			case FCGI_UNKNOWN_ROLE:
				syslog(LOG_ERR, "FCGI role is unknown\n");
				success = 0;
				break;
			case FCGI_REQUEST_COMPLETE:
				//syslog(LOG_ERR, "FCGI request completed");
				success = 1;
				break;
			}
			if (tmpcmd != NULL)  {
				if (success) {
					TAILQ_REMOVE(&cmds, tmpcmd, rq_link);
					timeout_del(&tmpcmd->ev);
					event_del(&tmpcmd->socket_ev);
					if (tmpcmd->socket)
						close(tmpcmd->socket);
					free(tmpcmd);
				} else {
				       /* Rexec the command so the event is not lost. */
					syslog(LOG_ERR, "Repeating event %s/%s because it was not triggered.", tmpcmd->command, tmpcmd->params);
					if (tmpcmd->dontexec)
						tmpcmd->dontexec = 0;
				}
			}
		}
		break;
	}
}

static void
socket_read_command(int fd, short event, void *arg)
{
	struct command *cmd;
	struct event *ev = arg;
	enum { bufsize = 2048 };
	char buf[bufsize];
	register int n;
	char **ap, *argv[bufsize], *p;
	int i, loop = 0;

	if (event == EV_TIMEOUT) {
		socket_close_command(fd, ev);
		return;
	}
		
tryagain:
	bzero(buf, sizeof(buf));
	if ((n = read (fd, buf, bufsize)) == -1) {
		if (errno != EWOULDBLOCK && errno != EINTR) {
			socket_close_command(fd, ev);
			return;
		} else {
			if (loop > 3) {
				socket_close_command(fd, ev);
				return;
			}
			loop++;
			goto tryagain;
		}
	} else if (n == 0) {
		socket_close_command(fd, ev);
		return;
	}
	
	if (buf[n - 1] == '\n')
		buf[n - 1] = '\0'; /* remove stray \n */
	if (n > 1 && buf[n - 2] == '\r') {
		n--;
		buf[n - 1] = '\0';
	}
	for (i = 0; i < n - 1; i++) {
		if (!isalpha(buf[i]) && !isspace(buf[i]) && !isdigit(buf[i]) && !ispunct(buf[i])) {
			write(fd, "ERROR:\tonly alphanumeric chars allowd", 37);
			socket_close_command(fd, ev);
			return;
		}
	}
	p = buf; /* blah, compiler workaround */

	i = 0;
	for (ap = argv; (*ap = strsep(&p, " \t")) != NULL;) {
		if (**ap != '\0') {
			if (++ap >= &argv[bufsize])
				break;
		}
		i++;
	}
	if (i > 0) {
		p = argv[i - 1];
		i = i - 1;
	} else {
		p = argv[i];
	}
	cmd = parse_command(fd, i, argv);
	if (cmd != NULL) {
		write(fd, "OK\n", 3);
		run_command(cmd, p);
	}

	return;
}

static void
socket_accept_command(int fd, __unused short event, __unused void *arg)
{
	struct sockaddr_un sun;
	struct timeval tv = { 10, 0 };
	struct event *ev;
	socklen_t len;
	int newfd;

	if ((newfd = accept(fd, (struct sockaddr *)&sun, &len)) < 0) {
		if (errno != EWOULDBLOCK && errno != EINTR)
			syslog(LOG_ERR, "problems on accept");
		return;
	}
	set_blockmode(newfd, O_NONBLOCK | FD_CLOEXEC);

	if ((ev = malloc(sizeof(*ev))) == NULL) {
		syslog(LOG_ERR, "Cannot allocate new struct event.");
		close(newfd);
		return;
	}

	event_set(ev, newfd, EV_READ | EV_PERSIST, socket_read_command, ev);
	event_add(ev, &tv);
}

static void
set_blockmode(int fd, int cmd)
{
        int     flags;

        if ((flags = fcntl(fd, F_GETFL, 0)) == -1)
                errx(errno, "fcntl F_GETFL");

	flags |= cmd;

        if ((flags = fcntl(fd, F_SETFL, flags)) == -1)
                errx(errno, "fcntl F_SETFL");
}

int
main(void)
{
	struct event ev;
	struct sockaddr_un sun;
	struct sigaction sa;
	mode_t *mset, mode;
	sigset_t set;
	int fd, errcode = 0;
	char *p;

	tzset();

	/* daemonize */
	if (daemon(0, 0) < 0) {
		syslog(LOG_ERR, "check_reload_status could not start.");
		errcode = 1;
		goto error;
	}

	syslog(LOG_NOTICE, "check_reload_status is starting.");

	uname(&uts);

	if ((p = getenv("fcgipath")) != NULL) {
		fcgipath = p;
		syslog(LOG_NOTICE, "fcgipath from environment %s", fcgipath);
	}

	sigemptyset(&set);
	sigfillset(&set);
	sigdelset(&set, SIGHUP);
	sigdelset(&set, SIGTERM);
	sigdelset(&set, SIGCHLD);
	sigprocmask(SIG_BLOCK, &set, NULL);
	signal(SIGCHLD, SIG_IGN);

	sa.sa_handler = handle_signal;
	sa.sa_sigaction = handle_signal_act;
        sa.sa_flags = SA_SIGINFO|SA_RESTART;
        sigemptyset(&sa.sa_mask);
        sigaction(SIGHUP, &sa, NULL);
	sigaction(SIGTERM, &sa, NULL);

	ppid = getpid();
	if (fork() == 0) {
		setproctitle("Monitoring daemon of check_reload_status");
		/* Prepare code to monitor the parent :) */
		struct kevent kev;
		int kq;

		while (1) {
			kq = kqueue();
			EV_SET(&kev, ppid, EVFILT_PROC, EV_ADD, NOTE_EXIT, 0, NULL);
			kevent(kq, &kev, 1, NULL, 0, NULL);
			switch (kevent(kq, NULL, 0, &kev, 1, NULL)) {
			case 1:
				syslog(LOG_ERR, "Reloading check_reload_status because it exited from an error!");
				execl("/usr/local/sbin/check_reload_status", "/usr/local/sbin/check_reload_status", (char *)NULL);
				_exit(127);
				syslog(LOG_ERR, "could not run check_reload_status again");
				/* NOTREACHED */
				break;
			default:
				/* XXX: Should report any event?! */
				break;
			}
			close(kq);
		}
		exit(2);
	}

	fd = socket(PF_UNIX, SOCK_STREAM, 0);
	if (fd < 0) {
		errcode = -1;
		printf("Could not socket\n");
		goto error;
	}

#if 0
	if (unlink(PATH) == -1) {
		errcode = -2;
		printf("Could not unlink\n");
		close(fd);
		goto error;
	}
#else
	unlink(PATH);
#endif

	bzero(&sun, sizeof(sun));
        sun.sun_family = PF_UNIX;
        strlcpy(sun.sun_path, PATH, sizeof(sun.sun_path));
	if (bind(fd, (struct sockaddr *)&sun, sizeof(sun)) < 0) {
		errcode = -2;
		printf("Could not bind\n");
		close(fd);
		goto error;
	}

	set_blockmode(fd, O_NONBLOCK | FD_CLOEXEC);

        if (listen(fd, 30) == -1) {
                printf("control_listen: listen");
		close(fd);
                return (-1);
        }

	/* 0666 */
	if ((mset = setmode("0666")) != NULL) {
		mode = getmode(mset, S_IRUSR|S_IWUSR | S_IRGRP|S_IWGRP | S_IROTH|S_IWOTH);
		chmod(PATH, mode);
		free(mset);
	}

	TAILQ_INIT(&cmds);

	event_init();
	event_set(&ev, fd, EV_READ | EV_PERSIST, socket_accept_command, &ev);
	event_add(&ev, NULL);
	event_dispatch();

	return (0);
error:
	syslog(LOG_NOTICE, "check_reload_status is stopping.");

	return (errcode);
}
