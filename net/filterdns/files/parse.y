/*
 * parse.y
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2022 Rubicon Communications, LLC (Netgate)
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

%{
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <ctype.h>
#include <err.h>
#include <errno.h>
#include <unistd.h>
#include <limits.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <syslog.h>

#include "filterdns.h"

struct file {
	FILE	*stream;
	char	*name;
	int	 lineno;
	int	 error;
};

static struct	 file *file;
int		 yyparse(void);
int		 yylex(void);
int		 yyerror(const char *, ...);
int		 kw_cmp(const void *, const void *);
int		 lookup(char *);
int		 lgetc(int);
int		 lungetc(int);
int		 findeol(void);

int      atoul(char *, u_long *);

static int errors = 0;

typedef struct {
	union {
		int	 number;
		char	*string;
	} v;
	int lineno;
} YYSTYPE;

%}

%token	IPFW PF PIPE ERROR CMD
%token	<v.string>	STRING
%token	<v.number>	NUMBER
%type	<v.number>	ftype
%type	<v.number>	pipe
%type	<v.string>	anchor command

%%

grammar		: /* empty */
		| grammar '\n'
		| grammar dnsrule '\n'
		| grammar error '\n' { errors++; }
		;

dnsrule		: ftype STRING STRING anchor pipe command {
			int eexist;
			struct action *act = NULL;
			struct thread_host *thr = NULL;

			if (($1 != IPFW_TYPE && $1 != PF_TYPE) || $2 == NULL) {
				yyerror("Wrong configuration parameters");
				YYERROR;
			}
			if ($1 != PF_TYPE && $4 != NULL) {
				yyerror("Anchors are only supported for pf");
				YYERROR;
			}
			act = action_add($1, $2, $3, $4, $5, $6, &eexist);
			free($2);
			if ($3)
				free($3);
			if ($6)
				free($6);
			if (eexist != 0) {
				yyerror("filterdns: duplicate configuration entry found");
				free($2);
				YYERROR;
			}
			if (act == NULL) {
				yyerror("filterdns: could not allocate memory");
				free($2);
				YYERROR;
			}
			if (thr == NULL)
				thr = host_add(act);
			if (thr == NULL) {
				yyerror("filterdns: could not allocate memory");
				YYERROR;
			}
		}
		| CMD STRING command {
			int eexist;
			struct thread_host *thr = NULL;
			struct action *act = NULL;

			if ($2 == NULL || $3 == NULL) {
				yyerror("Hostname and Command are mandatory on CMD type directive");
				YYERROR;
			}
			act = action_add(CMD_TYPE, $2, NULL, NULL, 0, $3, &eexist);
			free($2);
			free($3);
			if (eexist != 0) {
				yyerror("filterdns: duplicate configuration entry found");
				free($2);
				YYERROR;
			}
			if (act == NULL) {
				yyerror("filterdns: could not allocate memory");
				free($2);
				YYERROR;
			}
			if (thr == NULL)
				thr = host_add(act);
			if (thr == NULL) {
				yyerror("filterdns: could not allocate memory");
				YYERROR;
			}
		}
		;

anchor		: { }
		| STRING { $$ = $1; }
		;

ftype		: IPFW { $$ = IPFW_TYPE; }
		| PF { $$ = PF_TYPE; }
		;

command		: /* empty */ { $$ = NULL; }
		| STRING {$$ = $1; }
		;

pipe		: /* empty */ { $$ = 0; }
		| PIPE STRING {
			u_long  ulval;

                        if (atoul($2, &ulval) == -1) {
                                yyerror("%s is not a number", $2);
                                free($2);
                                YYERROR;
                        } else {
                                $$ = ulval;
                                free($2);
                        }
		}
		;

%%

struct keywords {
	const char	*k_name;
	int		 k_val;
};

int
yyerror(const char *fmt, ...)
{
	va_list	ap;

	file->error++;
	va_start(ap, fmt);
	fprintf(stderr, "%s:%d: ", file->name, yylval.lineno);
	vfprintf(stderr, fmt, ap);
	fprintf(stderr, "\n");
	va_end(ap);
	return (0);
}

int
kw_cmp(const void *k, const void *e)
{
	return (strcmp(k, ((const struct keywords *)e)->k_name));
}

int
lookup(char *s)
{
	/* this has to be sorted always */
	static const struct keywords keywords[] = {
		{"cmd",			CMD},
		{"ipfw",		IPFW},
		{"pf",			PF},
		{"pipe",		PIPE},
	};
	const struct keywords	*p;

	p = bsearch(s, keywords, sizeof(keywords)/sizeof(keywords[0]),
	    sizeof(keywords[0]), kw_cmp);

	if (p)
		return (p->k_val);
	else
		return (STRING);
}

#define MAXPUSHBACK	128

char	*parsebuf;
int	 parseindex;
char	 pushback_buffer[MAXPUSHBACK];
int	 pushback_index = 0;

