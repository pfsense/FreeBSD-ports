/*
 * Copyright (c) 2002-2004 Marius Strobl
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
 * Wrapper for Intel(R) C/C++ Compiler for Linux to allow linking of native
 * FreeBSD binaries.
 * Based on a shell-script written by Dan Nelson <dnelson@allantgroup.com>
 * with some modifications by Alexander Leidinger <netchild@FreeBSD.org>.
 *
 */

#include <sys/cdefs.h>

#include <err.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <osreldate.h>

#define	PATH_LD	"/usr/bin/ld"

#define	LDW_CXXICC	(1<<0)
#define	LDW_DYN		(1<<1)
#define	LDW_GPROF	(1<<2)
#define	LDW_PIC		(1<<3)
#define	LDW_STLP	(1<<4)
#define	LDW_THR		(1<<5)

struct arglist {
	size_t argc;
	const char **argv;
};

static void addarg(struct arglist *al, const char *arg);
int main(int argc, char *argv[], char *envp[]);

static void
addarg(struct arglist *al, const char *arg)
{
	const char **argv2;

	argv2 = realloc(al->argv, (al->argc + 1) * sizeof(al->argv[0]));

	if (argv2 == NULL)
		err(1, NULL);

	al->argv = argv2;

	al->argv[al->argc++] = arg;
}

