--- configuration.c.orig	2003-01-08 05:58:19 UTC
+++ configuration.c
@@ -43,7 +43,7 @@ void    Get_conf(const char *File_name, member *My)
   char    my_local_host_name[255];
   static const size_t  my_local_host_name_len=255;
   struct  hostent         *hent;
-  int	  i, full;
+  int	  full;
   Num_prefer = 0;
 
   if (File_name && File_name[0] && (NULL != (fp = fopen(File_name,"r"))) )