int
lgetc(int quotec __unused)
{
	int		c, next;

	if (parsebuf) {
		/* Read character from the parsebuffer instead of input. */
		if (parseindex >= 0) {
			c = parsebuf[parseindex++];
			if (c != '\0')
				return (c);
			parsebuf = NULL;
		} else
			parseindex++;
	}

	if (pushback_index)
		return (pushback_buffer[--pushback_index]);

	while ((c = getc(file->stream)) == '\\') {
		next = getc(file->stream);
		if (next != '\n') {
			c = next;
			break;
		}
		yylval.lineno = file->lineno;
		file->lineno++;
	}

if (c == '\t' || c == ' ') {
		/* Compress blanks to a single space. */
		do {
			c = getc(file->stream);
		} while (c == '\t' || c == ' ');
		ungetc(c, file->stream);
		c = ' ';
	}

	return (c);
}

int
lungetc(int c)
{
	if (c == EOF)
		return (EOF);
	if (parsebuf) {
		parseindex--;
		if (parseindex >= 0)
			return (c);
	}
	if (pushback_index < MAXPUSHBACK-1)
		return (pushback_buffer[pushback_index++] = c);
	else
		return (EOF);
}

int
findeol(void)
{
	int	c;

	parsebuf = NULL;
	pushback_index = 0;

	/* skip to either EOF or the first real EOL */
	while (1) {
		c = lgetc(0);
		if (c == '\n') {
			file->lineno++;
			break;
		}
		if (c == EOF)
			break;
	}
	return (ERROR);
}

int
yylex(void)
{
	char	 buf[8096];
	char	*p;
	int	 quotec, next, c;
	int	 token;

	p = buf;
	while ((c = lgetc(0)) == ' ' || c == '\t')
		; /* nothing */

	yylval.lineno = file->lineno;
	if (c == '#')
		while ((c = lgetc(0)) != '\n' && c != EOF)
			; /* nothing */
	switch (c) {
	case '\'':
	case '"':
		quotec = c;
		while (1) {
			if ((c = lgetc(quotec)) == EOF)
				return (0);
			if (c == quotec) {
				*p = '\0';
				break;
			} else if (c == '\n') {
				file->lineno++;
				continue;
			} else if (c == '\\') {
				if ((next = lgetc(quotec)) == EOF)
					return (0);
				if (next == quotec || c == ' ' || c == '\t')
					c = next;
				else if (next == '\n')
					continue;
				else
					lungetc(next);
			}
			if (p + 1 >= buf + sizeof(buf) - 1) {
				yyerror("string too long");
				return (findeol());
			}
			*p++ = (char)c;
		}
		yylval.v.string = strdup(buf);
		if (yylval.v.string == NULL)
			err(1, "yylex: strdup");
		return (STRING);
	}

#define allowed_in_string(x) \
	(isalnum(x) || (ispunct(x) && x != '(' && x != ')' && \
	x != '{' && x != '}' && \
	x != '!' && x != '=' && x != '#' && \
	x != ','))

	if (isalnum(c) || c == ':' || c == '_') {
		do {
			*p++ = c;
			if ((unsigned)(p-buf) >= sizeof(buf)) {
				yyerror("string too long");
				return (findeol());
			}
		} while ((c = lgetc(0)) != EOF && (allowed_in_string(c)));
		lungetc(c);
		*p = '\0';
		if ((token = lookup(buf)) == STRING)
			if ((yylval.v.string = strdup(buf)) == NULL)
				err(1, "yylex: strdup");
		return (token);
	}
	if (c == '\n') {
		yylval.lineno = file->lineno;
		file->lineno++;
	}
	if (c == EOF)
		return (0);
	return (c);
}

int
parse_config(char *filename)
{
	int error = 0;

	if (filename == NULL)
		return (-1);
	if ((file = calloc(1, sizeof(struct file))) == NULL) {
		warnx("maaloc");
		return (-1);
	}
	if ((file->name = strdup(filename)) == NULL) {
		warnx("malloc strdup");
		free(file);
		return (-1);
	}
	if ((file->stream = fopen(file->name, "r")) == NULL) {
		warnx("open file");
		error = -1;
		goto exitnow;
	}
	file->lineno = 1;

	yyparse();
	errors = file->error;
	if (errors)
		error = -1;

exitnow:
	if (file->stream)
		fclose(file->stream);
	free(file->name);
	free(file);

	return (error);
}

int
atoul(char *s, u_long *ulvalp)
{
        u_long   ulval;
        char    *ep;

        errno = 0;
        ulval = strtoul(s, &ep, 0);
        if (s[0] == '\0' || *ep != '\0')
                return (-1);
        if (errno == ERANGE && ulval == ULONG_MAX)
                return (-1);
        *ulvalp = ulval;
        return (0);
}
