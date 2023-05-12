--- net/cert/cert_verify_proc.cc.orig	2023-03-30 00:33:54 UTC
+++ net/cert/cert_verify_proc.cc
@@ -414,7 +414,7 @@ base::Value CertVerifyParams(X509Certificate* cert,
 
 }  // namespace
 
-#if !(BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS))
+#if !(BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD))
 // static
 scoped_refptr<CertVerifyProc> CertVerifyProc::CreateSystemVerifyProc(
     scoped_refptr<CertNetFetcher> cert_net_fetcher) {
