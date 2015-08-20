/*
 * $Id: lang.h,v 1.3 2005/09/01 19:23:14 cpressey Exp $
 */
#ifndef __LANG_H_
#define __LANG_H_

#ifdef __cplusplus
extern "C" {
#endif

int set_lang_syscons(const char *);
int set_lang_envars(const char *);
	
#ifdef __cplusplus
}
#endif

#endif /* !__LANG_H_ */
