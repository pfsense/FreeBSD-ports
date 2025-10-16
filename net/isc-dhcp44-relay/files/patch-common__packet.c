--- common/packet.c.orig	2020-09-17 06:51:06.550928000 +0200
+++ common/packet.c	2020-09-17 06:52:05.628196000 +0200
@@ -201,6 +201,14 @@
  * Doesn't support infiniband yet as the supported oses shouldn't get here
  */
 
+#define VIRTUAL_HEADER_SIZE	4
+ssize_t decode_virtual_header (from)
+     struct hardware *from;
+{
+	from -> hlen = 0;
+	return VIRTUAL_HEADER_SIZE;
+}
+
 ssize_t decode_hw_header (interface, buf, bufix, from)
      struct interface_info *interface;
      unsigned char *buf;
@@ -219,6 +227,8 @@
 	case HTYPE_INFINIBAND:
 		log_error("Attempt to decode hw header for infiniband");
 		return (0);
+	case HTYPE_VIRTUAL:
+		return (decode_virtual_header(from));
 	case HTYPE_ETHER:
 	default:
 		return (decode_ethernet_header(interface, buf, bufix, from));

