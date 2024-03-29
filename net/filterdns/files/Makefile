#
# Makefile
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2009-2024 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

MAINTAINER?=	coreteam@pfsense.org
COMMENT?=	filterdns

PROG=           filterdns
PREFIX?=        /usr/local
BINDIR?=        ${PREFIX}/sbin
DATAMODE=       0444
INSTALL?=       /usr/bin/install

OBJS=           filterdns.o parse.o tables.o

WARNS=          -Wsystem-headers -Werror -Wall -Wno-format-y2k -W \
                -Wno-unused-parameter -Wstrict-prototypes \
                -Wmissing-prototypes -Wpointer-arith -Wreturn-type \
                -Wcast-qual -Wwrite-strings -Wswitch -Wshadow \
                -Wunused-parameter -Wchar-subscripts -Winline \
                -Wnested-externs -Wredundant-decls -Wno-pointer-sign \
		-Wno-format
LDD=            -lpthread
CFLAGS+= -O2 -pipe ${WARNS}

all: ${PROG}

${PROG}: ${OBJS}
	cc ${CFLAGS} -o ${PROG} ${OBJS} ${LDD}

install: all
	${INSTALL} -m 0755 -s ${PROG} ${BINDIR}/

clean:
	rm -f ${PROG} ${PROG}.core *.o y.tab.h
