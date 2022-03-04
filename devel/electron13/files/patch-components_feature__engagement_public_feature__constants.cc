--- components/feature_engagement/public/feature_constants.cc.orig	2021-07-15 19:13:37 UTC
+++ components/feature_engagement/public/feature_constants.cc
@@ -12,7 +12,7 @@ const base::Feature kIPHDemoMode{"IPH_DemoMode",
 const base::Feature kIPHDummyFeature{"IPH_Dummy",
                                      base::FEATURE_DISABLED_BY_DEFAULT};
 
-#if defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || \
+#if defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_BSD) || \
     defined(OS_CHROMEOS)
 const base::Feature kIPHDesktopTabGroupsNewGroupFeature{
     "IPH_DesktopTabGroupsNewGroup", base::FEATURE_DISABLED_BY_DEFAULT};
@@ -38,7 +38,7 @@ const base::Feature kIPHDesktopPwaInstallFeature{
     "IPH_DesktopPwaInstall", base::FEATURE_DISABLED_BY_DEFAULT};
 const base::Feature kIPHProfileSwitchFeature{"IPH_ProfileSwitch",
                                              base::FEATURE_DISABLED_BY_DEFAULT};
-#endif  // defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) ||
+#endif  // defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_BSD) ||
         // defined(OS_CHROMEOS)
 
 #if defined(OS_ANDROID)