int
main(int argc, char *argv[], char *envp[])
{
	size_t i;
	u_int flags;
	const char *libc, *libthr, *icc_localbase;
	struct arglist al = { 0, NULL };

	flags = 0;

	if (argc == 1)
		errx(1, "no input files");

	if ((icc_localbase = getenv("ICC_LOCALBASE")) == NULL)
		errx(1, "can't get ICC_LOCALBASE");

#ifdef DEBUG
	printf("input: ");
#endif

#define	ARGCMP(i, x)	!strcmp(argv[i], (x))

	/*
	 * XXX This doesn't deal with whitespace but a) the output of the
	 *     compiler should be fixed and b) the real linker is also picky
	 *     about whitespace.
	 */
	for (i = 0; i < argc; i++) {
#ifdef DEBUG
		printf("%s ", argv[i]);
#endif

	 	if (ARGCMP(i, "-CPLUSPLUS")) {
			flags |= LDW_CXXICC;
			continue;
	    	}

	 	if (ARGCMP(i, "-MT")) {
			flags |= LDW_THR;
			continue;
	    	}

	 	if (ARGCMP(i, "-PIC")) {
			flags |= LDW_PIC;
			continue;
	    	}

		/*
		 * Check if the compiler wants us to do dynamic linking, i.e.
		 * the compiler was called with -shared or without -static.
		 * If the compiler was called with -static we shouldn't see
		 * "--dynamic-linker" here.
		 * Note: According to ld(1) this is "--dynamic-linker" but
		 *	 ICC passes "-dynamic-linker" to it.
		 */
		if (ARGCMP(i, "--dynamic-linker") ||
		    ARGCMP(i, "-dynamic-linker") || ARGCMP(i, "-shared")) {
			flags |= LDW_DYN;
			continue;
		}

		/*
		 * The STLport library just can be linked once otherwise
		 * we get problems with constructors and destructors of
		 * global instances.
		 */
		if (!strncmp(argv[i], "-lstlport_icc",
		    sizeof("-lstlport_icc") - 1)) {
			flags |= LDW_STLP;
			continue;
		}

		/*
		 * Link against libc_p when "-qp" or "-p" were given,
		 * "/usr/lib/gcrt1.o" indicates this.
		 */
		if (ARGCMP(i, "/usr/lib/gcrt1.o")) {
			flags |= LDW_GPROF;
			continue;
		}
	}

	/*
	 * Allow the user to specify an alternative threads library
	 * implementation, such as -lthr, or whatever.
	 */
#if __FreeBSD_version >= 500016
	if ((libthr = getenv("PTHREAD_LIBS")) == NULL)
#if __FreeBSD_version >= 502102
		libthr = "-lpthread";
#else
		libthr = "-lc_r";
#endif
#else
	libthr = "-lc_r";
#endif
	/*
	 * Use the appropriate libs for libc and libthr when linking static
	 * and "-KPIC" or "-pg" where given.
	 */
	if (!(flags & LDW_DYN) && flags & (LDW_PIC | LDW_GPROF)) {
		/*
		 * Let libc_p win above libc_pic when both, "-KPIC" and "-pg",
		 * where given, GCC does the same.
		 */
		if (!(flags & LDW_GPROF))
			libc = "-lc_pic";
		else {
			char *p;

			libc = "-lc_p";
			asprintf(&p, "%s_p", libthr);
			if (p == NULL)
				err(1, NULL);
			libthr = p;
		}
	} else
		libc = "-lc";

#ifdef DEBUG
	printf("\n");
#endif

	for (i = 0; i < argc; i++) {
	 	if (ARGCMP(i, "-CPLUSPLUS") || ARGCMP(i, "-MT") ||
		    ARGCMP(i, "-PIC"))
			continue;

		/*
		 * Prepend "-melf_i386" and "-melf_i386_fbsd" respectively
		 * to the commandline.
		 */
		if (i == 0) {
			addarg(&al, argv[0]);
#if __FreeBSD_version < 500042
			addarg(&al, "-melf_i386");
#else
			addarg(&al, "-melf_i386_fbsd");
#endif
			continue;
		}

		/*
		 * Don't add "-m elf_i386" ICC passed to us. Don't add
		 * libgcc_eh, libgcc_s or libgcc_s_32, libdl.
		 */
		if ((ARGCMP(i, "-m") && i < argc - 1 && ARGCMP(i + 1,
		    "elf_i386")) || (ARGCMP(i, "elf_i386") && i != 0 &&
		    ARGCMP(i - 1, "-m")) || ARGCMP(i, "-lgcc_eh") ||
		    ARGCMP(i, "-lgcc_s") || ARGCMP(i, "-lgcc_s_32") ||
		    ARGCMP(i, "-ldl"))
			continue;

		/*
		 * Replace libcprts with libstlport_icc. The Dinkumware STL
		 * shipping with ICC has unresolvable glibc dependencies
		 * in both, the static and the dynamic, versions.
		 */
		if (ARGCMP(i, "-lcprts")) {
			if (flags & LDW_CXXICC && !(flags & LDW_STLP)) {
				char *p;

				asprintf(&p, "-L%s/lib", icc_localbase);
				if (p == NULL)
					err(1, NULL);
				addarg(&al, p);
				addarg(&al,
				    flags & LDW_DYN ? "-Bdynamic" : "-Bstatic");
				addarg(&al, "-lstlport_icc");
			}
			continue;
		}

		/*
		 * Inject the compatibility library for ICC libs on FreeBSD.
		 * Link against libthr when compiling multi-threaded or C++
		 * code and not using libstdc++ (libcxa and libunwind depend
		 * on a threads library).
		 */
		if (ARGCMP(i, "-lc")) {
			addarg(&al, "-Bstatic");
			addarg(&al, "-liccfbsd");
			addarg(&al, flags & LDW_DYN ? "-Bdynamic" : "-Bstatic");
			if (flags & (LDW_CXXICC | LDW_THR)) {
				addarg(&al, libthr);
#if __FreeBSD_version >= 500016
				addarg(&al,
				    flags & LDW_DYN ? "-Bdynamic" : "-Bstatic");
				addarg(&al, libc);
#endif
			} else
				addarg(&al, libc);
			continue;
		}

		/* Switch Linux stuff to FreeBSD counterparts. */
		if (ARGCMP(i, "/lib/ld-linux.so.2")) {
#if __FreeBSD_version >= 501105
			addarg(&al, "/libexec/ld-elf.so.1");
#else
			addarg(&al, "/usr/libexec/ld-elf.so.1");
#endif
			continue;
		}
		if (ARGCMP(i, "-L/usr/lib")) {
			addarg(&al, "-L/usr/libexec/elf");
			addarg(&al, "-L/usr/libexec");
			addarg(&al, "-L/usr/lib");
			continue;
		}

		/*
		 * Force libcxa, libcxaguard, libimf, libsvml and libunwind
		 * to static linkage, since the dynamic versions have glibc
		 * dependencies.
		 */
		if (ARGCMP(i, "-Bdynamic") && i < argc - 1 &&
		    (ARGCMP(i + 1, "-lcxa") || ARGCMP(i + 1, "-lcxaguard") ||
		    ARGCMP(i + 1, "-limf") || ARGCMP(i + 1, "-lsvml") ||
		    ARGCMP(i + 1, "-lunwind"))) {
			addarg(&al, "-Bstatic");
			continue;
		}

		/*
		 * Sanity check if every lib is prepended by a linkage option,
		 * add if missing.
		 */
		if (!strncmp(argv[i], "-l", 2) &&
		    ((i != 0 && strncmp(argv[i - 1], "-B", 2)) ||
		    (al.argc > 0 && strncmp(al.argv[al.argc - 1], "-B", 2)))) {
			if (ARGCMP(i, "-lcxa") || ARGCMP(i, "-lcxaguard") ||
			    ARGCMP(i, "-limf") || ARGCMP(i, "-lirc") ||
			    ARGCMP(i, "-lirc_s") || ARGCMP(i, "-lsvml") ||
			    ARGCMP(i, "-lunwind"))
				addarg(&al, "-Bstatic");
			else
				addarg(&al,
 				    flags & LDW_DYN ? "-Bdynamic" : "-Bstatic");

			addarg(&al, argv[i]);
			continue;
	 	}

		/* default */
		addarg(&al, argv[i]);
	}

	/* Still something to do? */
	if (al.argc == 1)
		errx(1, "no input files");

#ifdef DEBUG
	printf("output: ");

	for (i = 0; i < al.argc; i++)
		printf("%s ", al.argv[i]);

	printf("\n");
#endif

	addarg(&al, NULL);

	/* Launch the real linker. */
	if (execve(PATH_LD, (char **)al.argv, envp) == -1)
		err(1, "execing " PATH_LD);

	exit (1);
}
