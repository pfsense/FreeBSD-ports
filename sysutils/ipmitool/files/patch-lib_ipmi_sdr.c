Description: Fix soensor reading
Author: mareedu srinivasa rao
Origin: upstream, https://sourceforge.net/p/ipmitool/bugs/490/
Bug: https://sourceforge.net/p/ipmitool/bugs/490/
Bug-debian: https://bugs.debian.org/cgi-bin/bugreport.cgi?bug=983082
Forwarded: not-needed
Last-Update: 2022-10-29
---
This patch header follows DEP-3: http://dep.debian.net/deps/dep3/
Index: lib/ipmi_sdr.c
===================================================================
--- lib/ipmi_sdr.c
+++ lib/ipmi_sdr.c
@@ -1799,7 +1799,7 @@ ipmi_sdr_print_sensor_fc(struct ipmi_int
 						      sr->s_a_units);
 			} else /* Discrete */
 				snprintf(sval, sizeof(sval),
-					"0x%02x", sr->s_reading);
+					"0x%02x", sr->s_data2);
 		}
 		else if (sr->s_scanning_disabled)
 			snprintf(sval, sizeof (sval), sr->full ? "disabled"   : "Not Readable");
Index: lib/ipmi_sensor.c
===================================================================
--- lib/ipmi_sensor.c
+++ lib/ipmi_sensor.c
@@ -201,7 +201,7 @@ ipmi_sensor_print_fc_discrete(struct ipm
 					       sr->s_a_str, sr->s_a_units, "ok");
 				} else {
 					printf("| 0x%-8x | %-10s | 0x%02x%02x",
-					       sr->s_reading, "discrete",
+					       sr->s_data2, "discrete",
 					       sr->s_data2, sr->s_data3);
 				}
 			} else {
