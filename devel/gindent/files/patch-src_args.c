--- src/args.c.orig	2018-09-02 20:30:45 UTC
+++ src/args.c
@@ -105,13 +105,20 @@ RCSTAG_CC ("$Id$");
      "-npcs\0-nprs\0-npsl\0-sai\0-saf\0-saw\0-ncs\0-nsc\0-sob\0-nfca\0-cp33\0-ss\0" \
      "-ts8\0-il1\0-nbs\0"
 
-const char *settings_strings[6] = {
+#define KNF_SETTINGS_STRING \
+     "-bad\0-bap\0-nbbb\0-nbc\0-bbo\0-br\0-brs\0-nbs\0-c33\0-cd33\0-cdb\0" \
+     "-ce\0-ci4\0-cli0\0-cp33\0-ncs\0-d0\0-di0\0-ndj\0-nfc1\0-nfca\0-hnl\0" \
+     "-i8\0-ip8\0-l79\0-nlp\0-npcs\0-nprs\0-psl\0-sai\0-saf\0-saw\0-sc\0" \
+     "-nsob\0-nss\0"
+
+const char *settings_strings[7] = {
 	KR_SETTINGS_STRING,
 	GNU_SETTINGS_STRING,
 	ORIG_SETTINGS_STRING,
 	LINUX_SETTINGS_STRING,
 	"-ip0\0",
-	VERSION
+	VERSION,
+	KNF_SETTINGS_STRING
 };
 
 #define KR_SETTINGS_IDX      (void *)0
@@ -120,6 +127,7 @@ const char *settings_strings[6] = {
 #define LINUX_SETTINGS_IDX   (void *)3
 #define NIP_SETTINGS_IDX     (void *)4
 #define VERSION_SETTINGS_IDX (void *)5
+#define KNF_SETTINGS_IDX     (void *)6
 
 /**
  * Profile types. These identify what kind of switches and arguments 
@@ -188,6 +196,7 @@ static int exp_hnl  = 0;
 static int exp_i    = 0;
 static int exp_il   = 0;
 static int exp_ip   = 0;
+static int exp_knf  = 0;
 static int exp_kr   = 0;
 static int exp_l    = 0;
 static int exp_lc   = 0;
@@ -296,66 +305,69 @@ const pro_ty pro[] =
 #endif
     {"pi",      PRO_INT,                               -1, ONOFF_NA, &settings.paren_indent,                     &exp_pi},
     {"pcs",     PRO_BOOL,                           false,       ON, &settings.proc_calls_space,                 &exp_pcs},
+    {"orig",    PRO_SETTINGS,                           0, ONOFF_NA, ORIG_SETTINGS_IDX,                          &exp_orig},
     {"o",       PRO_BOOL,                           false,       ON, &settings.expect_output_file,               &exp_o},
     {"nv",      PRO_BOOL,                           false,      OFF, &settings.verbose,                          &exp_v},
-    {"nut",     PRO_BOOL,                            true,      OFF, &settings.use_tabs,                         &exp_ut},
-    {"nss",     PRO_BOOL,                           false,      OFF, &settings.space_sp_semicolon,               &exp_ss},
-    {"nsob",    PRO_BOOL,                           false,      OFF, &settings.swallow_optional_blanklines,      &exp_sob},
-    {"nsc",     PRO_BOOL,                            true,      OFF, &settings.star_comment_cont,                &exp_sc},
-    {"nsaw",    PRO_BOOL,                            true,      OFF, &settings.space_after_while,                &exp_saw},
-    {"nsai",    PRO_BOOL,                            true,      OFF, &settings.space_after_if,                   &exp_sai},
-    {"nsaf",    PRO_BOOL,                            true,      OFF, &settings.space_after_for,                  &exp_saf},
-    {"npsl",    PRO_BOOL,                            true,      OFF, &settings.procnames_start_line,             &exp_psl},
-    {"nprs",    PRO_BOOL,                           false,      OFF, &settings.parentheses_space,                &exp_prs},
+    {"nut",     PRO_BOOL,                           false,      OFF, &settings.use_tabs,                         &exp_ut},
+    {"nss",     PRO_BOOL,                            true,      OFF, &settings.space_sp_semicolon,               &exp_ss},
+    {"nsob",    PRO_BOOL,                            true,      OFF, &settings.swallow_optional_blanklines,      &exp_sob},
+    {"nsc",     PRO_BOOL,                           false,      OFF, &settings.star_comment_cont,                &exp_sc},
+    {"nsaw",    PRO_BOOL,                           false,      OFF, &settings.space_after_while,                &exp_saw},
+    {"nsai",    PRO_BOOL,                           false,      OFF, &settings.space_after_if,                   &exp_sai},
+    {"nsaf",    PRO_BOOL,                           false,      OFF, &settings.space_after_for,                  &exp_saf},
+    {"npsl",    PRO_BOOL,                           false,      OFF, &settings.procnames_start_line,             &exp_psl},
+    {"nprs",    PRO_BOOL,                            true,      OFF, &settings.parentheses_space,                &exp_prs},
     {"npro",    PRO_IGN,                                0, ONOFF_NA, 0,                                          &exp_pro},
 #ifdef PRESERVE_MTIME
     {"npmt",    PRO_BOOL,                           false,      OFF, &settings.preserve_mtime,                   &exp_pmt},
 #endif
-    {"npcs",    PRO_BOOL,                           false,      OFF, &settings.proc_calls_space,                 &exp_pcs},
+    {"npcs",    PRO_BOOL,                           true,       OFF, &settings.proc_calls_space,                 &exp_pcs},
     {"nlps",    PRO_BOOL,                           false,      OFF, &settings.leave_preproc_space,              &exp_lps},
     {"nlp",     PRO_BOOL,                            true,      OFF, &settings.lineup_to_parens,                 &exp_lp},
     {"nip",     PRO_SETTINGS,                           0, ONOFF_NA, NIP_SETTINGS_IDX,                           &exp_nip},
-    {"nhnl",    PRO_BOOL,                            true,      OFF, &settings.honour_newlines,                  &exp_hnl},
+    {"nhnl",    PRO_BOOL,                           false,      OFF, &settings.honour_newlines,                  &exp_hnl},
     {"ngts",    PRO_BOOL,                           false,      OFF, &settings.gettext_strings,                  &exp_gts},
     {"nfca",    PRO_BOOL,                            true,      OFF, &settings.format_comments,                  &exp_fca},
     {"nfc1",    PRO_BOOL,                            true,      OFF, &settings.format_col1_comments,             &exp_fc1},
     {"neei",    PRO_BOOL,                           false,      OFF, &settings.extra_expression_indent,          &exp_eei},
-    {"ndj",     PRO_BOOL,                           false,      OFF, &settings.ljust_decl,                       &exp_dj},
-    {"ncs",     PRO_BOOL,                            true,      OFF, &settings.cast_space,                       &exp_cs},
-    {"nce",     PRO_BOOL,                            true,      OFF, &settings.cuddle_else,                      &exp_ce},
+    {"ndj",     PRO_BOOL,                            true,      OFF, &settings.ljust_decl,                       &exp_dj},
+    {"ncs",     PRO_BOOL,                           false,      OFF, &settings.cast_space,                       &exp_cs},
+    {"nce",     PRO_BOOL,                           false,      OFF, &settings.cuddle_else,                      &exp_ce},
     {"ncdw",    PRO_BOOL,                           false,      OFF, &settings.cuddle_do_while,                  &exp_cdw},
-    {"ncdb",    PRO_BOOL,                            true,      OFF, &settings.comment_delimiter_on_blankline,   &exp_cdb},
+    {"ncdb",    PRO_BOOL,                           false,      OFF, &settings.comment_delimiter_on_blankline,   &exp_cdb},
     {"nbs",     PRO_BOOL,                           false,      OFF, &settings.blank_after_sizeof,               &exp_bs},
     {"nbfda",   PRO_BOOL,                           false,      OFF, &settings.break_function_decl_args,         &exp_bfda},
     {"nbfde",   PRO_BOOL,                           false,      OFF, &settings.break_function_decl_args_end,     &exp_bfde},
     {"nbc",     PRO_BOOL,                            true,       ON, &settings.leave_comma,                      &exp_bc},
-    {"nbbo",    PRO_BOOL,                            true,      OFF, &settings.break_before_boolean_operator,    &exp_bbo},
-    {"nbbb",    PRO_BOOL,                           false,      OFF, &settings.blanklines_before_blockcomments,  &exp_bbb},
+    {"nbbo",    PRO_BOOL,                           false,      OFF, &settings.break_before_boolean_operator,    &exp_bbo},
+    {"nbbb",    PRO_BOOL,                            true,      OFF, &settings.blanklines_before_blockcomments,  &exp_bbb},
     {"nbap",    PRO_BOOL,                           false,      OFF, &settings.blanklines_after_procs,           &exp_bap},
     {"nbadp",   PRO_BOOL,                           false,      OFF, &settings.blanklines_after_declarations_at_proctop,  &exp_badp},
     {"nbad",    PRO_BOOL,                           false,      OFF, &settings.blanklines_after_declarations,    &exp_bad},
     {"nbacc",   PRO_BOOL,                           false,      OFF, &settings.blanklines_around_conditional_compilation, &exp_bacc},
     {"linux",   PRO_SETTINGS,                           0, ONOFF_NA, LINUX_SETTINGS_IDX,                         &exp_linux},
     {"lps",     PRO_BOOL,                           false,       ON, &settings.leave_preproc_space,              &exp_lps},
-    {"lp",      PRO_BOOL,                            true,       ON, &settings.lineup_to_parens,                 &exp_lp},
+    {"lp",      PRO_BOOL,                           false,       ON, &settings.lineup_to_parens,                 &exp_lp},
     {"lc",      PRO_INT,     DEFAULT_RIGHT_COMMENT_MARGIN, ONOFF_NA, &settings.comment_max_col,                  &exp_lc},
     {"l",       PRO_INT,             DEFAULT_RIGHT_MARGIN, ONOFF_NA, &settings.max_col,                          &exp_l},
+/* This is now the default. */
+    {"knf",     PRO_SETTINGS,                           0, ONOFF_NA, KNF_SETTINGS_IDX,                           &exp_knf},
     {"kr",      PRO_SETTINGS,                           0, ONOFF_NA, KR_SETTINGS_IDX,                            &exp_kr},
-    {"ip",      PRO_INT,                                4, ONOFF_NA, &settings.indent_parameters,                &exp_ip},
-    {"i",       PRO_INT,                                4, ONOFF_NA, &settings.ind_size,                         &exp_i},
+    {"ip",      PRO_INT,                                8, ONOFF_NA, &settings.indent_parameters,                &exp_ip},
+    {"i",       PRO_INT,                                8, ONOFF_NA, &settings.ind_size,                         &exp_i},
     {"il",      PRO_INT,             DEFAULT_LABEL_INDENT, ONOFF_NA, &settings.label_offset,                     &exp_il},
     {"hnl",     PRO_BOOL,                            true,       ON, &settings.honour_newlines,                  &exp_hnl},
     {"h",       PRO_BOOL,                               0, ONOFF_NA, NULL,                                       NULL},
     {"gts",     PRO_BOOL,                           false,       ON, &settings.gettext_strings,                  &exp_gts},
     {"gnu",     PRO_SETTINGS,                           0, ONOFF_NA, GNU_SETTINGS_IDX,                           &exp_gnu},
     {"fnc",     PRO_BOOL,                           false,       ON, &settings.fix_nested_comments,              &exp_fnc},
-    {"fca",     PRO_BOOL,                            true,       ON, &settings.format_comments,                  &exp_fca},
-    {"fc1",     PRO_BOOL,                            true,       ON, &settings.format_col1_comments,             &exp_fc1},
+    {"fca",     PRO_BOOL,                           false,       ON, &settings.format_comments,                  &exp_fca},
+    {"fc1",     PRO_BOOL,                           false,       ON, &settings.format_col1_comments,             &exp_fc1},
     {"eei",     PRO_BOOL,                           false,       ON, &settings.extra_expression_indent,          &exp_eei},
     {"dj",      PRO_BOOL,                           false,       ON, &settings.ljust_decl,                       &exp_dj},
-    {"di",      PRO_INT,                               16, ONOFF_NA, &settings.decl_indent,                      &exp_di},
+    {"di",      PRO_INT,                                0, ONOFF_NA, &settings.decl_indent,                      &exp_di},
     {"d",       PRO_INT,                                0, ONOFF_NA, &settings.unindent_displace,                &exp_d},
-    {"cs",      PRO_BOOL,                            true,       ON, &settings.cast_space,                       &exp_cs},
+    {"cs",      PRO_BOOL,                           false,       ON, &settings.cast_space,                       &exp_cs},
     {"cp",      PRO_INT,                               33, ONOFF_NA, &settings.else_endif_col,                   &exp_cp},
     {"cli",     PRO_INT,                                0, ONOFF_NA, &settings.case_indent,                      &exp_cli},
     {"ci",      PRO_INT,                                4, ONOFF_NA, &settings.continuation_indent,              &exp_ci},
@@ -376,12 +388,12 @@ const pro_ty pro[] =
     {"bl",      PRO_BOOL,                            true,      OFF, &settings.btype_2,                          &exp_bl},
     {"bfda",    PRO_BOOL,                           false,       ON, &settings.break_function_decl_args,         &exp_bfda},
     {"bfde",    PRO_BOOL,                           false,       ON, &settings.break_function_decl_args_end,     &exp_bfde},
-    {"bc",      PRO_BOOL,                            true,      OFF, &settings.leave_comma,                      &exp_bc},
+    {"bc",      PRO_BOOL,                           false,      OFF, &settings.leave_comma,                      &exp_bc},
     {"bbo",     PRO_BOOL,                            true,       ON, &settings.break_before_boolean_operator,    &exp_bbo},
     {"bbb",     PRO_BOOL,                           false,       ON, &settings.blanklines_before_blockcomments,  &exp_bbb},
-    {"bap",     PRO_BOOL,                           false,       ON, &settings.blanklines_after_procs,           &exp_bap},
-    {"badp",    PRO_BOOL,                           false,       ON, &settings.blanklines_after_declarations_at_proctop,  &exp_badp},
-    {"bad",     PRO_BOOL,                           false,       ON, &settings.blanklines_after_declarations,    &exp_bad},
+    {"bap",     PRO_BOOL,                            true,       ON, &settings.blanklines_after_procs,           &exp_bap},
+    {"badp",    PRO_BOOL,                            true,       ON, &settings.blanklines_after_declarations_at_proctop,  &exp_badp},
+    {"bad",     PRO_BOOL,                            true,       ON, &settings.blanklines_after_declarations,    &exp_bad},
     {"bacc",    PRO_BOOL,                           false,       ON, &settings.blanklines_around_conditional_compilation, &exp_bacc},
     {"T",       PRO_KEY,                                0, ONOFF_NA, 0,                                          &exp_T},
     {"ppi",     PRO_INT,                                0, ONOFF_NA, &settings.force_preproc_width,              &exp_ppi},
@@ -468,6 +480,7 @@ const pro_ty pro[] =
     {"lp",      PRO_BOOL,                            true,       ON, &settings.lineup_to_parens,                 &exp_lp},
     {"lc",      PRO_INT,     DEFAULT_RIGHT_COMMENT_MARGIN, ONOFF_NA, &settings.comment_max_col,                  &exp_lc},
     {"l",       PRO_INT,             DEFAULT_RIGHT_MARGIN, ONOFF_NA, &settings.max_col,                          &exp_l},
+    {"knf",     PRO_SETTINGS,                           0, ONOFF_NA, KNF_SETTINGS_IDX,                           &exp_knf},
     {"kr",      PRO_SETTINGS,                           0, ONOFF_NA, KR_SETTINGS_IDX,                            &exp_kr},
     {"il",      PRO_INT,             DEFAULT_LABEL_INDENT, ONOFF_NA, &settings.label_offset,                     &exp_il},
     {"ip",      PRO_INT,                                5, ONOFF_NA, &settings.indent_parameters,                &exp_ip},
@@ -649,6 +662,9 @@ const long_option_conversion_ty option_conversions[] =
     {"blank-lines-after-declarations",              "bad"},
     {"blank-lines-after-commas",                    "bc"},
     {"blank-before-sizeof",                         "bs"},
+    {"berkeley-kernel-style",                       "knf"},
+    {"berkeley-kernel-normal-form",                 "knf"},
+    {"kernel-normal-form",                          "knf"},
     {"berkeley-style",                              "orig"},
     {"berkeley",                                    "orig"},
     {"Bill-Shannon",                                "bs"},
@@ -861,7 +877,7 @@ extern int set_option(
 
     if (!found)
     {
-        DieError(invocation_error, _("%s: unknown option \"%s\"\n"), option_source, option - 1);
+        DieError(invocation_error, _("%s: unknown option \"%s\"\n"), option_source, option - option_length);
     }
     else if (strlen(p->p_name) == 1 && *(p->p_name) == 'h')
     {
