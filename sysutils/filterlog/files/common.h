

#ifndef _FILTER_LOG_COMMON_H_
#define _FILTER_LOG_COMMON_H_

#include <sys/sbuf.h>

#define MAXIMUM_SNAPLEN	65535

#ifndef IP_V
# define        IP_V(x)         (x)->ip_v
#endif
#ifndef IP_HL
# define        IP_HL(x)        (x)->ip_hl
#endif

struct tok {
        int action;
        const char *descr;
};

#include "ipproto.h"

typedef struct {
	u_int16_t	val;
} __attribute__((packed)) unaligned_u_int16_t;

typedef struct {
        u_int32_t       val;
} __attribute__((packed)) unaligned_u_int32_t;

static inline u_int16_t
EXTRACT_16BITS(const void *p)
{
	return ((u_int16_t)ntohs(((const unaligned_u_int16_t *)(p))->val));
}

static inline u_int32_t
EXTRACT_32BITS(const void *p)
{
	return ((u_int32_t)ntohl(((const unaligned_u_int32_t *)(p))->val));
}

const char *code2str(const struct tok *, const char[], int);
void ip_print(struct sbuf *sbuf, const u_char *bp, u_int length);
void ip6_print(struct sbuf *sbuf, const u_char *bp, u_int length);
int mobility_print(struct sbuf *sbuf, const u_char *bp, int len);
void tcp_print(struct sbuf *sbuf, register const u_char *bp, register u_int length,
          register const u_char *bp2);
void icmp_print(struct sbuf *, const u_char *, u_int, const u_char *, int);
int hbhopt_print(struct sbuf *sbuf, register const u_char *bp);
int dstopt_print(struct sbuf *sbuf, register const u_char *bp);
void ip6_opt_print(struct sbuf *sbuf, const u_char *bp, int len);

#endif /* _FILTER_LOG_COMMON_H_ */
