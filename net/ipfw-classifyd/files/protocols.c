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
 * $Id: protocols.c 566 2008-08-01 17:13:16Z mtm $
 */

#include <sys/types.h>
#include <sys/queue.h>

#include <ctype.h>
#include <dirent.h>
#include <err.h>
#include <limits.h>
#include <syslog.h>
#include <regex.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "protocols.h"

static char	*get_token(FILE *, size_t *);
static int	hex2dec(char);
static int	parse_protocol_file(struct ic_protocols *, struct protocol *);
static char	*translate_re(char *, size_t *);

struct ic_protocols *
init_protocols(const char *dir)
{
	char path[LINE_MAX];
	char errmsg[255];
	struct ic_protocols *fp;
	struct protocol *p;
	struct dirent **nlp;
	int error, num;

	num = scandir(dir, &nlp, NULL, alphasort);
	if (num == -1)
		return (NULL);

	/*
	 * Directory is empty (only ./ and ../)
	 */
	if (num <= 2)
		return (NULL);

	fp = (struct ic_protocols *)malloc(sizeof(struct ic_protocols));
	if (fp == NULL)
		return (NULL);
	SLIST_INIT(&fp->fp_p);
	fp->fp_reflags = REG_EXTENDED | REG_ICASE | REG_NOSUB | REG_PEND;
	fp->fp_inuse = 0;

	for (num--; num > 0; num--) {
		if (nlp[num]->d_type != DT_REG)
			continue;

		p = (struct protocol *)calloc(1, sizeof(struct protocol));
		if (p == NULL) {
			fini_protocols(fp);
			return (NULL);
		}
		snprintf(path, LINE_MAX, "%s/%s", dir, nlp[num]->d_name);
		p->p_path = strdup(path);
		error = parse_protocol_file(fp, p);
		if (error != 0) {
			if (error == -2) /* Duplicate pattern */
				syslog(LOG_ERR, "Duplicate pattern for %s from file %s", p->p_name == NULL ? "unknown" : p->p_name, path);
			else
				syslog(LOG_ERR, "unable to parse %s", path);
			if (p->p_name != NULL)
				free(p->p_name);
			if (p->p_re != NULL)
				free(p->p_re);
			free(p->p_path);
			free(p);
		} else {
			/* For REG_PEND specify the end of the RE explicitly */
			p->p_preg.re_endp = &p->p_re[p->p_relen];
			error = regcomp(&p->p_preg, p->p_re, fp->fp_reflags);
			if (error != 0) {
				regerror(error, &p->p_preg, errmsg, sizeof(errmsg));
				syslog(LOG_ERR, "unable to compile %s: %s", p->p_name, errmsg);
				if (p->p_name != NULL)
					free(p->p_name);
				if (p->p_re != NULL)
					free(p->p_re);
				free(p->p_path);
				free(p);
			} else
				SLIST_INSERT_HEAD(&fp->fp_p, p, p_next);
		}
	}

	return (fp);
}

void
fini_protocols(struct ic_protocols *fp)
{
	struct protocol *p;

	while (!SLIST_EMPTY(&fp->fp_p)) {
		p = SLIST_FIRST(&fp->fp_p);
		SLIST_REMOVE_HEAD(&fp->fp_p, p_next);
		if (p->p_path != NULL)
			free(p->p_path);
		if (p->p_name != NULL)
			free(p->p_name);
		if (p->p_re != NULL)
			free(p->p_re);
		regfree(&p->p_preg);
		free(p);
	}
}

static int
parse_protocol_file(struct ic_protocols *fp, struct protocol *p)
{
	struct protocol *tmp;
	FILE   *f;

	f = fopen((const char *)p->p_path, "r");
	if (f == NULL)
		return (-1);
	p->p_name = get_token(f, NULL);
	if (p->p_name == NULL) {
		fclose(f);
		return (-1);
	}

	if (fp != NULL) {
		SLIST_FOREACH(tmp, &fp->fp_p, p_next) {
			if (!strcmp(tmp->p_name, p->p_name)) {
				fclose(f);
				return (-2);
			}
		}
	}
	/*
	 * The RE needs to be cooked a little before it is fit for
	 * consumption.
	 */
	p->p_re = get_token(f, &p->p_relen);
	if (p->p_re == NULL) {
		fclose(f);
		return (-1);
	}
	translate_re(p->p_re, &p->p_relen);

	fclose(f);
	return (0);
}

/*
 * Returns a token from a line as a string. It is the caller's responsibility
 * to free the memory used by the string.
 */
static char *
get_token(FILE *f, size_t *lenp)
{
	char	 *name;
	char	 *line;
	size_t	 len, skipped;
	uint32_t i, j;

	while (!feof(f)) {
		line = fgetln(f, &len);
		if (ferror(f))
			return (NULL);
		
		/*
		 * Skip whitespace; stop processing line on '#' or eol.
		 */
		skipped = 0;
		for (i = 0; i < len; i++) {
			if (!isspace(line[i]) || line[i] == '\n')
				break;
			skipped++;
		}
		if (line[i] == '#' || line[i] == '\n')
			continue;

		/*
		 * Some things to keep in mind when computing string length:
		 * 	o If the token is on the last line it may or may not
		 *	  have a terminating newline.
		 *	o The length obtained from fgetln(3) includes the
		 *	  newline, if there is one.
		 *	o When allocating memory we add an extra byte to
		 *	  hold the terminating NULL in case there is no
		 *	  newline character.
		 */
		j = 0;
		name = (char *)malloc(len - skipped + 1);
		if (name == NULL)
			return (NULL);
		while (line[i] != '\n' && i < len) {
			name[j] = line[i];
			j++;
			i++;
		}
		name[j] = '\0';
		if (lenp != NULL)
			*lenp = j;
		return (name);
	}

	return (NULL);
}

/*
 * Credits (with modifications): l7-filter Project <http://l7-filter.sf.net>
 */
static char *
translate_re(char *re, size_t *len)
{
	uint32_t i, j;

	/*
	 * Convert, "in place", hex numbers in the RE to decimal equivalent.
	 * If the result of the conversion is an RE control character, then
	 * prefix it with a '\'.
	 */
	for (i = 0, j = 0; i < *len; i++, j++) {
		if (((i + 3) < *len) && (re[i] == '\\') && (re[i + 1] == 'x') &&
		    isxdigit(re[i + 2]) && isxdigit(re[i + 3])) {
			re[j] = (hex2dec(re[i + 2]) * 16) + hex2dec(re[i + 3]);
			i+=3;
		} else
			re[j] = re[i];
	}
	if (i != 0) {
		re[j] = '\0';
		*len = j;
	}
	return (re);
}

/*
 * Credits (with modifications): l7-filter Project <http://l7-filter.sf.net>
 */
static int
hex2dec(char c) 
{
	switch (c) {
	case '0' ... '9':
		return (c - '0');
	case 'a' ... 'f':
		return (c - 'a' + 10);
	case 'A' ... 'F':
	default:
		return (c - 'A' + 10);
	}
}
