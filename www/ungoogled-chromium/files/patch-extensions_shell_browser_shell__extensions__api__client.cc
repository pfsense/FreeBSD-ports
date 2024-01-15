--- extensions/shell/browser/shell_extensions_api_client.cc.orig	2022-10-01 07:40:07 UTC
+++ extensions/shell/browser/shell_extensions_api_client.cc
@@ -58,7 +58,7 @@ ShellExtensionsAPIClient::CreateDisplayInfoProvider() 
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD)
 FileSystemDelegate* ShellExtensionsAPIClient::GetFileSystemDelegate() {
   if (!file_system_delegate_)
     file_system_delegate_ = std::make_unique<ShellFileSystemDelegate>();
