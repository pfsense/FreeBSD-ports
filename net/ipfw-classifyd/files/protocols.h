/*-
 * Copyright (c) 2008 Michael Telahun Makonnen <mtm@FreeBSD.Org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 *
 * $Id: protocols.h 566 2008-08-01 17:13:16Z mtm $
 */

#include <sys/queue.h>

#include <regex.h>

struct protocol {
	char	*p_name;		/* name of protocol */
	char	*p_path;		/* path to protocol file */
	char	*p_re;			/* Regular Expression */
	size_t	p_relen;		/* Length of RE */
	regex_t p_preg;			/* Compiled form of RE */
	uint16_t p_fwrule;		/* Rule matching pkts should skip to */
	SLIST_ENTRY(protocol) p_next;	/* Next protocol */
};

SLIST_HEAD(phead, protocol);
struct ic_protocols {
	struct phead fp_p;		/* List of protocol structures */
	int  fp_reflags;		/* Flags passed to regcomp(3) */
	int  fp_inuse;			/* Count of protocols to classify */
};

void			fini_protocols(struct ic_protocols *);
struct ic_protocols	*init_protocols(const char *);
