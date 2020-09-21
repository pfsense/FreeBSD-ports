From b727c12aabf1afc2b6e33f8590c9786e349e4fcb Mon Sep 17 00:00:00 2001
From: Paul Manley <paul.manley@wholefoods.com>
Date: Thu, 9 Jul 2020 11:25:34 -0500
Subject: [PATCH] vtysh: properly exit BFD_PEER_NODE when marking file

vtysh needs to be aware of how to properly exit a bfd peer when subsequent commands only succeed in a higher context.

https://github.com/FRRouting/frr/issues/6511#issuecomment-656166206

Signed-off-by: Paul Manley <paul.manley@wholefoods.com>
---
 vtysh/vtysh.c | 3 +++
 1 file changed, 3 insertions(+)

diff --git a/vtysh/vtysh.c b/vtysh/vtysh.c
index 29e0842da..9e5885c7b 100644
--- vtysh/vtysh.c
+++ vtysh/vtysh.c
@@ -809,6 +809,9 @@ int vtysh_mark_file(const char *filename)
 			} else if ((prev_node == KEYCHAIN_KEY_NODE)
 				   && (tried == 1)) {
 				vty_out(vty, "exit\n");
+			} else if ((prev_node == BFD_PEER_NODE)
+				   && (tried == 1)) {
+				vty_out(vty, "exit\n");
 			} else if (tried) {
 				vty_out(vty, "end\n");
 			}
-- 
2.25.4
