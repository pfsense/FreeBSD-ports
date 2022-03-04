--- chrome/browser/chrome_browser_main_linux.cc.orig	2021-07-19 18:45:08 UTC
+++ chrome/browser/chrome_browser_main_linux.cc
@@ -47,7 +47,7 @@ ChromeBrowserMainPartsLinux::~ChromeBrowserMainPartsLi
 }
 
 void ChromeBrowserMainPartsLinux::PreProfileInit() {
-#if !BUILDFLAG(IS_CHROMEOS_ASH)
+#if !BUILDFLAG(IS_CHROMEOS_ASH) && !defined(OS_BSD)
   // Needs to be called after we have chrome::DIR_USER_DATA and
   // g_browser_process.  This happens in PreCreateThreads.
   // base::GetLinuxDistro() will initialize its value if needed.
@@ -78,7 +78,7 @@ void ChromeBrowserMainPartsLinux::PreProfileInit() {
 }
 
 void ChromeBrowserMainPartsLinux::PostCreateMainMessageLoop() {
-#if !BUILDFLAG(IS_CHROMEOS_ASH)
+#if !BUILDFLAG(IS_CHROMEOS_ASH) && !defined(OS_BSD)
   bluez::BluezDBusManager::Initialize(nullptr /* system_bus */);
 #endif
 
@@ -86,7 +86,7 @@ void ChromeBrowserMainPartsLinux::PostCreateMainMessag
 }
 
 void ChromeBrowserMainPartsLinux::PostDestroyThreads() {
-#if !BUILDFLAG(IS_CHROMEOS_ASH)
+#if !BUILDFLAG(IS_CHROMEOS_ASH) && !defined(OS_BSD)
   bluez::BluezDBusManager::Shutdown();
   bluez::BluezDBusThreadManager::Shutdown();
 #endif
