--- lib/exabgp/reactor/network/outgoing.py.orig	2021-06-02 13:31:16 UTC
+++ lib/exabgp/reactor/network/outgoing.py
@@ -53,6 +53,8 @@ class Outgoing(Connection):
             connect(self.io, self.peer, self.port, self.afi, self.md5)
             return None
         except NetworkError as exc:
+            self.io.close()
+            self.io = None
             return exc
 
     def establish(self):
