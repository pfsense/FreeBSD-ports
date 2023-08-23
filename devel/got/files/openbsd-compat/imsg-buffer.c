/*	$OpenBSD: imsg-buffer.c,v 1.16 2023/06/19 17:19:50 claudio Exp $	*/

/*
 * Copyright (c) 2003, 2004 Henning Brauer <henning@openbsd.org>
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

#include <sys/types.h>
#include <sys/queue.h>
#include <sys/socket.h>
#include <sys/uio.h>

#include <limits.h>
#include <errno.h>
#include <endian.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include <openbsd-compat.h>

#include "imsg.h"

static int	ibuf_realloc(struct ibuf *, size_t);
static void	ibuf_enqueue(struct msgbuf *, struct ibuf *);
static void	ibuf_dequeue(struct msgbuf *, struct ibuf *);
static void	msgbuf_drain(struct msgbuf *, size_t);

struct ibuf *
ibuf_open(size_t len)
{
	struct ibuf	*buf;

	if (len == 0) {
		errno = EINVAL;
		return (NULL);
	}
	if ((buf = calloc(1, sizeof(struct ibuf))) == NULL)
		return (NULL);
	if ((buf->buf = calloc(len, 1)) == NULL) {
		free(buf);
		return (NULL);
	}
	buf->size = buf->max = len;
	buf->fd = -1;

	return (buf);
}

struct ibuf *
ibuf_dynamic(size_t len, size_t max)
{
	struct ibuf	*buf;

	if (max < len) {
		errno = EINVAL;
		return (NULL);
	}

	if ((buf = calloc(1, sizeof(struct ibuf))) == NULL)
		return (NULL);
	if (len > 0) {
		if ((buf->buf = calloc(len, 1)) == NULL) {
			free(buf);
			return (NULL);
		}
	}
	buf->size = len;
	buf->max = max;
	buf->fd = -1;

	return (buf);
}

static int
ibuf_realloc(struct ibuf *buf, size_t len)
{
	unsigned char	*b;

	/* on static buffers max is eq size and so the following fails */
	if (len > SIZE_MAX - buf->wpos || buf->wpos + len > buf->max) {
		errno = ERANGE;
		return (-1);
	}

	b = recallocarray(buf->buf, buf->size, buf->wpos + len, 1);
	if (b == NULL)
		return (-1);
	buf->buf = b;
	buf->size = buf->wpos + len;

	return (0);
}

void *
ibuf_reserve(struct ibuf *buf, size_t len)
{
	void	*b;

	if (len > SIZE_MAX - buf->wpos) {
		errno = ERANGE;
		return (NULL);
	}

	if (buf->wpos + len > buf->size)
		if (ibuf_realloc(buf, len) == -1)
			return (NULL);

	b = buf->buf + buf->wpos;
	buf->wpos += len;
	memset(b, 0, len);
	return (b);
}

int
ibuf_add(struct ibuf *buf, const void *data, size_t len)
{
	void *b;

	if ((b = ibuf_reserve(buf, len)) == NULL)
		return (-1);

	memcpy(b, data, len);
	return (0);
}

int
ibuf_add_buf(struct ibuf *buf, const struct ibuf *from)
{
	return ibuf_add(buf, from->buf, from->wpos);
}

