/*
 * Copyright (c) 2004 The DragonFly Project.  All rights reserved.
 *
 * This code is derived from software contributed to The DragonFly Project
 * by Chris Pressey <cpressey@catseye.mine.nu>.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 * 3. Neither the name of The DragonFly Project nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific, prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE
 * COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT
 * OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */


/*
 * fspred.c
 * Filesystem predicates.
 * $Id: fspred.c,v 1.4 2005/08/27 07:17:16 cpressey Exp $
 */

#include <sys/stat.h>
#include <sys/param.h>
#if defined(__linux__) || defined(sun)
#include <mntent.h>
#else
#include <sys/ucred.h>
#endif
#include <sys/mount.h>

#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "fspred.h"

#if (__NetBSD_Version__ >= 200040000)
#define STATFS statvfs
#else
#define STATFS statfs
#endif

/** PREDICATES **/

int
is_dir(const char *fmt, ...)
{
	va_list args;
	char *filename;
	int result;
	struct stat sb;

	va_start(args, fmt);
	vasprintf(&filename, fmt, args);
	va_end(args);

	result = stat(filename, &sb);
	free(filename);

	if (result == 0)
		return(sb.st_mode & S_IFDIR);
	else
		return(0);
}

int
is_file(const char *fmt, ...)
{
	va_list args;
	char *filename;
	int result;
	struct stat sb;

	va_start(args, fmt);
	vasprintf(&filename, fmt, args);
	va_end(args);

	result = stat(filename, &sb);
	free(filename);

	if (result == 0)
		return(sb.st_mode & S_IFREG);
	else
		return(0);
}

int
is_program(const char *fmt, ...)
{
	va_list args;
	char *filename;
	int result;
	struct stat sb;

	va_start(args, fmt);
	vasprintf(&filename, fmt, args);
	va_end(args);

	result = stat(filename, &sb);
	free(filename);

	if (result == 0)
		return((sb.st_mode & S_IFREG) && (sb.st_mode & S_IXOTH));
	else
		return(0);
}

int
is_device(const char *fmt, ...)
{
	va_list args;
	char *filename;
	int result;
	struct stat sb;

	va_start(args, fmt);
	vasprintf(&filename, fmt, args);
	va_end(args);

	result = stat(filename, &sb);
	free(filename);

	if (result == 0)
		return((sb.st_mode & S_IFCHR) | (sb.st_mode & S_IFBLK));
	else
		return(0);
}

int
is_named_pipe(const char *fmt, ...)
{
	va_list args;
	char *filename;
	int result;
	struct stat sb;

	va_start(args, fmt);
	vasprintf(&filename, fmt, args);
	va_end(args);

	result = stat(filename, &sb);

	free(filename);

	if (result == 0)
		return(sb.st_mode & S_IFIFO);
	else
		return(0);
}

#if defined(__linux__) || defined(sun)
int
is_mountpoint_mounted(const char *mtpt)
{
	FILE *f;
	struct mntent *mt_ptr;

	if ((f = setmntent("/etc/mtab", "r")) == NULL) {
		return(0);
	}

	while ((mt_ptr = getmntent(f)) != NULL) {
		if (strcmp(mt_ptr->mnt_dir, mtpt) == 0) {
			endmntent(f);
			return(1);
		}
	}

	endmntent(f);
	return(0);
}

int
is_device_mounted(const char *device)
{
	FILE *f;
	struct mntent *mt_ptr;

	if ((f = setmntent("/etc/mtab", "r")) == NULL) {
		return(0);
	}

	while ((mt_ptr = getmntent(f)) != NULL) {
		if (strcmp(mt_ptr->mnt_fsname, device) == 0) {
			endmntent(f);
			return(1);
		}
	}

	endmntent(f);
	return(0);
}

int
is_any_slice_mounted(const char *diskdev)
{
	FILE *f;
	struct mntent *mt_ptr;

	if ((f = setmntent("/etc/mtab", "r")) == NULL) {
		return(0);
	}

	while ((mt_ptr = getmntent(f)) != NULL) {
		if (strstr(mt_ptr->mnt_fsname, diskdev) ==
		    mt_ptr->mnt_fsname) {
			endmntent(f);
			return(1);
		}
	}

	endmntent(f);
	return(0);
}
#else
int
is_mountpoint_mounted(const char *mtpt)
{
	struct STATFS *mt_array, *mt_ptr;
	int count;

	count = getmntinfo(&mt_array, MNT_WAIT);
	for (mt_ptr = mt_array; count > 0; mt_ptr++, count--) {
		if (strcmp(mt_ptr->f_mntonname, mtpt) == 0)
			return(1);
	}
	return(0);
}

int
is_device_mounted(const char *device)
{
	struct STATFS *mt_array, *mt_ptr;
	int count;

	count = getmntinfo(&mt_array, MNT_WAIT);
	for (mt_ptr = mt_array; count > 0; mt_ptr++, count--) {
		if (strcmp(mt_ptr->f_mntfromname, device) == 0)
			return(1);
	}
	return(0);
}

int
is_any_slice_mounted(const char *diskdev)
{
	struct STATFS *mt_array, *mt_ptr;
	int count;

	count = getmntinfo(&mt_array, MNT_WAIT);
	for (mt_ptr = mt_array; count > 0; mt_ptr++, count--) {
		if (strstr(mt_ptr->f_mntfromname, diskdev) ==
		    mt_ptr->f_mntfromname)
			return(1);
	}
	return(0);
}
#endif
