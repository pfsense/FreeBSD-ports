--- config.mk.orig	2019-07-29 18:58:54 UTC
+++ config.mk
@@ -20,10 +20,10 @@
 #
 # Uncomment the following lines and replaced "no" with "yes":
 #
-# CC      = clang
-# CFLAGS += -I/usr/local/include
-# MORELIBS += -L /usr/local/lib
-FREEBSD ?= no
+CC      = clang
+CFLAGS += -I/usr/local/include
+MORELIBS += -L /usr/local/lib
+FREEBSD ?= YES
 #
 # Now build recorder with the "gmake" command:
 #
@@ -57,10 +57,10 @@ WITH_GREENWICH ?= no
 
 # Where should the recorder store its data? This directory must
 # exist and be writeable by recorder (and readable by ocat)
-STORAGEDEFAULT = /var/spool/owntracks/recorder/store
+STORAGEDEFAULT = /var/db/owntracks/recorder/store
 
 # Where should the recorder find its document root (HTTP)?
-DOCROOT = /var/spool/owntracks/recorder/htdocs
+DOCROOT = /usr/local/www/ot-recorder
 
 # Define the precision for reverse-geo lookups. The higher
 # the number, the more granular reverse-geo will be:
@@ -83,7 +83,7 @@ GHASHPREC = 7
 JSON_INDENT ?= no
 
 # Location of optional default configuration file
-CONFIGFILE = /etc/default/ot-recorder
+CONFIGFILE = /usr/local/etc/ot-recorder/ot-recorder.conf
 
 # Optionally specify the path to the Mosquitto libs, include here
 MOSQUITTO_INC = -I/usr/include
