--- if_re.c.orig	2020-06-18 16:22:59 UTC
+++ if_re.c
@@ -62,8 +62,8 @@ __FBSDID("$FreeBSD: src/sys/dev/re/if_re.c,v " RE_VERS
 #include <sys/malloc.h>
 #include <sys/kernel.h>
 #include <sys/socket.h>
+#include <sys/sysctl.h>
 #include <sys/taskqueue.h>
-#include <sys/random.h>
 
 #include <net/if.h>
 #include <net/if_var.h>
@@ -85,7 +85,7 @@ __FBSDID("$FreeBSD: src/sys/dev/re/if_re.c,v " RE_VERS
 #include <sys/endian.h>
 
 #include <dev/mii/mii.h>
-#include <dev/re/if_rereg.h>
+#include "if_rereg.h"
 #ifdef ENABLE_FIBER_SUPPORT
 #include <dev/re/if_fiber.h>
 #endif //ENABLE_FIBER_SUPPORT
@@ -258,34 +258,48 @@ static void re_hw_start_unlock(struct re_softc *sc);
 static void re_hw_start_unlock_8125(struct re_softc *sc);
 
 /* Tunables. */
+SYSCTL_NODE(_hw, OID_AUTO, re, CTLFLAG_RW | CTLFLAG_MPSAFE, 0, "");
 static int msi_disable = 1;
-TUNABLE_INT("hw.re.msi_disable", &msi_disable);
+SYSCTL_INT(_hw_re, OID_AUTO, msi_disable, CTLFLAG_RDTUN, &msi_disable, 0,
+    "");
 static int msix_disable = 0;
-TUNABLE_INT("hw.re.msix_disable", &msix_disable);
+SYSCTL_INT(_hw_re, OID_AUTO, msix_disable, CTLFLAG_RDTUN, &msix_disable, 0,
+    "");
 static int prefer_iomap = 0;
-TUNABLE_INT("hw.re.prefer_iomap", &prefer_iomap);
+SYSCTL_INT(_hw_re, OID_AUTO, prefer_iomap, CTLFLAG_RDTUN, &prefer_iomap, 0,
+    "");
 #ifdef ENABLE_EEE
 static int eee_enable = 1;
 #else
 static int eee_enable = 0;
 #endif
-TUNABLE_INT("hw.re.eee_enable", &eee_enable);
+SYSCTL_INT(_hw_re, OID_AUTO, eee_enable, CTLFLAG_RDTUN, &eee_enable, 0,
+    "");
 static int phy_power_saving = 1;
-TUNABLE_INT("hw.re.phy_power_saving", &phy_power_saving);
+SYSCTL_INT(_hw_re, OID_AUTO, phy_power_saving, CTLFLAG_RDTUN,
+    &phy_power_saving, 0,
+    "");
 static int phy_mdix_mode = RE_ETH_PHY_AUTO_MDI_MDIX;
-TUNABLE_INT("hw.re.phy_mdix_mode", &phy_mdix_mode);
+SYSCTL_INT(_hw_re, OID_AUTO, phy_mdix_mode, CTLFLAG_RDTUN, &phy_mdix_mode, 0,
+    "");
 #ifdef ENABLE_S5WOL
 static int s5wol = 1;
 #else
 static int s5wol = 0;
-TUNABLE_INT("hw.re.s5wol", &s5wol);
+SYSCTL_INT(_hw_re, OID_AUTO, s5wol, CTLFLAG_RDTUN, &s5wol, 0,
+    "");
 #endif
 #ifdef ENABLE_S0_MAGIC_PACKET
 static int s0_magic_packet = 1;
 #else
 static int s0_magic_packet = 0;
 #endif
-TUNABLE_INT("hw.re.s0_magic_packet", &s0_magic_packet);
+SYSCTL_INT(_hw_re, OID_AUTO, s0_magic_packet, CTLFLAG_RDTUN,
+    &s0_magic_packet, 0,
+    "");
+static int max_rx_mbuf_sz = MJUM9BYTES;
+SYSCTL_INT(_hw_re, OID_AUTO, max_rx_mbuf_sz, CTLFLAG_RDTUN, &max_rx_mbuf_sz, 0,
+    "");
 
 #define RE_CSUM_FEATURES    (CSUM_IP | CSUM_TCP | CSUM_UDP)
 
@@ -930,6 +944,7 @@ static int re_alloc_buf(struct re_softc *sc)
         int error =0;
         int i,size;
 
+	RE_UNLOCK(sc);
         error = bus_dma_tag_create(sc->re_parent_tag, 1, 0,
                                    BUS_SPACE_MAXADDR, BUS_SPACE_MAXADDR, NULL,
                                    NULL, MCLBYTES* RE_NTXSEGS, RE_NTXSEGS, 4096, 0,
@@ -938,6 +953,7 @@ static int re_alloc_buf(struct re_softc *sc)
         if (error) {
                 //device_printf(dev,"re_tx_mtag fail\n");
                 //goto fail;
+		RE_LOCK(sc);
                 return error;
         }
 
@@ -955,9 +971,11 @@ static int re_alloc_buf(struct re_softc *sc)
         if (error) {
                 //device_printf(dev,"re_rx_mtag fail\n");
                 //goto fail;
+		RE_LOCK(sc);
                 return error;
         }
 
+	RE_LOCK(sc);
         if (sc->re_rx_mbuf_sz <= MCLBYTES)
                 size = MCLBYTES;
         else if (sc->re_rx_mbuf_sz <=  MJUMPAGESIZE)
@@ -3428,16 +3446,6 @@ is_valid_ether_addr(const u_int8_t * addr)
         return !is_multicast_ether_addr(addr) && !is_zero_ether_addr(addr);
 }
 
-static inline void
-random_ether_addr(u_int8_t * dst)
-{
-        if (read_random(dst, 6) == 0)
-                arc4rand(dst, 6, 0);
-
-        dst[0] &= 0xfe;
-        dst[0] |= 0x02;
-}
-
 static void re_disable_now_is_oob(struct re_softc *sc)
 {
         if (sc->re_hw_supp_now_is_oob_ver == 1)
@@ -3889,7 +3897,7 @@ static void re_get_hw_mac_address(struct re_softc *sc,
 
         if (!is_valid_ether_addr(eaddr)) {
                 device_printf(dev,"Invalid ether addr: %6D\n", eaddr, ":");
-                random_ether_addr(eaddr);
+                ether_gen_addr(sc->re_ifp, (struct ether_addr *)eaddr);
                 device_printf(dev,"Random ether addr: %6D\n", eaddr, ":");
         }
 
@@ -4291,9 +4299,9 @@ static void re_init_software_variable(struct re_softc 
 
         sc->re_rx_mbuf_sz = sc->max_jumbo_frame_size + ETHER_VLAN_ENCAP_LEN + ETHER_HDR_LEN + ETHER_CRC_LEN + RE_ETHER_ALIGN + 1;
 
-        if (sc->re_rx_mbuf_sz > MJUM9BYTES) {
-                sc->max_jumbo_frame_size -= (sc->re_rx_mbuf_sz - MJUM9BYTES);
-                sc->re_rx_mbuf_sz = MJUM9BYTES;
+        if (sc->re_rx_mbuf_sz > max_rx_mbuf_sz) {
+                sc->max_jumbo_frame_size -= (sc->re_rx_mbuf_sz - max_rx_mbuf_sz);
+                sc->re_rx_mbuf_sz = max_rx_mbuf_sz;
         }
 
         switch(sc->re_type) {
@@ -7073,12 +7081,11 @@ static void re_init_unlock(void *xsc)  	/* Software & 
         return;
 }
 
-static void re_init(void *xsc)  	/* Software & Hardware Initialize */
+static void re_init_locked(void *xsc)
 {
         struct re_softc		*sc = xsc;
         struct ifnet		*ifp;
 
-        RE_LOCK(sc);
         ifp = RE_GET_IFNET(sc);
 
         if (re_link_ok(sc)) {
@@ -7089,7 +7096,14 @@ static void re_init(void *xsc)  	/* Software & Hardwar
 
         sc->re_link_chg_det = 1;
         re_start_timer(sc);
+}
 
+static void re_init(void *xsc)  	/* Software & Hardware Initialize */
+{
+        struct re_softc		*sc = xsc;
+
+        RE_LOCK(sc);
+	re_init_locked(sc);
         RE_UNLOCK(sc);
 }
 
@@ -8438,7 +8452,7 @@ static void re_int_task(void *arg, int npending)
                         if ((status & RE_ISR_FIFO_OFLOW) &&
                             (!(status & (RE_ISR_RX_OK | RE_ISR_TX_OK | RE_ISR_RX_OVERRUN)))) {
                                 re_reset(sc);
-                                re_init(sc);
+                                re_init_locked(sc);
                                 sc->rx_fifo_overflow = 0;
                                 CSR_WRITE_2(sc, RE_ISR, RE_ISR_FIFO_OFLOW);
                         }
@@ -8449,7 +8463,7 @@ static void re_int_task(void *arg, int npending)
 
         if (status & RE_ISR_SYSTEM_ERR) {
                 re_reset(sc);
-                re_init(sc);
+                re_init_locked(sc);
         }
 
         switch(sc->re_type) {
@@ -8514,7 +8528,7 @@ static void re_int_task_8125(void *arg, int npending)
 
         if (status & RE_ISR_SYSTEM_ERR) {
                 re_reset(sc);
-                re_init(sc);
+                re_init_locked(sc);
         }
 
         RE_UNLOCK(sc);
@@ -8614,6 +8628,22 @@ struct re_softc		*sc;
         return;
 }
 
+#if OS_VER >= VERSION(13,0)
+static u_int
+re_hash_maddr(void *arg, struct sockaddr_dl *sdl, u_int cnt)
+{
+	uint32_t h, *hashes = arg;
+
+	h = ether_crc32_be(LLADDR(sdl), ETHER_ADDR_LEN) >> 26;
+	if (h < 32)
+		hashes[0] |= (1 << h);
+	else
+		hashes[1] |= (1 << (h - 32));
+
+	return (1);
+}
+#endif
+
 /*
  * Program the 64-bit multicast hash filter.
  */
@@ -8623,7 +8653,9 @@ struct re_softc		*sc;
         struct ifnet		*ifp;
         int			h = 0;
         u_int32_t		hashes[2] = { 0, 0 };
+#if OS_VER < VERSION(13,0)
         struct ifmultiaddr	*ifma;
+#endif
         u_int32_t		rxfilt;
         int			mcnt = 0;
 
@@ -8640,7 +8672,12 @@ struct re_softc		*sc;
         }
 
         /* now program new ones */
-#if OS_VER > VERSION(6,0)
+#if OS_VER >= VERSION(13,0)
+	mcnt = if_foreach_llmaddr(ifp, re_hash_maddr, hashes);
+#else
+#if OS_VER >= VERSION(12,0)
+	if_maddr_rlock(ifp);
+#elif OS_VER > VERSION(6,0)
         IF_ADDR_LOCK(ifp);
 #endif
 #if OS_VER < VERSION(4,9)
@@ -8662,9 +8699,12 @@ struct re_softc		*sc;
                         hashes[1] |= (1 << (h - 32));
                 mcnt++;
         }
-#if OS_VER > VERSION(6,0)
+#if OS_VER >= VERSION(12,0)
+	if_maddr_runlock(ifp);
+#elif OS_VER > VERSION(6,0)
         IF_ADDR_UNLOCK(ifp);
 #endif
+#endif
 
         if (mcnt) {
                 if ((sc->re_if_flags & RL_FLAG_PCIE) != 0) {
@@ -8720,7 +8760,7 @@ caddr_t			data;
                                 error =re_alloc_buf(sc);
 
                                 if (error == 0) {
-                                        re_init(sc);
+                                        re_init_locked(sc);
                                 }
                                 RE_UNLOCK(sc);
 
@@ -8743,7 +8783,7 @@ caddr_t			data;
         case SIOCSIFFLAGS:
                 RE_LOCK(sc);
                 if (ifp->if_flags & IFF_UP) {
-                        re_init(sc);
+                        re_init_locked(sc);
                 } else if (ifp->if_drv_flags & IFF_DRV_RUNNING) {
                         re_stop(sc);
                 }
