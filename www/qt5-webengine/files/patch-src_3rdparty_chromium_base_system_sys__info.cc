--- src/3rdparty/chromium/base/system/sys_info.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/system/sys_info.cc
@@ -104,7 +104,7 @@ void SysInfo::GetHardwareInfo(base::OnceCallback<void(
 #elif defined(OS_ANDROID) || defined(OS_APPLE)
   base::ThreadPool::PostTaskAndReplyWithResult(
       FROM_HERE, {}, base::BindOnce(&GetHardwareInfoSync), std::move(callback));
-#elif defined(OS_LINUX) || defined(OS_CHROMEOS)
+#elif defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   base::ThreadPool::PostTaskAndReplyWithResult(
       FROM_HERE, {base::MayBlock()}, base::BindOnce(&GetHardwareInfoSync),
       std::move(callback));
