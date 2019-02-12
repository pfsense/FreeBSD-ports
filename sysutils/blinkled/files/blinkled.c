/* Part of this code taken from:
 * MiniUPnP project
 * http://miniupnp.free.fr/ or http://miniupnp.tuxfamily.org/
 * author: Ryan Wagoner and Thomas Bernard
 * (c) 2006 Thomas Bernard 
 * This software is subject to the conditions detailed
 * in the LICENCE file provided within the distribution */

#include <sys/types.h>
#include <sys/ck.h>
#include <sys/socket.h>
#include <net/if.h>
#include <arpa/inet.h>
#include <netinet/in.h>
#if defined(__FreeBSD__)
#include <net/if_var.h>
#endif
#include <net/pfvar.h>
#include <kvm.h>
#include <fcntl.h>
#include <nlist.h>
#include <sys/queue.h>
#include <signal.h>
#include <stdio.h>
#include <string.h>
#include <limits.h>
#include <unistd.h>

struct nlist list[] = {
	{"_ifnet"},
	{NULL}
};

struct ifdata {
	unsigned long opackets;
	unsigned long ipackets;
	unsigned long obytes;
	unsigned long ibytes;
	unsigned long baudrate;
};

static volatile int quitting = 0;

void sigterm(int sig);
int getifstats(const char * ifname, struct ifdata * data);

int main(int argc, char * * argv)
{
	int i, file, ledstatelen;
	int debugflag = 0;
	char ledstate[5];
	struct ifdata tdata, pdata;
	const char * iface = NULL, * led = NULL;
	struct sigaction sa;

	for(i=1; i<argc; i++)
	{
		if(argv[i][0]!='-')
		{
			fprintf(stderr, "Unknown option: %s\n", argv[i]);
		}
		else switch(argv[i][1])
		{
		case 'i':
			iface = argv[++i];
			break;
		case 'l':
			led = argv[++i];
			break;
		case 'd':
			debugflag = 1;	
			break;		
		default:
			fprintf(stderr, "Unknown option: %s\n", argv[i]);
		}
	}

	if(!iface || !led)
	{
		fprintf(stderr, "Usage:\n\t%s [-i ifname] [-l led] [-d]\n", argv[0]);
		return 1;
	}

	if(access(led, F_OK) < 0)
	{
		fprintf(stderr, "Error: Unable to access %s\n", led);
		return 1;
	}

	if (!debugflag)
	{
		if (fork() == 0)
		{
			int nullfd;
			if ((nullfd = open("/dev/null", O_WRONLY, 0)) < 0)
			{
				fprintf(stderr, "Error: Could not open /dev/null");
				return 1;
			}
			dup2(nullfd, STDIN_FILENO);
			dup2(nullfd, STDOUT_FILENO);
			dup2(nullfd, STDERR_FILENO);
			close(nullfd);
			setsid();
		}
		else
		{
			return 0;
		}
	}	

	memset(&sa, 0, sizeof(struct sigaction));
	sa.sa_handler = sigterm;

	if (sigaction(SIGTERM, &sa, NULL))
	{
		fprintf(stderr, "Error: Unable to set SIGTERM handler\n");
		return 1;
	}
	if (sigaction(SIGINT, &sa, NULL))
	{
		fprintf(stderr, "Error: Unable to set SIGTERM handler\n");
		return 1;
	}		

	if(getifstats(iface, &tdata) < 0)
	{
		fprintf(stderr, "Error: getifstats: FAILED\n");
		return 1;
	}

	while (!quitting)
	{
		sleep(1);

		if(getifstats(iface, &pdata) < 0)
		{
			fprintf(stderr, "Error: getifstats: FAILED\n");
			return 1;
		}

		if( (pdata.ibytes - tdata.ibytes) > 5120 || (pdata.obytes - tdata.obytes) > 5120 )
		{
			ledstatelen = sprintf(ledstate, "f1");
			tdata = pdata;
		}
		else
		{
			ledstatelen = sprintf(ledstate, "0");
		}

		file = open(led, O_WRONLY, 0666);
		write(file, ledstate, ledstatelen);
		close(file);				
	}

	ledstatelen = sprintf(ledstate, "0");
	file = open(led, O_WRONLY, 0666);
	write(file, ledstate, ledstatelen);
	close(file);	

	printf("Good-bye\n");	

	return 0;
}

void sigterm(int sig)
{
	signal(sig, SIG_IGN);
	quitting = 1;
}

int getifstats(const char * ifname, struct ifdata * data)
{
#if defined(__FreeBSD__)
	struct ifnethead ifh;
#elif defined(__OpenBSD__) || defined(__NetBSD__)
	struct ifnet_head ifh;
#else
	#error "Dont know if I should use struct ifnethead or struct ifnet_head"
#endif
	struct ifnet ifc;
	struct ifnet *ifp;
	kvm_t *kd;
	ssize_t n;
	char errstr[_POSIX2_LINE_MAX];

	kd = kvm_openfiles(NULL, NULL, NULL, O_RDONLY, errstr);
	if(!kd)
	{
		fprintf(stderr, "Error: kvm_open(): %s\n", errstr);
		return -1;
	}
	if(kvm_nlist(kd, list) < 0)
	{
		fprintf(stderr, "Error: kvm_nlist(): FAILED\n");
		kvm_close(kd);
		return -1;
	}
	if(!list[0].n_value)
	{
		fprintf(stderr, "Error: n_value(): FAILED\n");
		kvm_close(kd);
		return -1;
	}
	n = kvm_read(kd, list[0].n_value, &ifh, sizeof(ifh));
	if(n<0)
	{
		fprintf(stderr, "Error: kvm_read(head): %s\n", kvm_geterr(kd));
		kvm_close(kd);
		return -1;
	}
	for(ifp = STAILQ_FIRST(&ifh); ifp; ifp = STAILQ_NEXT(&ifc, if_link))
	{
		n = kvm_read(kd, (u_long)ifp, &ifc, sizeof(ifc));
		if(n<0)
		{
			fprintf(stderr, "Error: kvm_read(element): %s\n", kvm_geterr(kd));
			kvm_close(kd);
			return -1;
		}
		if(strcmp(ifname, ifc.if_xname) == 0)
		{
#if defined(__FreeBSD__) && __FreeBSD_version >= 1100011
			data->opackets = ifc.if_get_counter(&ifc, IFCOUNTER_OPACKETS);
			data->ipackets = ifc.if_get_counter(&ifc, IFCOUNTER_IPACKETS);
			data->obytes = ifc.if_get_counter(&ifc, IFCOUNTER_OBYTES);
			data->ibytes = ifc.if_get_counter(&ifc, IFCOUNTER_IBYTES);
			data->baudrate = ifc.if_baudrate;
#else
			data->opackets = ifc.if_data.ifi_opackets;
			data->ipackets = ifc.if_data.ifi_ipackets;
			data->obytes = ifc.if_data.ifi_obytes;
			data->ibytes = ifc.if_data.ifi_ibytes;
			data->baudrate = ifc.if_data.ifi_baudrate;
#endif
			kvm_close(kd);
			return 0;
		}
	}

	return -1;
}
