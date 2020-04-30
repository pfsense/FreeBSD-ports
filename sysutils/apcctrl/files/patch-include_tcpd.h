--- include/tcpd.h.orig	2016-06-15 10:03:22 UTC
+++ include/tcpd.h
@@ -0,0 +1,235 @@
+ /*
+  * @(#) tcpd.h 1.5 96/03/19 16:22:24
+  * 
+  * Author: Wietse Venema, Eindhoven University of Technology, The Netherlands.
+  */
+
+/* 
+ * This version of the file has been hacked over by 
+ *   Kern Sibbald to make it compatible with C++ for 
+ *   the few functions that Bacula uses.  19 April 2002
+ *  It now compiles with C++ but remains untested.
+ *  A correct fix would require significantly more work.
+ */
+
+#ifdef __cplusplus
+extern "C" {
+#endif
+
+/* Structure to describe one communications endpoint. */
+
+#define STRING_LENGTH   128             /* hosts, users, processes */
+
+struct host_info {
+    char    name[STRING_LENGTH];        /* access via eval_hostname(host) */
+    char    addr[STRING_LENGTH];        /* access via eval_hostaddr(host) */
+    struct sockaddr_in *sin;            /* socket address or 0 */
+    struct t_unitdata *unit;            /* TLI transport address or 0 */
+    struct request_info *request;       /* for shared information */
+};
+
+/* Structure to describe what we know about a service request. */
+
+struct request_info {
+    int     fd;                         /* socket handle */
+    char    user[STRING_LENGTH];        /* access via eval_user(request) */
+    char    daemon[STRING_LENGTH];      /* access via eval_daemon(request) */
+    char    pid[10];                    /* access via eval_pid(request) */
+    struct host_info client[1];         /* client endpoint info */
+    struct host_info server[1];         /* server endpoint info */
+    void  (*sink) (void);               /* datagram sink function or 0 */
+    void  (*hostname) (void);           /* address to printable hostname */
+    void  (*hostaddr) (void);           /* address to printable address */
+    void  (*cleanup) (void);            /* cleanup function or 0 */
+    struct netconfig *config;           /* netdir handle */
+};
+
+/* Common string operations. Less clutter should be more readable. */
+
+#define STRN_CPY(d,s,l) { strncpy((d),(s),(l)); (d)[(l)-1] = 0; }
+
+#define STRN_EQ(x,y,l)  (strncasecmp((x),(y),(l)) == 0)
+#define STRN_NE(x,y,l)  (strncasecmp((x),(y),(l)) != 0)
+#define STR_EQ(x,y)     (strcasecmp((x),(y)) == 0)
+#define STR_NE(x,y)     (strcasecmp((x),(y)) != 0)
+
+ /*
+  * Initially, all above strings have the empty value. Information that
+  * cannot be determined at runtime is set to "unknown", so that we can
+  * distinguish between `unavailable' and `not yet looked up'. A hostname
+  * that we do not believe in is set to "paranoid".
+  */
+
+#define STRING_UNKNOWN  "unknown"       /* lookup failed */
+#define STRING_PARANOID "paranoid"      /* hostname conflict */
+
+extern char unknown[];
+extern char paranoid[];
+
+#define HOSTNAME_KNOWN(s) (STR_NE((s),unknown) && STR_NE((s),paranoid))
+
+#define NOT_INADDR(s) (s[strspn(s,"01234567890./")] != 0)
+
+/* Global functions. */
+
+#if defined(TLI) || defined(PTX) || defined(TLI_SEQUENT)
+extern void fromhost();                 /* get/validate client host info */
+#else
+#define fromhost sock_host              /* no TLI support needed */
+#endif
+
+extern int hosts_access(struct request_info *); /* access control */
+extern void shell_cmd(void);            /* execute shell command */
+extern char *percent_x(void);               /* do %<char> expansion */
+extern void rfc931(void);                   /* client name from RFC 931 daemon */
+extern void clean_exit(void);               /* clean up and exit */
+extern void refuse(void);                   /* clean up and exit */
+extern char *xgets(void);                   /* fgets() on steroids */
+extern char *split_at(void);                /* strchr() and split */
+extern unsigned long dot_quad_addr(void);   /* restricted inet_addr() */
+
+/* Global variables. */
+
+extern int allow_severity;              /* for connection logging */
+extern int deny_severity;               /* for connection logging */
+extern char *hosts_allow_table;         /* for verification mode redirection */
+extern char *hosts_deny_table;          /* for verification mode redirection */
+extern int hosts_access_verbose;        /* for verbose matching mode */
+extern int rfc931_timeout;              /* user lookup timeout */
+extern int resident;                    /* > 0 if resident process */
+
+ /*
+  * Routines for controlled initialization and update of request structure
+  * attributes. Each attribute has its own key.
+  */
+
+#ifdef __STDC__
+extern struct request_info *request_init(struct request_info *,...);
+extern struct request_info *request_set(struct request_info *,...);
+#else
+extern struct request_info *request_init();     /* initialize request */
+extern struct request_info *request_set();      /* update request structure */
+#endif
+
+#define RQ_FILE         1               /* file descriptor */
+#define RQ_DAEMON       2               /* server process (argv[0]) */
+#define RQ_USER         3               /* client user name */
+#define RQ_CLIENT_NAME  4               /* client host name */
+#define RQ_CLIENT_ADDR  5               /* client host address */
+#define RQ_CLIENT_SIN   6               /* client endpoint (internal) */
+#define RQ_SERVER_NAME  7               /* server host name */
+#define RQ_SERVER_ADDR  8               /* server host address */
+#define RQ_SERVER_SIN   9               /* server endpoint (internal) */
+
+ /*
+  * Routines for delayed evaluation of request attributes. Each attribute
+  * type has its own access method. The trivial ones are implemented by
+  * macros. The other ones are wrappers around the transport-specific host
+  * name, address, and client user lookup methods. The request_info and
+  * host_info structures serve as caches for the lookup results.
+  */
+
+extern char *eval_user(void);               /* client user */
+extern char *eval_hostname(void);           /* printable hostname */
+extern char *eval_hostaddr(void);           /* printable host address */
+extern char *eval_hostinfo(void);           /* host name or address */
+extern char *eval_client(struct request_info *); /* whatever is available */
+extern char *eval_server(void);             /* whatever is available */
+#define eval_daemon(r)  ((r)->daemon)   /* daemon process name */
+#define eval_pid(r)     ((r)->pid)      /* process id */
+
+/* Socket-specific methods, including DNS hostname lookups. */
+
+extern void sock_host(struct request_info *); 
+extern void sock_hostname(void);            /* translate address to hostname */
+extern void sock_hostaddr(void);            /* address to printable address */
+#define sock_methods(r) \
+        { (r)->hostname = sock_hostname; (r)->hostaddr = sock_hostaddr; }
+
+/* The System V Transport-Level Interface (TLI) interface. */
+
+#if defined(TLI) || defined(PTX) || defined(TLI_SEQUENT)
+extern void tli_host();                 /* look up endpoint addresses etc. */
+#endif
+
+ /*
+  * Problem reporting interface. Additional file/line context is reported
+  * when available. The jump buffer (tcpd_buf) is not declared here, or
+  * everyone would have to include <setjmp.h>.
+  */
+
+#ifdef __STDC__
+extern void tcpd_warn(char *, ...);     /* report problem and proceed */
+extern void tcpd_jump(char *, ...);     /* report problem and jump */
+#else
+extern void tcpd_warn();
+extern void tcpd_jump();
+#endif
+
+struct tcpd_context {
+    char   *file;                       /* current file */
+    int     line;                       /* current line */
+};
+extern struct tcpd_context tcpd_context;
+
+ /*
+  * While processing access control rules, error conditions are handled by
+  * jumping back into the hosts_access() routine. This is cleaner than
+  * checking the return value of each and every silly little function. The
+  * (-1) returns are here because zero is already taken by longjmp().
+  */
+
+#define AC_PERMIT       1               /* permit access */
+#define AC_DENY         (-1)            /* deny_access */
+#define AC_ERROR        AC_DENY         /* XXX */
+
+ /*
+  * In verification mode an option function should just say what it would do,
+  * instead of really doing it. An option function that would not return
+  * should clear the dry_run flag to inform the caller of this unusual
+  * behavior.
+  */
+
+extern void process_options(void);          /* execute options */
+extern int dry_run;                     /* verification flag */
+
+/* Bug workarounds. */
+
+#ifdef INET_ADDR_BUG                    /* inet_addr() returns struct */
+#define inet_addr fix_inet_addr
+extern long fix_inet_addr(void);
+#endif
+
+#ifdef BROKEN_FGETS                     /* partial reads from sockets */
+#define fgets fix_fgets
+extern char *fix_fgets(void);
+#endif
+
+#ifdef RECVFROM_BUG                     /* no address family info */
+#define recvfrom fix_recvfrom
+extern int fix_recvfrom(void);
+#endif
+
+#ifdef GETPEERNAME_BUG                  /* claims success with UDP */
+#define getpeername fix_getpeername
+extern int fix_getpeername(void);
+#endif
+
+#ifdef SOLARIS_24_GETHOSTBYNAME_BUG     /* lists addresses as aliases */
+#define gethostbyname fix_gethostbyname
+extern struct hostent *fix_gethostbyname(void);
+#endif
+
+#ifdef USE_STRSEP                       /* libc calls strtok(void) */
+#define strtok  fix_strtok
+extern char *fix_strtok(void);
+#endif
+
+#ifdef LIBC_CALLS_STRTOK                /* libc calls strtok() */
+#define strtok  my_strtok
+extern char *my_strtok(void);
+#endif
+
+#ifdef __cplusplus
+}
+#endif
