--- cftoken.l.orig	2017-02-28 19:06:15 UTC
+++ cftoken.l
@@ -1,5 +1,7 @@
 /*	$KAME: cftoken.l,v 1.35 2005/01/12 06:06:11 suz Exp $	*/
 
+%option noyywrap
+%option noinput
 %{
 /*
  * Copyright (C) 2002 WIDE Project.
@@ -53,6 +55,10 @@
 
 #define YY_NO_UNPUT 1
 
+extern char *configfilename;
+extern int lineno;
+extern void cf_init(void);
+
 char *configfilename;
 int lineno = 1;
 
@@ -65,24 +71,28 @@ static struct include_stack {
 	YY_BUFFER_STATE state;
 	int lineno;
 } incstack[MAX_INCLUDE_DEPTH];
-int incstackp = 0;
+static int incstackp = 0;
 
 
 static int yy_first_time = 1;
 static int yyerrorcount = 0;
- 
+
 #ifndef NOCONFIG_DEBUG
+#ifndef YYDEBUG
 #define YYDEBUG 1
-
-int cfdebug = 1;
+#endif
+static int cfdebug = 1;
 #else
-int cfdebug = 0;
+static int cfdebug = 0;
 #endif
 
-extern int yyparse __P((void));
-extern int cf_post_config __P((void));
+extern int yyparse(void);
+extern int cf_post_config(void);
 
-static void cfdebug_print __P((char *, char *, int));
+static void cfdebug_print(const char *, const char *, int);
+void yyerror(const char *, ...);
+void yywarn(char *, ...);
+int cfswitch_buffer(char *);
 
 #define DP(str) if (cfdebug) cfdebug_print(str, yytext, yyleng)
 #define DECHO if (cfdebug) cfdebug_print(NULL, yytext, yyleng);
@@ -103,12 +113,14 @@ number		{integer}|({digit}*\.{integer})
 hexdigit	[0-9A-Fa-f]
 hexpair		{hexdigit}{hexdigit}
 hexstring	0[xX]{hexpair}+
-duid 		{hexpair}(:{hexpair})*
+/* XXX */
+hexdata 	{hexpair}(:{hexpair})*
 ifname		{string}
 slash		\/
 bcl		\{
 ecl		\}
 
+
 %s S_CNF
 %s S_IFACE
 %s S_PREF
@@ -120,6 +132,7 @@ ecl		\}
 %s S_SECRET
 %s S_ADDRPOOL
 %s S_INCL
+%s S_RAW
 
 %%
 %{
@@ -147,7 +160,7 @@ ecl		\}
 }
 
 	/* address pool configuration */
-<S_CNF>pool { DECHO; BEGIN S_ADDRPOOL; return (ADDRPOOL); }  
+<S_CNF>pool { DECHO; BEGIN S_ADDRPOOL; return (ADDRPOOL); }
 
 <S_ADDRPOOL>{string} {
 	DECHO;
@@ -157,7 +170,7 @@ ecl		\}
 }
 
 <S_CNF>duid { DECHO; BEGIN S_DUID; return (DUID); }
-<S_DUID>{duid} {
+<S_DUID>{hexdata} {
 	DECHO;
 	yylval.str = strdup(yytext);
 	BEGIN S_CNF;
@@ -205,10 +218,18 @@ ecl		\}
 <S_CNF>bcmcs-server-address { DECHO; return (BCMCS_SERVERS); }
 <S_CNF>bcmcs-server-domain-name { DECHO; return (BCMCS_NAME); }
 <S_CNF>refreshtime { DECHO; return (REFRESHTIME); }
+<S_CNF>raw-option { DECHO; BEGIN S_RAW; return (RAW); }
+<S_RAW>{integer} { DECHO; yylval.num = strtoll(yytext, NULL, 10); return (NUMBER); }
+<S_RAW>{hexdata} {
+	DECHO;
+	yylval.str = strdup(yytext);
+	BEGIN S_CNF;
+	return (STRING);
+}
 
 	/* provided for a backward compatibility to WIDE-DHCPv6 before Oct 1 2006 */
 <S_CNF>nis-server-domain-name { DECHO; return (NIS_NAME); }
-<S_CNF>nisp-server-domain-name { DECHO; return (NISP_NAME); }	
+<S_CNF>nisp-server-domain-name { DECHO; return (NISP_NAME); }
 
 	/* generic options */
 <S_CNF>information-only { DECHO; return (INFO_ONLY); }
@@ -335,9 +356,7 @@ ecl		\}
 
 %%
 static void
-cfdebug_print(w, t, l)
-	char *w, *t;
-	int l;
+cfdebug_print(const char *w, const char *t, int l)
 {
 	if (w) {
 		d_printf(LOG_DEBUG, FNAME,
@@ -349,7 +368,7 @@ cfdebug_print(w, t, l)
 }
 
 static void
-yyerror0(int level, char *s, va_list ap)
+yyerror0(int level, const char *s, va_list ap)
 {
 	char ebuf[BUFSIZ], *bp, *ep;
 
@@ -363,7 +382,7 @@ yyerror0(int level, char *s, va_list ap)
 }
 
 void
-yyerror(char *s, ...)
+yyerror(const char *s, ...)
 {
 	va_list ap;
 #ifdef HAVE_STDARG_H
@@ -390,8 +409,7 @@ yywarn(char *s, ...)
 }
 
 int
-cfswitch_buffer(incl)
-	char *incl;
+cfswitch_buffer(char *incl)
 {
 	char *path = qstrdup(incl);
 	FILE *fp;
@@ -423,10 +441,14 @@ cfswitch_buffer(incl)
 }
 
 int
-cfparse(conf)
-	char *conf;
+cfparse(const char *conf)
 {
-	configfilename = conf;
+
+	free(configfilename);
+	configfilename = strdup(conf);
+
+	lineno = 1;
+	cf_init();
 	if ((yyin = fopen(configfilename, "r")) == NULL) {
 		d_printf(LOG_ERR, FNAME, "cfparse: fopen(%s): %s",
 			configfilename, strerror(errno));
