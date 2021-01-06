VirtualTerminal for Wayland is only present on Linux at the moment.
As we are only interested in an X11 session at the moment, we therefore
can hide it behind a Q_OS_LINUX.

Only set XDG_CURRENT_DESKTOP and XDG_SESSION_DESKTOP if the xsession .desktop
file defines DesktopNames.  Our xinitrc.desktop does not set it because that
may confuse the desktop session started from ~/.xinitrc.  These environment
variables are used for OnlyShowIn and NotShowIn in .desktop files.

--- src/daemon/Display.cpp.orig	2020-11-03 09:55:31 UTC
+++ src/daemon/Display.cpp
@@ -320,10 +320,12 @@ namespace SDDM {
         env.insert(QStringLiteral("XDG_SEAT_PATH"), daemonApp->displayManager()->seatPath(seat()->name()));
         env.insert(QStringLiteral("XDG_SESSION_PATH"), daemonApp->displayManager()->sessionPath(QStringLiteral("Session%1").arg(daemonApp->newSessionId())));
         env.insert(QStringLiteral("DESKTOP_SESSION"), session.desktopSession());
+        if (!session.desktopNames().isEmpty())
         env.insert(QStringLiteral("XDG_CURRENT_DESKTOP"), session.desktopNames());
         env.insert(QStringLiteral("XDG_SESSION_CLASS"), QStringLiteral("user"));
         env.insert(QStringLiteral("XDG_SESSION_TYPE"), session.xdgSessionType());
         env.insert(QStringLiteral("XDG_SEAT"), seat()->name());
+        if (!session.desktopNames().isEmpty())
         env.insert(QStringLiteral("XDG_SESSION_DESKTOP"), session.desktopNames());
 
         m_auth->insertEnvironment(env);
