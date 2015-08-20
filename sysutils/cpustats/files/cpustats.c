
#include <sys/types.h>
#include <sys/resource.h>
#include <sys/sysctl.h>

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

/* these are for calculating cpu state percentages */

static long cp_time[CPUSTATES];
static long cp_old[CPUSTATES];
static long cp_diff[CPUSTATES];

int cpu_states[CPUSTATES];
char *cpustatenames[] = {
        "user", "nice", "system", "interrupt", "idle", NULL
};

/*
 *  percentages(cnt, out, new, old, diffs) - calculate percentage change
 *      between array "old" and "new", putting the percentages i "out".
 *      "cnt" is size of each array and "diffs" is used for scratch space.
 *      The array "old" is updated on each call.
 *      The routine assumes modulo arithmetic.  This function is especially
 *      useful on BSD mchines for calculating cpu state percentages.
 */

long
percentages(int cnt, int *out, register long *new, register long *old, long *diffs)
{
    register int i;
    register long change;
    register long total_change;
    register long *dp;
    long half_total;

    /* initialization */
    total_change = 0;
    dp = diffs;
     
    /* calculate changes for each state and the overall change */
    for (i = 0; i < cnt; i++)
    {
        if ((change = *new - *old) < 0)
        {
            /* this only happens when the counter wraps */
            change = (int)
                ((unsigned long)*new-(unsigned long)*old);
        }
        total_change += (*dp++ = change);
        *old++ = *new++;
    }

    /* avoid divide by zero potential */
    if (total_change == 0)
    {
        total_change = 1;
    }

    /* calculate percentages based on overall change, rounding up */
    half_total = total_change / 2l;

    /* Do not divide by 0. Causes Floating point exception */
    if(total_change) {
        for (i = 0; i < cnt; i++)
        {
          *out++ = (int)((*diffs++ * 1000 + half_total) / total_change);
        }
    }

    /* return the total in case the caller wants to use it */
    return(total_change);
}


int
main(int argc, char **argv)
{

	char *separator;
	size_t s_stats = sizeof(long) * CPUSTATES;
	int i;

	separator = ":";
	if (argc > 1)
		separator = argv[1];

	if (sysctlbyname("kern.cp_time", cp_time, &s_stats, NULL, 0) < 0)
		printf("Could not fetch kernel processor times.\n");

	/* convert cp_time counts to percentages */
	percentages(CPUSTATES, cpu_states, cp_time, cp_old, cp_diff);

	for (i = 0; i < CPUSTATES; i++)
		cp_old[i] = cp_time[i];

	sleep(1);
	if (sysctlbyname("kern.cp_time", cp_time, &s_stats, NULL, 0) < 0)
		printf("Could not fetch kernel processor times.\n");

	/* convert cp_time counts to percentages */
	percentages(CPUSTATES, cpu_states, cp_time, cp_old, cp_diff);

	if (argc > 2) {
		printf("%s:%.1f%s ", cpustatenames[CP_USER], ((float)cpu_states[CP_USER])/10., separator);
		printf("%s:%.1f%s ", cpustatenames[CP_NICE], ((float)cpu_states[CP_NICE])/10., separator);
		printf("%s:%.1f%s ", cpustatenames[CP_SYS], ((float)cpu_states[CP_SYS])/10., separator);
		printf("%s:%.1f%s ", cpustatenames[CP_INTR], ((float)cpu_states[CP_INTR])/10., separator);
		printf("%s:%.1f \n", cpustatenames[CP_IDLE], ((float)cpu_states[CP_IDLE])/10.);
	} else {
		printf("%.1f%s", ((float)cpu_states[CP_USER])/10., separator);
		printf("%.1f%s", ((float)cpu_states[CP_NICE])/10., separator);
		printf("%.1f%s", ((float)cpu_states[CP_SYS])/10., separator);
		printf("%.1f%s", ((float)cpu_states[CP_INTR])/10., separator);
		printf("%.1f \n", ((float)cpu_states[CP_IDLE])/10.);
	}
}