int
ibuf_add_n8(struct ibuf *buf, uint64_t value)
{
	uint8_t v;

	if (value > UINT8_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = value;
	return ibuf_add(buf, &v, sizeof(v));
}

int
ibuf_add_n16(struct ibuf *buf, uint64_t value)
{
	uint16_t v;

	if (value > UINT16_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = htobe16(value);
	return ibuf_add(buf, &v, sizeof(v));
}

int
ibuf_add_n32(struct ibuf *buf, uint64_t value)
{
	uint32_t v;

	if (value > UINT32_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = htobe32(value);
	return ibuf_add(buf, &v, sizeof(v));
}

int
ibuf_add_n64(struct ibuf *buf, uint64_t value)
{
	value = htobe64(value);
	return ibuf_add(buf, &value, sizeof(value));
}

int
ibuf_add_zero(struct ibuf *buf, size_t len)
{
	void *b;

	if ((b = ibuf_reserve(buf, len)) == NULL)
		return (-1);
	return (0);
}

void *
ibuf_seek(struct ibuf *buf, size_t pos, size_t len)
{
	/* only allowed to seek in already written parts */
	if (len > SIZE_MAX - pos || pos + len > buf->wpos) {
		errno = ERANGE;
		return (NULL);
	}

	return (buf->buf + pos);
}

int
ibuf_set(struct ibuf *buf, size_t pos, const void *data, size_t len)
{
	void *b;

	if ((b = ibuf_seek(buf, pos, len)) == NULL)
		return (-1);

	memcpy(b, data, len);
	return (0);
}

int
ibuf_set_n8(struct ibuf *buf, size_t pos, uint64_t value)
{
	uint8_t v;

	if (value > UINT8_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = value;
	return (ibuf_set(buf, pos, &v, sizeof(v)));
}

int
ibuf_set_n16(struct ibuf *buf, size_t pos, uint64_t value)
{
	uint16_t v;

	if (value > UINT16_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = htobe16(value);
	return (ibuf_set(buf, pos, &v, sizeof(v)));
}

int
ibuf_set_n32(struct ibuf *buf, size_t pos, uint64_t value)
{
	uint32_t v;

	if (value > UINT32_MAX) {
		errno = EINVAL;
		return (-1);
	}
	v = htobe32(value);
	return (ibuf_set(buf, pos, &v, sizeof(v)));
}

int
ibuf_set_n64(struct ibuf *buf, size_t pos, uint64_t value)
{
	value = htobe64(value);
	return (ibuf_set(buf, pos, &value, sizeof(value)));
}

void *
ibuf_data(struct ibuf *buf)
{
	return (buf->buf);
}

size_t
ibuf_size(struct ibuf *buf)
{
	return (buf->wpos);
}

size_t
ibuf_left(struct ibuf *buf)
{
	return (buf->max - buf->wpos);
}

void
ibuf_close(struct msgbuf *msgbuf, struct ibuf *buf)
{
	ibuf_enqueue(msgbuf, buf);
}

void
ibuf_free(struct ibuf *buf)
{
	if (buf == NULL)
		return;
#ifdef NOTYET
	if (buf->fd != -1)
		close(buf->fd);
#endif
	freezero(buf->buf, buf->size);
	free(buf);
}

int
ibuf_fd_avail(struct ibuf *buf)
{
	return (buf->fd != -1);
}

int
ibuf_fd_get(struct ibuf *buf)
{
	int fd;

	fd = buf->fd;
#ifdef NOTYET
	buf->fd = -1;
#endif
	return (fd);
}

void
ibuf_fd_set(struct ibuf *buf, int fd)
{
	if (buf->fd != -1)
		close(buf->fd);
	buf->fd = fd;
}

int
ibuf_write(struct msgbuf *msgbuf)
{
	struct iovec	 iov[IOV_MAX];
	struct ibuf	*buf;
	unsigned int	 i = 0;
	ssize_t	n;

	memset(&iov, 0, sizeof(iov));
	TAILQ_FOREACH(buf, &msgbuf->bufs, entry) {
		if (i >= IOV_MAX)
			break;
		iov[i].iov_base = buf->buf + buf->rpos;
		iov[i].iov_len = buf->wpos - buf->rpos;
		i++;
	}

again:
	if ((n = writev(msgbuf->fd, iov, i)) == -1) {
		if (errno == EINTR)
			goto again;
		if (errno == ENOBUFS)
			errno = EAGAIN;
		return (-1);
	}

	if (n == 0) {			/* connection closed */
		errno = 0;
		return (0);
	}

	msgbuf_drain(msgbuf, n);

	return (1);
}

void
msgbuf_init(struct msgbuf *msgbuf)
{
	msgbuf->queued = 0;
	msgbuf->fd = -1;
	TAILQ_INIT(&msgbuf->bufs);
}

static void
msgbuf_drain(struct msgbuf *msgbuf, size_t n)
{
	struct ibuf	*buf, *next;

	for (buf = TAILQ_FIRST(&msgbuf->bufs); buf != NULL && n > 0;
	    buf = next) {
		next = TAILQ_NEXT(buf, entry);
		if (n >= buf->wpos - buf->rpos) {
			n -= buf->wpos - buf->rpos;
			ibuf_dequeue(msgbuf, buf);
		} else {
			buf->rpos += n;
			n = 0;
		}
	}
}

void
msgbuf_clear(struct msgbuf *msgbuf)
{
	struct ibuf	*buf;

	while ((buf = TAILQ_FIRST(&msgbuf->bufs)) != NULL)
		ibuf_dequeue(msgbuf, buf);
}

int
msgbuf_write(struct msgbuf *msgbuf)
{
	struct iovec	 iov[IOV_MAX];
	struct ibuf	*buf, *buf0 = NULL;
	unsigned int	 i = 0;
	ssize_t		 n;
	struct msghdr	 msg;
	struct cmsghdr	*cmsg;
	union {
		struct cmsghdr	hdr;
		char		buf[CMSG_SPACE(sizeof(int))];
	} cmsgbuf;

	memset(&iov, 0, sizeof(iov));
	memset(&msg, 0, sizeof(msg));
	memset(&cmsgbuf, 0, sizeof(cmsgbuf));
	TAILQ_FOREACH(buf, &msgbuf->bufs, entry) {
		if (i >= IOV_MAX)
			break;
		if (i > 0 && buf->fd != -1)
			break;
		iov[i].iov_base = buf->buf + buf->rpos;
		iov[i].iov_len = buf->wpos - buf->rpos;
		i++;
		if (buf->fd != -1)
			buf0 = buf;
	}

	msg.msg_iov = iov;
	msg.msg_iovlen = i;

	if (buf0 != NULL) {
		msg.msg_control = (caddr_t)&cmsgbuf.buf;
		msg.msg_controllen = sizeof(cmsgbuf.buf);
		cmsg = CMSG_FIRSTHDR(&msg);
		cmsg->cmsg_len = CMSG_LEN(sizeof(int));
		cmsg->cmsg_level = SOL_SOCKET;
		cmsg->cmsg_type = SCM_RIGHTS;
		*(int *)CMSG_DATA(cmsg) = buf0->fd;
	}

again:
	if ((n = sendmsg(msgbuf->fd, &msg, 0)) == -1) {
		if (errno == EINTR)
			goto again;
		if (errno == ENOBUFS)
			errno = EAGAIN;
		return (-1);
	}

	if (n == 0) {			/* connection closed */
		errno = 0;
		return (0);
	}

	/*
	 * assumption: fd got sent if sendmsg sent anything
	 * this works because fds are passed one at a time
	 */
	if (buf0 != NULL) {
		close(buf0->fd);
		buf0->fd = -1;
	}

	msgbuf_drain(msgbuf, n);

	return (1);
}

static void
ibuf_enqueue(struct msgbuf *msgbuf, struct ibuf *buf)
{
	TAILQ_INSERT_TAIL(&msgbuf->bufs, buf, entry);
	msgbuf->queued++;
}

static void
ibuf_dequeue(struct msgbuf *msgbuf, struct ibuf *buf)
{
	TAILQ_REMOVE(&msgbuf->bufs, buf, entry);

	if (buf->fd != -1) {
		close(buf->fd);
		buf->fd = -1;
	}

	msgbuf->queued--;
	ibuf_free(buf);
}
