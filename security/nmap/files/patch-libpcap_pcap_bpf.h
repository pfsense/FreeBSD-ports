--- libpcap/pcap/bpf.h.orig	2021-12-10 21:01:02 UTC
+++ libpcap/pcap/bpf.h
@@ -67,6 +67,10 @@
  */
 #if !defined(_NET_BPF_H_) && !defined(_NET_BPF_H_INCLUDED) && !defined(_BPF_H_) && !defined(_H_BPF) && !defined(lib_pcap_bpf_h)
 #define lib_pcap_bpf_h
+#define _NET_BPF_H_
+#define _NET_BPF_H_INCLUDED
+#define _BPF_H_
+#define _H_BPF
 
 #include <pcap/funcattrs.h>
 
