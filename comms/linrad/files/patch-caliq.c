--- caliq.c.orig	2014-11-04 10:25:00 UTC
+++ caliq.c
@@ -144,7 +144,7 @@ for(j=0; j<mm; j+=2)
   }
 }
 
-void write_iq_foldcorr()
+void write_iq_foldcorr(void)
 {
 FILE *file;
 int i;
@@ -391,7 +391,7 @@ int i,j,k,m,n,mm,errskip;
 int seg,color;
 int ia,ib;
 int i,j,k,m,n,mm,errskip;
-float z[2*MAX_ADCHAN];
+float z[4*MAX_ADCHAN];
 float t1,t2,t3;
 caliq_clear_flag=TRUE;
 clear_fft1_filtercorr();
