--- if_re.c.orig	2024-06-04 09:39:04 UTC
+++ if_re.c
@@ -1267,7 +1267,7 @@ static void set_rxbufsize(struct re_softc *sc)
 {
         struct ifnet		*ifp;
         ifp = RE_GET_IFNET(sc);
-        sc->re_rx_desc_buf_sz = (ifp->if_mtu > ETHERMTU) ? ifp->if_mtu: ETHERMTU;
+        sc->re_rx_desc_buf_sz = (if_getmtu(ifp) > ETHERMTU) ? if_getmtu(ifp) : ETHERMTU;
         sc->re_rx_desc_buf_sz += (ETHER_VLAN_ENCAP_LEN + ETHER_HDR_LEN + ETHER_CRC_LEN);
         if (!(sc->re_if_flags & RL_FLAG_8168G_PLUS) ||
             sc->re_type == MACFG_56 || sc->re_type == MACFG_57 ||
@@ -4559,7 +4559,7 @@ re_sysctl_driver_variable(SYSCTL_HANDLER_ARGS)
                 printf("%s Driver Variables:\n", device_get_nameunit(sc->dev));
 
                 printf("driver version\t%s\n", RE_VERSION);
-                printf("if_drv_flags\t0x%08x\n", sc->re_ifp->if_drv_flags);
+                printf("if_drv_flags\t0x%08x\n", if_getdrvflags(sc->re_ifp));
                 printf("re_type\t%d\n", sc->re_type);
                 printf("re_res_id\t%d\n", sc->re_res_id);
                 printf("re_res_type\t%d\n", sc->re_res_type);
@@ -4619,7 +4619,7 @@ re_sysctl_driver_variable(SYSCTL_HANDLER_ARGS)
 #elif OS_VER < VERSION(7,0)
                 printf("dev_addr\t%6D\n", IFP2ENADDR(sc->re_ifp), ":");
 #else
-                printf("dev_addr\t%6D\n", IF_LLADDR(sc->re_ifp), ":");
+                printf("dev_addr\t%6D\n", if_getlladdr(sc->re_ifp), ":");
 #endif
                 printf("msi_disable\t%d\n", msi_disable);
                 printf("msix_disable\t%d\n", msix_disable);
@@ -4659,7 +4659,7 @@ re_sysctl_stats(SYSCTL_HANDLER_ARGS)
                 extend_stats = false;
                 if (sc->HwSuppExtendTallyCounterVer > 0)
                         extend_stats = true;
-                if ((sc->re_ifp->if_drv_flags & IFF_DRV_RUNNING) == 0) {
+                if ((if_getdrvflags(sc->re_ifp) & IFF_DRV_RUNNING) == 0) {
                         RE_UNLOCK(sc);
                         goto done;
                 }
@@ -5519,54 +5519,54 @@ static int re_attach(device_t dev)
                 goto fail;
         }
 #endif
-        ifp->if_softc = sc;
+        if_setsoftc(ifp, sc);
 #if OS_VER < VERSION(5,3)
         ifp->if_unit = unit;
         ifp->if_name = "re";
 #else
         if_initname(ifp, device_get_name(dev), device_get_unit(dev));
 #endif
-        ifp->if_mtu = ETHERMTU;
-        ifp->if_flags = IFF_BROADCAST | IFF_SIMPLEX | IFF_MULTICAST;
-        ifp->if_ioctl = re_ioctl;
-        ifp->if_output = ether_output;
-        ifp->if_start = re_start;
+        if_setmtu(ifp, ETHERMTU);
+        if_setflags(ifp, IFF_BROADCAST | IFF_SIMPLEX | IFF_MULTICAST);
+        if_setioctlfn(ifp, re_ioctl);
+        if_setoutputfn(ifp, ether_output);
+        if_setstartfn(ifp, re_start);
 #if OS_VER < VERSION(7,0)
         ifp->if_watchdog = re_watchdog;
 #endif
         if ((sc->re_type == MACFG_24) || (sc->re_type == MACFG_25) || (sc->re_type == MACFG_26))
-                ifp->if_hwassist |= CSUM_TCP | CSUM_UDP;
+                if_sethwassistbits(ifp, CSUM_TCP | CSUM_UDP, 0);
         else
-                ifp->if_hwassist |= RE_CSUM_FEATURES;
-        ifp->if_capabilities = IFCAP_HWCSUM | IFCAP_HWCSUM_IPV6;
+                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
+        if_setcapabilitiesbit(ifp, IFCAP_HWCSUM | IFCAP_HWCSUM_IPV6, 0);
         /* TSO capability setup */
         if (sc->re_if_flags & RL_FLAG_8168G_PLUS) {
-                ifp->if_hwassist |= CSUM_TSO;
-                ifp->if_capabilities |= IFCAP_TSO;
+                if_sethwassistbits(ifp, CSUM_TSO, 0);
+                if_setcapabilitiesbit(ifp, IFCAP_TSO, 0);
         }
         /* RTL8169/RTL8101E/RTL8168B not support TSO v6 */
         if (!(sc->re_if_flags & RL_FLAG_DESCV2)) {
-                ifp->if_hwassist &= ~(CSUM_IP6_TSO |
+                if_sethwassistbits(ifp, 0, CSUM_IP6_TSO |
                                       CSUM_TCP_IPV6 |
                                       CSUM_UDP_IPV6);
-                ifp->if_capabilities &= ~(IFCAP_TSO6 | IFCAP_HWCSUM_IPV6);
+                if_setcapabilitiesbit(ifp, 0, IFCAP_TSO6 | IFCAP_HWCSUM_IPV6);
         }
-        ifp->if_init = re_init;
+        if_setinitfn(ifp, re_init);
         /* VLAN capability setup */
-        ifp->if_capabilities |= IFCAP_VLAN_MTU | IFCAP_VLAN_HWTAGGING;
+        if_setcapabilitiesbit(ifp, IFCAP_VLAN_MTU | IFCAP_VLAN_HWTAGGING, 0);
         /* LRO capability setup */
-        ifp->if_capabilities |= IFCAP_LRO;
+        if_setcapabilitiesbit(ifp, IFCAP_LRO, 0);
 
         /* Enable WOL if PM is supported. */
         if (pci_find_cap(sc->dev, PCIY_PMG, &reg) == 0)
-                ifp->if_capabilities |= IFCAP_WOL;
-        ifp->if_capenable = ifp->if_capabilities;
-        ifp->if_capenable &= ~(IFCAP_WOL_UCAST | IFCAP_WOL_MCAST);
+                if_setcapabilitiesbit(ifp, IFCAP_WOL, 0);
+        if_setcapenablebit(ifp, if_getcapabilities(ifp), 0);
+        if_setcapenablebit(ifp, 0, IFCAP_WOL_UCAST | IFCAP_WOL_MCAST);
         /*
          * Default disable ipv6 tso.
          */
-        ifp->if_hwassist &= ~CSUM_IP6_TSO;
-        ifp->if_capenable &= ~IFCAP_TSO6;
+        if_sethwassistbits(ifp, 0, CSUM_IP6_TSO);
+        if_setcapenablebit(ifp, 0, IFCAP_TSO6);
 
         /* Not enable LRO for OS version lower than 11.0 */
 #if OS_VER < VERSION(11,0)
@@ -5615,26 +5615,25 @@ static int re_attach(device_t dev)
 
         switch(sc->re_device_id) {
         case RT_DEVICEID_8126:
-                ifp->if_baudrate = 50000000000;
+                if_setbaudrate(ifp, 50000000000);
                 break;
         case RT_DEVICEID_8125:
         case RT_DEVICEID_3000:
-                ifp->if_baudrate = 25000000000;
+                if_setbaudrate(ifp, 25000000000);
                 break;
         case RT_DEVICEID_8169:
         case RT_DEVICEID_8169SC:
         case RT_DEVICEID_8168:
         case RT_DEVICEID_8161:
         case RT_DEVICEID_8162:
-                ifp->if_baudrate = 1000000000;
+                if_setbaudrate(ifp, 1000000000);
                 break;
         default:
-                ifp->if_baudrate = 100000000;
+                if_setbaudrate(ifp, 100000000);
                 break;
         }
-        IFQ_SET_MAXLEN(&ifp->if_snd, IFQ_MAXLEN);
-        ifp->if_snd.ifq_drv_maxlen = IFQ_MAXLEN;
-        IFQ_SET_READY(&ifp->if_snd);
+        if_setsendqlen(ifp, IFQ_MAXLEN);
+        if_setsendqready(ifp);
 
         switch (sc->re_type) {
         case MACFG_80:
@@ -5996,7 +5995,7 @@ re_resume(device_t dev)
         RE_LOCK(sc);
         sc->ifmedia_upd(ifp);
         sc->suspended = 0;
-        if (ifp->if_flags & IFF_UP) {
+        if (if_getflags(ifp) & IFF_UP) {
                 sc->re_link_chg_det = 1;
                 re_start_timer(sc);
         }
@@ -6100,7 +6099,7 @@ static int re_shutdown(device_t dev)	/* The same with 
         } else {
                 struct ifnet            *ifp;
                 ifp = RE_GET_IFNET(sc);
-                ifp->if_capenable = IFCAP_WOL_MAGIC;
+                if_setcapabilitiesbit(ifp, IFCAP_WOL_MAGIC, 0);
                 re_setwol(sc);
         }
 
@@ -6123,7 +6122,7 @@ static void re_set_eee_lpi_timer(struct re_softc *sc)
         case MACFG_74:
         case MACFG_75:
         case MACFG_76:
-                re_mac_ocp_write(sc, RE_EEE_TXIDLE_TIMER_8168, ifp->if_mtu + ETHER_HDR_LEN + 0x20);
+                re_mac_ocp_write(sc, RE_EEE_TXIDLE_TIMER_8168, if_getmtu(ifp) + ETHER_HDR_LEN + 0x20);
                 break;
         case MACFG_80:
         case MACFG_81:
@@ -6136,7 +6135,7 @@ static void re_set_eee_lpi_timer(struct re_softc *sc)
         case MACFG_90:
         case MACFG_91:
         case MACFG_92:
-                CSR_WRITE_2(sc, RE_EEE_TXIDLE_TIMER_8125, ifp->if_mtu + ETHER_HDR_LEN + 0x20);
+                CSR_WRITE_2(sc, RE_EEE_TXIDLE_TIMER_8125, if_getmtu(ifp) + ETHER_HDR_LEN + 0x20);
                 break;
         default:
                 break;
@@ -6243,7 +6242,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_2 (sc, RE_CPlusCmd, 0x2060);
                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_0);
 
-                if (ifp->if_mtu > ETHERMTU) {
+                if (if_getmtu(ifp) > ETHERMTU) {
                         data8 = pci_read_config(sc->dev, 0x69, 1);
                         data8 &= ~0x70;
                         data8 |= 0x28;
@@ -6258,7 +6257,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_2 (sc, RE_CPlusCmd, 0x2060);
                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_0);
 
-                if (ifp->if_mtu > ETHERMTU) {
+                if (if_getmtu(ifp) > ETHERMTU) {
                         data8 = pci_read_config(sc->dev, 0x69, 1);
                         data8 &= ~0x70;
                         data8 |= 0x28;
@@ -6342,27 +6341,27 @@ static void re_hw_start_unlock(struct re_softc *sc)
                         data16 = re_ephy_read(sc, 0x06) & ~0x0080;
                         re_ephy_write(sc, 0x06, data16);
 
-                        if (ifp->if_mtu > ETHERMTU) {
+                        if (if_getmtu(ifp) > ETHERMTU) {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1 << 1)); //Jumbo_en1
 
                                 re_set_offset79(sc, 0x20);
-                                ifp->if_capenable &= ~IFCAP_HWCSUM;
+                                if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                         } else {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1 << 1)); //Jumbo_en1
                                 re_set_offset79(sc, 0x40);
                                 if (sc->re_tx_cstag) {
-                                        ifp->if_capenable |= IFCAP_TXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
                                         if ((sc->re_type == MACFG_24) || (sc->re_type == MACFG_25) || (sc->re_type == MACFG_26))
-                                                ifp->if_hwassist |= CSUM_TCP | CSUM_UDP;
+                                                if_sethwassistbits(ifp, CSUM_TCP | CSUM_UDP, 0);
                                         else
-                                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                                 }
                                 if (sc->re_rx_cstag) {
-                                        ifp->if_capenable |= IFCAP_RXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                                 }
                         }
@@ -6374,52 +6373,52 @@ static void re_hw_start_unlock(struct re_softc *sc)
                         data16 |= 0x0220;
                         re_ephy_write(sc, 0x03, data16);
 
-                        if (ifp->if_mtu > ETHERMTU) {
+                        if (if_getmtu(ifp) > ETHERMTU) {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1<<1)); //Jumbo_en1
 
                                 re_set_offset79(sc, 0x20);
-                                ifp->if_capenable &= ~IFCAP_HWCSUM;
+                                if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                         } else {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1<<1)); //Jumbo_en1
                                 re_set_offset79(sc, 0x40);
                                 if (sc->re_tx_cstag) {
-                                        ifp->if_capenable |= IFCAP_TXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
                                         if ((sc->re_type == MACFG_24) || (sc->re_type == MACFG_25) || (sc->re_type == MACFG_26))
-                                                ifp->if_hwassist |= CSUM_TCP | CSUM_UDP;
+                                                if_sethwassistbits(ifp, CSUM_TCP | CSUM_UDP, 0);
                                         else
-                                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                                 }
                                 if (sc->re_rx_cstag) {
-                                        ifp->if_capenable |= IFCAP_RXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                                 }
                         }
                 } else if (sc->re_type == MACFG_26) {
-                        if (ifp->if_mtu > ETHERMTU) {
+                        if (if_getmtu(ifp) > ETHERMTU) {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1<<1)); //Jumbo_en1
 
                                 re_set_offset79(sc, 0x20);
-                                ifp->if_capenable &= ~IFCAP_HWCSUM;
+                                if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                         } else {
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1<<1)); //Jumbo_en1
                                 re_set_offset79(sc, 0x40);
                                 if (sc->re_tx_cstag) {
-                                        ifp->if_capenable |= IFCAP_TXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
                                         if ((sc->re_type == MACFG_24) || (sc->re_type == MACFG_25) || (sc->re_type == MACFG_26))
-                                                ifp->if_hwassist |= CSUM_TCP | CSUM_UDP;
+                                                if_sethwassistbits(ifp, CSUM_TCP | CSUM_UDP, 0);
                                         else
-                                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                                 }
                                 if (sc->re_rx_cstag) {
-                                        ifp->if_capenable |= IFCAP_RXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                                 }
                         }
@@ -6438,24 +6437,24 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 if (sc->re_type == MACFG_28)
                         CSR_WRITE_1(sc, 0xD1, 0x20);
 
-                if (ifp->if_mtu > ETHERMTU) {
+                if (if_getmtu(ifp) > ETHERMTU) {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1<<1)); //Jumbo_en1
 
                         re_set_offset79(sc, 0x20);
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                        if_sethwassistbits(ifp, 0, ~RE_CSUM_FEATURES);
                 } else {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1<<1)); //Jumbo_en1
                         re_set_offset79(sc, 0x40);
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                         }
                 }
@@ -6475,24 +6474,24 @@ static void re_hw_start_unlock(struct re_softc *sc)
 
                 CSR_WRITE_2 (sc, RE_CPlusCmd, 0x2060);
 
-                if (ifp->if_mtu > ETHERMTU) {
+                if (if_getmtu(ifp) > ETHERMTU) {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1<<1)); //Jumbo_en1
 
                         re_set_offset79(sc, 0x20);
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1<<1)); //Jumbo_en1
                         re_set_offset79(sc, 0x40);
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                         }
                 }
@@ -6543,24 +6542,24 @@ static void re_hw_start_unlock(struct re_softc *sc)
 
                 CSR_WRITE_2 (sc, RE_CPlusCmd, 0x2060);
 
-                if (ifp->if_mtu > ETHERMTU) {
+                if (if_getmtu(ifp) > ETHERMTU) {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) | (1<<1)); //Jumbo_en1
 
                         re_set_offset79(sc, 0x20);
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2); //Jumbo_en0
                         CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) & ~(1<<1)); //Jumbo_en1
                         re_set_offset79(sc, 0x40);
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                         }
                 }
@@ -6651,14 +6650,14 @@ static void re_hw_start_unlock(struct re_softc *sc)
                         data16 |= 0x0040;
                         re_ephy_write(sc, 0x0A, data16);
 
-                        if (ifp->if_mtu > ETHERMTU) {
+                        if (if_getmtu(ifp) > ETHERMTU) {
                                 CSR_WRITE_1 (sc, RE_MTPS, 0x24);
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) | BIT_2);
                                 CSR_WRITE_1(sc, RE_CFG4, CSR_READ_1(sc, RE_CFG4) |0x01);
                                 re_set_offset79(sc, 0x20);
-                                ifp->if_capenable &= ~IFCAP_HWCSUM;
+                                if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
                                 CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) & ~RL_RxChkSum);
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                         } else {
                                 CSR_WRITE_1 (sc, RE_MTPS, 0x0c);
                                 CSR_WRITE_1(sc, RE_CFG3, CSR_READ_1(sc, RE_CFG3) & ~BIT_2);
@@ -6666,11 +6665,11 @@ static void re_hw_start_unlock(struct re_softc *sc)
                                 re_set_offset79(sc, 0x40);
 
                                 if (sc->re_tx_cstag) {
-                                        ifp->if_capenable |= IFCAP_TXCSUM;
-                                        ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                        if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                        if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                                 }
                                 if (sc->re_rx_cstag) {
-                                        ifp->if_capenable |= IFCAP_RXCSUM;
+                                        if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                                         CSR_WRITE_2 (sc, RE_CPlusCmd,CSR_READ_2(sc, RE_CPlusCmd) |RL_RxChkSum);
                                 }
                         }
@@ -6749,10 +6748,10 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_1(sc, 0xD0, CSR_READ_1(sc, 0xD0) | BIT_6);
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) | BIT_6);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
-                ifp->if_capenable &= ~IFCAP_HWCSUM;
-                ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if_setcapenablebit(ifp, 0, IFCAP_TXCSUM);
+                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
         } else if (macver == 0x24000000) {
                 if (pci_read_config(sc->dev, 0x81, 1)==1) {
                         CSR_WRITE_1(sc, RE_DBG_reg, 0x98);
@@ -6821,7 +6820,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 /* set EPHY registers */
                 re_ephy_write(sc, 0x19, 0xFF64);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
         } else if (macver == 0x48000000) {
                 /*set configuration space offset 0x70f to 0x27*/
@@ -6890,19 +6889,19 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_1(sc, 0xD0, CSR_READ_1(sc, 0xD0) | BIT_6);
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) | BIT_6);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
 
-                if (ifp->if_mtu > ETHERMTU) {
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_TXCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                         }
                 }
         } else if (macver == 0x48800000) {
@@ -6967,19 +6966,19 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_1(sc, 0xD0, CSR_READ_1(sc, 0xD0) | BIT_6);
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) | BIT_6);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
 
-                if (ifp->if_mtu > ETHERMTU) {
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                         }
                 }
         } else if (macver == 0x44800000) {
@@ -7218,7 +7217,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
 
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) & ~BIT_3);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
 
                 if (sc->re_type == MACFG_56 || sc->re_type == MACFG_57 ||
@@ -7231,16 +7230,16 @@ static void re_hw_start_unlock(struct re_softc *sc)
                         re_mac_ocp_write(sc, 0xC142, 0xFFFF);
                 }
 
-                if (ifp->if_mtu > ETHERMTU) {
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                         }
                 }
         } else if (macver == 0x50000000) {
@@ -7316,7 +7315,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
 
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) & ~BIT_3);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
 
                 if (sc->re_type == MACFG_67) {
@@ -7337,16 +7336,16 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 re_mac_ocp_write(sc, 0xC140, 0xFFFF);
                 re_mac_ocp_write(sc, 0xC142, 0xFFFF);
 
-                if (ifp->if_mtu > ETHERMTU) {
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                         }
                 }
         } else if (macver == 0x54800000) {
@@ -7480,29 +7479,31 @@ static void re_hw_start_unlock(struct re_softc *sc)
 
                 CSR_WRITE_1(sc, 0xF2, CSR_READ_1(sc, 0xF2) & ~BIT_3);
 
-                if (ifp->if_mtu > ETHERMTU)
+                if (if_getmtu(ifp) > ETHERMTU)
                         CSR_WRITE_1 (sc, RE_MTPS, 0x27);
 
                 re_mac_ocp_write(sc, 0xC140, 0xFFFF);
                 re_mac_ocp_write(sc, 0xC142, 0xFFFF);
 
-                if (ifp->if_mtu > ETHERMTU) {
-                        ifp->if_capenable &= ~IFCAP_HWCSUM;
-                        ifp->if_hwassist &= ~RE_CSUM_FEATURES;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_TXCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
+                        if_setcapenablebit(ifp, 0, IFCAP_HWCSUM);
+                        if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES);
                 } else {
                         if (sc->re_tx_cstag) {
-                                ifp->if_capenable |= IFCAP_TXCSUM;
-                                ifp->if_hwassist |= RE_CSUM_FEATURES;
+                                if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                         }
                         if (sc->re_rx_cstag) {
-                                ifp->if_capenable |= IFCAP_RXCSUM;
+                                if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                         }
                 }
         }
 
         if (!((sc->re_if_flags & RL_FLAG_DESCV2) &&
               (sc->re_if_flags & RL_FLAG_8168G_PLUS)))
-                ifp->if_hwassist &= ~(CSUM_TCP_IPV6 | CSUM_UDP_IPV6);
+                if_sethwassistbits(ifp, 0, CSUM_TCP_IPV6 | CSUM_UDP_IPV6);
 
         //clear io_rdy_l23
         switch (sc->re_type) {
@@ -7572,12 +7573,12 @@ static void re_hw_start_unlock(struct re_softc *sc)
         re_clrwol(sc);
 
         data16 = CSR_READ_2(sc, RE_CPlusCmd);
-        if ((ifp->if_capenable & IFCAP_VLAN_HWTAGGING) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_VLAN_HWTAGGING) != 0)
                 data16 |= RL_CPLUSCMD_VLANSTRIP;
         else
                 data16 &= ~RL_CPLUSCMD_VLANSTRIP;
 
-        if ((ifp->if_capenable & IFCAP_RXCSUM) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_RXCSUM) != 0)
                 data16 |= RL_RxChkSum;
         else
                 data16 &= ~RL_RxChkSum;
@@ -7609,8 +7610,7 @@ static void re_hw_start_unlock(struct re_softc *sc)
                 CSR_WRITE_1(sc, RE_COMMAND, RE_CMD_TX_ENB | RE_CMD_RX_ENB);
         }
 
-        ifp->if_drv_flags |= IFF_DRV_RUNNING;
-        ifp->if_drv_flags &= ~IFF_DRV_OACTIVE;
+        if_setdrvflags(ifp, (if_getdrvflags(ifp) | IFF_DRV_RUNNING) & ~IFF_DRV_OACTIVE);
 
         /*
         * Enable interrupts.
@@ -7641,9 +7641,9 @@ static void re_init_unlock(void *xsc)  	/* Software & 
         * Disable TSO if interface MTU size is greater than MSS
         * allowed in controller.
         */
-        if (ifp->if_mtu > ETHERMTU) {
-                ifp->if_capenable &= ~(IFCAP_TSO | IFCAP_VLAN_HWTSO);
-                ifp->if_hwassist &= ~CSUM_TSO;
+        if (if_getmtu(ifp) > ETHERMTU) {
+                if_setcapenablebit(ifp, 0, IFCAP_TSO | IFCAP_VLAN_HWTSO);
+                if_sethwassistbits(ifp, 0, CSUM_TSO);
         }
 
         /* Copy MAC address on stack to align. */
@@ -7652,7 +7652,7 @@ static void re_init_unlock(void *xsc)  	/* Software & 
 #elif OS_VER < VERSION(7,0)
         bcopy(IFP2ENADDR(ifp), eaddr.eaddr, ETHER_ADDR_LEN);
 #else
-        bcopy(IF_LLADDR(ifp), eaddr.eaddr, ETHER_ADDR_LEN);
+        bcopy(if_getlladdr(ifp), eaddr.eaddr, ETHER_ADDR_LEN);
 #endif
 
         /* Init our MAC address */
@@ -7992,11 +7992,11 @@ static void re_hw_start_unlock_8125(struct re_softc *s
                 }
 
                 if (sc->re_tx_cstag) {
-                        ifp->if_capenable |= IFCAP_TXCSUM;
-                        ifp->if_hwassist |= RE_CSUM_FEATURES;
+                        if_setcapenablebit(ifp, IFCAP_TXCSUM, 0);
+                        if_sethwassistbits(ifp, RE_CSUM_FEATURES, 0);
                 }
                 if (sc->re_rx_cstag) {
-                        ifp->if_capenable |= IFCAP_RXCSUM;
+                        if_setcapenablebit(ifp, IFCAP_RXCSUM, 0);
                 }
         }
 
@@ -8015,13 +8015,13 @@ static void re_hw_start_unlock_8125(struct re_softc *s
         else
                 CSR_WRITE_4(sc, 0x0A00, 0x00630063);
 
-        if ((ifp->if_capenable & IFCAP_VLAN_HWTAGGING) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_VLAN_HWTAGGING) != 0)
                 CSR_WRITE_4(sc, RE_RXCFG, CSR_READ_4(sc, RE_RXCFG) | (BIT_22 | BIT_23));
         else
                 CSR_WRITE_4(sc, RE_RXCFG, CSR_READ_4(sc, RE_RXCFG) & ~(BIT_22 | BIT_23));
 
         data16 = CSR_READ_2(sc, RE_CPlusCmd);
-        if ((ifp->if_capenable & IFCAP_RXCSUM) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_RXCSUM) != 0)
                 data16 |= RL_RxChkSum;
         else
                 data16 &= ~RL_RxChkSum;
@@ -8038,8 +8038,7 @@ static void re_hw_start_unlock_8125(struct re_softc *s
         /* Enable transmit and receive.*/
         CSR_WRITE_1(sc, RE_COMMAND, RE_CMD_TX_ENB | RE_CMD_RX_ENB);
 
-        ifp->if_drv_flags |= IFF_DRV_RUNNING;
-        ifp->if_drv_flags &= ~IFF_DRV_OACTIVE;
+        if_setdrvflags(ifp, (if_getdrvflags(ifp) | IFF_DRV_RUNNING) & ~IFF_DRV_OACTIVE);
 
         /*
         * Enable interrupts.
@@ -8309,7 +8308,7 @@ re_setwol(struct re_softc *sc)
 
         ifp = RE_GET_IFNET(sc);
 
-        if ((ifp->if_capenable & IFCAP_WOL) == 0) {
+        if ((if_getcapenable(ifp) & IFCAP_WOL) == 0) {
                 re_phy_power_down(sc->dev);
                 return;
         }
@@ -8320,7 +8319,7 @@ re_setwol(struct re_softc *sc)
         /* Enable config register write. */
         re_enable_cfg9346_write(sc);
 
-        if (ifp->if_capenable & IFCAP_WOL_MAGIC)
+        if (if_getcapenable(ifp) & IFCAP_WOL_MAGIC)
                 re_enable_magic_packet(sc);
         else
                 re_disable_magic_packet(sc);
@@ -8329,11 +8328,11 @@ re_setwol(struct re_softc *sc)
         v &= ~(RL_CFG5_WOL_BCAST | RL_CFG5_WOL_MCAST | RL_CFG5_WOL_UCAST |
                RL_CFG5_WOL_LANWAKE);
 
-        if ((ifp->if_capenable & IFCAP_WOL_UCAST) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_WOL_UCAST) != 0)
                 v |= RL_CFG5_WOL_UCAST;
-        if ((ifp->if_capenable & IFCAP_WOL_MCAST) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_WOL_MCAST) != 0)
                 v |= RL_CFG5_WOL_MCAST | RL_CFG5_WOL_BCAST;
-        if ((ifp->if_capenable & IFCAP_WOL) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_WOL) != 0)
                 v |= RL_CFG5_WOL_LANWAKE;
         CSR_WRITE_1(sc, RE_CFG5, v);
 
@@ -8349,12 +8348,12 @@ re_setwol(struct re_softc *sc)
         /* Request PME if WOL is requested. */
         pmstat = pci_read_config(sc->dev, pmc + PCIR_POWER_STATUS, 2);
         pmstat &= ~(PCIM_PSTAT_PME | PCIM_PSTAT_PMEENABLE);
-        if ((ifp->if_capenable & IFCAP_WOL) != 0)
+        if ((if_getcapenable(ifp) & IFCAP_WOL) != 0)
                 pmstat |= PCIM_PSTAT_PME | PCIM_PSTAT_PMEENABLE;
         pci_write_config(sc->dev, pmc + PCIR_POWER_STATUS, pmstat, 2);
 
         /* Put controller into sleep mode. */
-        if ((ifp->if_capenable & IFCAP_WOL) != 0) {
+        if ((if_getcapenable(ifp) & IFCAP_WOL) != 0) {
                 uint8_t wol_link_speed;
                 re_set_rx_packet_filter_in_sleep_state(sc);
                 wol_link_speed = re_set_wol_linkspeed(sc);
@@ -8494,7 +8493,7 @@ static void re_stop(struct re_softc *sc)  	/* Stop Dri
                 sc->re_desc.tx_last_index++;
         }
 
-        ifp->if_drv_flags &= ~(IFF_DRV_RUNNING | IFF_DRV_OACTIVE);
+        if_setdrvflagbits(ifp, 0, IFF_DRV_RUNNING | IFF_DRV_OACTIVE);
 
         return;
 }
@@ -8506,7 +8505,7 @@ static void re_start(struct ifnet *ifp)  	/* Transmit 
 {
         struct re_softc		*sc;
 
-        sc = ifp->if_softc;	/* Paste to ifp in function re_attach(dev) */
+        sc = if_getsoftc(ifp); /* Paste to ifp in function re_attach(dev) */
         RE_LOCK(sc);
         re_start_locked(ifp);
         RE_UNLOCK(sc);
@@ -8599,7 +8598,7 @@ static void re_start_locked(struct ifnet *ifp)
         int		error;
         int		i;
 
-        sc = ifp->if_softc;	/* Paste to ifp in function re_attach(dev) */
+        sc = if_getsoftc(ifp);	/* Paste to ifp in function re_attach(dev) */
 
         /*	RE_LOCK_ASSERT(sc);*/
 
@@ -8607,11 +8606,11 @@ static void re_start_locked(struct ifnet *ifp)
                 return;
 
         tx_cur_index = sc->re_desc.tx_cur_index;
-        for (queued = 0; !IFQ_DRV_IS_EMPTY(&ifp->if_snd);) {
+        for (queued = 0; !if_sendq_empty(ifp);) {
                 int fs = 1, ls = 0;
                 uint32_t  opts1;
                 uint32_t  opts2;
-                IFQ_DRV_DEQUEUE(&ifp->if_snd, m_head);	/* Remove(get) data from system transmit queue */
+                m_head = if_dequeue(ifp); /* Remove(get) data from system transmit queue */
                 if (m_head == NULL) {
                         break;
                 }
@@ -8620,8 +8619,8 @@ static void re_start_locked(struct ifnet *ifp)
                     sc->re_type == MACFG_82 || sc->re_type == MACFG_83) &&
                     sc->re_device_id != RT_DEVICEID_3000) {
                         if (re_8125_pad(sc, m_head) != 0) {
-                                IFQ_DRV_PREPEND(&ifp->if_snd, m_head);
-                                ifp->if_drv_flags |= IFF_DRV_OACTIVE;
+                                if_sendq_prepend(ifp, m_head);
+                                if_setdrvflagbits(ifp, IFF_DRV_OACTIVE, 0);
                                 break;
                         }
                 }
@@ -8629,8 +8628,8 @@ static void re_start_locked(struct ifnet *ifp)
                 entry = tx_cur_index % RE_TX_BUF_NUM;
                 if (sc->re_coalesce_tx_pkt) {
                         if (re_encap(sc, &m_head)) {
-                                IFQ_DRV_PREPEND(&ifp->if_snd, m_head);
-                                ifp->if_drv_flags |= IFF_DRV_OACTIVE;
+                                if_sendq_prepend(ifp, m_head);
+                                if_setdrvflagbits(ifp, IFF_DRV_OACTIVE, 0);
                                 break;
                         }
                 }
@@ -8656,8 +8655,8 @@ static void re_start_locked(struct ifnet *ifp)
                         }
                 } else if (error != 0) {
                         //return (error);
-                        IFQ_DRV_PREPEND(&ifp->if_snd, m_head);
-                        ifp->if_drv_flags |= IFF_DRV_OACTIVE;
+                        if_sendq_prepend(ifp, m_head);
+                        if_setdrvflagbits(ifp, IFF_DRV_OACTIVE, 0);
                         break;
                 }
                 if (nsegs == 0) {
@@ -8670,8 +8669,8 @@ static void re_start_locked(struct ifnet *ifp)
                 /* Check for number of available descriptors. */
                 if (CountFreeTxDescNum(&sc->re_desc) < nsegs) {	/* No enough descriptor */
                         bus_dmamap_unload(sc->re_desc.re_tx_mtag, sc->re_desc.re_tx_dmamap[entry]);
-                        IFQ_DRV_PREPEND(&ifp->if_snd, m_head);
-                        ifp->if_drv_flags |= IFF_DRV_OACTIVE;
+                        if_sendq_prepend(ifp, m_head);
+                        if_setdrvflagbits(ifp, IFF_DRV_OACTIVE, 0);
                         break;
                 }
 
@@ -8680,14 +8679,14 @@ static void re_start_locked(struct ifnet *ifp)
 
                 first_entry = entry;
 
-                if (ifp->if_bpf) {		/* If there's a BPF listener, bounce a copy of this frame to him. */
+                if (if_getbpf(ifp)) {		/* If there's a BPF listener, bounce a copy of this frame to him. */
                         //printf("If there's a BPF listener, bounce a copy of this frame to him. \n");
 
                         /*#if OS_VER < VERSION(5, 1)*/
 #if OS_VER < VERSION(4,9)
                         bpf_mtap(ifp, m_head);
 #else
-                        bpf_mtap(ifp->if_bpf, m_head);
+                        bpf_mtap(if_getbpf(ifp), m_head);
 #endif
                 }
 
@@ -9027,7 +9026,7 @@ static void re_txeof(struct re_softc *sc)  	/* Transmi
 
         if (sc->re_desc.tx_last_index != tx_last_index) {
                 sc->re_desc.tx_last_index = tx_last_index;
-                ifp->if_drv_flags &= ~IFF_DRV_OACTIVE;
+                if_setdrvflagbits(ifp, 0, IFF_DRV_OACTIVE);
         }
 
         /* prevent tx stop. */
@@ -9062,7 +9061,7 @@ re_rxq_input(struct re_softc *sc, struct mbuf *m, bool
         ifp = RE_GET_IFNET(sc);
 
 #if defined(INET) || defined(INET6)
-        if ((ifp->if_capenable & IFCAP_LRO) && lro_able) {
+        if ((if_getcapenable(ifp) & IFCAP_LRO) && lro_able) {
                 if (re_lro_rx(sc, m) == 0)
                         return;
         }
@@ -9074,7 +9073,7 @@ re_rxq_input(struct re_softc *sc, struct mbuf *m, bool
         m_adj(m, sizeof(struct ether_header));
         ether_input(ifp, eh, m);
 #else
-        (*ifp->if_input)(ifp, m);
+        if_input(ifp, m);
 #endif
 }
 
@@ -9218,7 +9217,7 @@ static int re_rxeof(struct re_softc *sc)	/* Receive Da
                                 bswap16((opts2 & RL_RDESC_VLANCTL_DATA));
                         m->m_flags |= M_VLANTAG;
                 }
-                if (ifp->if_capenable & IFCAP_RXCSUM) {
+                if (if_getcapenable(ifp) & IFCAP_RXCSUM) {
                         if ((sc->re_if_flags & RL_FLAG_DESCV2) == 0) {
                                 u_int32_t proto = opts1 & RL_ProtoMASK;
 
@@ -9301,7 +9300,7 @@ drop_packet:
         }
 
         if (sc->re_desc.rx_cur_index != rx_cur_index) {
-                if (ifp->if_capenable & IFCAP_LRO) {
+                if (if_getcapenable(ifp) & IFCAP_LRO) {
                         RE_UNLOCK(sc);
                         re_drain_soft_lro(sc);
                         RE_LOCK(sc);
@@ -9398,7 +9397,7 @@ static void re_int_task_poll(void *arg, int npending)
         ifp = RE_GET_IFNET(sc);
 
         if (sc->suspended ||
-            (ifp->if_drv_flags & IFF_DRV_RUNNING) == 0) {
+            (if_getdrvflags(ifp) & IFF_DRV_RUNNING) == 0) {
                 RE_UNLOCK(sc);
                 return;
         }
@@ -9407,7 +9406,7 @@ static void re_int_task_poll(void *arg, int npending)
 
         re_txeof(sc);
 
-        if (!IFQ_DRV_IS_EMPTY(&ifp->if_snd))
+        if (!if_sendq_empty(ifp))
                 re_start_locked(ifp);
 
         RE_UNLOCK(sc);
@@ -9447,7 +9446,7 @@ static void re_int_task(void *arg, int npending)
         }
 
         if (sc->suspended ||
-            (ifp->if_drv_flags & IFF_DRV_RUNNING) == 0) {
+            (if_getdrvflags(ifp) & IFF_DRV_RUNNING) == 0) {
                 RE_UNLOCK(sc);
                 return;
         }
@@ -9483,7 +9482,7 @@ static void re_int_task(void *arg, int npending)
                 re_init_locked(sc);
         }
 
-        if (!IFQ_DRV_IS_EMPTY(&ifp->if_snd))
+        if (!if_sendq_empty(ifp))
                 re_start_locked(ifp);
 
         RE_UNLOCK(sc);
@@ -9516,7 +9515,7 @@ static void re_int_task_8125_poll(void *arg, int npend
         ifp = RE_GET_IFNET(sc);
 
         if (sc->suspended ||
-            (ifp->if_drv_flags & IFF_DRV_RUNNING) == 0) {
+            (if_getdrvflags(ifp) & IFF_DRV_RUNNING) == 0) {
                 RE_UNLOCK(sc);
                 return;
         }
@@ -9525,7 +9524,7 @@ static void re_int_task_8125_poll(void *arg, int npend
 
         re_txeof(sc);
 
-        if (!IFQ_DRV_IS_EMPTY(&ifp->if_snd))
+        if (!if_sendq_empty(ifp))
                 re_start_locked(ifp);
 
         RE_UNLOCK(sc);
@@ -9565,7 +9564,7 @@ static void re_int_task_8125(void *arg, int npending)
         }
 
         if (sc->suspended ||
-            (ifp->if_drv_flags & IFF_DRV_RUNNING) == 0) {
+            (if_getdrvflags(ifp) & IFF_DRV_RUNNING) == 0) {
                 RE_UNLOCK(sc);
                 return;
         }
@@ -9579,7 +9578,7 @@ static void re_int_task_8125(void *arg, int npending)
                 re_init_locked(sc);
         }
 
-        if (!IFQ_DRV_IS_EMPTY(&ifp->if_snd))
+        if (!if_sendq_empty(ifp))
                 re_start_locked(ifp);
 
         RE_UNLOCK(sc);
@@ -9651,19 +9650,19 @@ static void re_set_rx_packet_filter(struct re_softc *s
 
         rxfilt |= RE_RXCFG_RX_INDIV;
 
-        if (ifp->if_flags & (IFF_MULTICAST | IFF_ALLMULTI | IFF_PROMISC)) {
+        if (if_getflags(ifp) & (IFF_MULTICAST | IFF_ALLMULTI | IFF_PROMISC)) {
                 rxfilt |= (RE_RXCFG_RX_ALLPHYS | RE_RXCFG_RX_MULTI);
         } else {
                 rxfilt &= ~(RE_RXCFG_RX_MULTI);
         }
 
-        if (ifp->if_flags & IFF_PROMISC) {
+        if (if_getflags(ifp) & IFF_PROMISC) {
                 rxfilt |= (RE_RXCFG_RX_ALLPHYS | RE_RXCFG_RX_RUNT | RE_RXCFG_RX_ERRPKT);
         } else {
                 rxfilt &= ~(RE_RXCFG_RX_ALLPHYS | RE_RXCFG_RX_RUNT | RE_RXCFG_RX_ERRPKT);
         }
 
-        if (ifp->if_flags & (IFF_BROADCAST | IFF_PROMISC)) {
+        if (if_getflags(ifp) & (IFF_BROADCAST | IFF_PROMISC)) {
                 rxfilt |= RE_RXCFG_RX_BROAD;
         } else {
                 rxfilt &= ~RE_RXCFG_RX_BROAD;
@@ -9710,7 +9709,7 @@ static void re_setmulti(struct re_softc *sc)
 
         rxfilt = CSR_READ_4(sc, RE_RXCFG);
 
-        if (ifp->if_flags & IFF_ALLMULTI || ifp->if_flags & IFF_PROMISC) {
+        if (if_getflags(ifp) & IFF_ALLMULTI || if_getflags(ifp) & IFF_PROMISC) {
                 rxfilt |= RE_RXCFG_RX_MULTI;
                 CSR_WRITE_4(sc, RE_RXCFG, rxfilt);
                 re_set_multicast_reg(sc, 0xFFFFFFFF, 0xFFFFFFFF);
@@ -9771,7 +9770,7 @@ static int re_ioctl(struct ifnet *ifp, u_long command,
 
 static int re_ioctl(struct ifnet *ifp, u_long command, caddr_t data)
 {
-        struct re_softc		*sc = ifp->if_softc;
+        struct re_softc		*sc = if_getsoftc(ifp);
         struct ifreq		*ifr = (struct ifreq *) data;
         /*int			s;*/
         int			error = 0;
@@ -9786,16 +9785,16 @@ static int re_ioctl(struct ifnet *ifp, u_long command,
                 break;
         case SIOCSIFMTU:
 
-                //printf("before mtu =%d\n",(int)ifp->if_mtu);
+                //printf("before mtu =%d\n",(int)if_getmtu(ifp));
                 if (ifr->ifr_mtu > sc->max_jumbo_frame_size) {
                         error = EINVAL;
                         break;
                 }
                 RE_LOCK(sc);
-                if (ifp->if_mtu != ifr->ifr_mtu) {
-                        ifp->if_mtu = ifr->ifr_mtu;
+                if (if_getmtu(ifp) != ifr->ifr_mtu) {
+                        if_setmtu(ifp, ifr->ifr_mtu);
                         //if running
-                        if (ifp->if_drv_flags & IFF_DRV_RUNNING) {
+                        if (if_getdrvflags(ifp) & IFF_DRV_RUNNING) {
                                 //printf("set mtu when running\n");
                                 re_stop(sc);
 
@@ -9818,20 +9817,20 @@ static int re_ioctl(struct ifnet *ifp, u_long command,
 
                         }
 
-                        if (ifp->if_mtu > ETHERMTU) {
-                                ifp->if_capenable &= ~(IFCAP_TSO |
-                                                       IFCAP_VLAN_HWTSO);
-                                ifp->if_hwassist &= ~CSUM_TSO;
+                        if (if_getmtu(ifp) > ETHERMTU) {
+                                if_setcapenablebit(ifp, 0, IFCAP_TSO |
+                                    IFCAP_VLAN_HWTSO);
+                                if_sethwassistbits(ifp, 0, CSUM_TSO);
                         }
-                        //	printf("after mtu =%d\n",(int)ifp->if_mtu);
+                        //	printf("after mtu =%d\n",(int)if_getmtu(ifp));
                 }
                 RE_UNLOCK(sc);
                 break;
         case SIOCSIFFLAGS:
                 RE_LOCK(sc);
-                if (ifp->if_flags & IFF_UP) {
+                if (if_getflags(ifp) & IFF_UP) {
                         re_init_locked(sc);
-                } else if (ifp->if_drv_flags & IFF_DRV_RUNNING) {
+                } else if (if_getdrvflags(ifp) & IFF_DRV_RUNNING) {
                         re_stop(sc);
                 }
                 error = 0;
@@ -9849,113 +9848,115 @@ static int re_ioctl(struct ifnet *ifp, u_long command,
                 error = ifmedia_ioctl(ifp, ifr, &sc->media, command);
                 break;
         case SIOCSIFCAP:
-                mask = ifr->ifr_reqcap ^ ifp->if_capenable;
+                mask = ifr->ifr_reqcap ^ if_getcapenable(ifp);
                 reinit = 0;
 
-                if ((mask & IFCAP_TXCSUM) != 0 && (ifp->if_capabilities & IFCAP_TXCSUM) != 0) {
-                        ifp->if_capenable ^= IFCAP_TXCSUM;
-                        if ((ifp->if_capenable & IFCAP_TXCSUM) != 0)  {
+                if ((mask & IFCAP_TXCSUM) != 0 && (if_getcapabilities(ifp) & IFCAP_TXCSUM) != 0) {
+                        if_togglecapenable(ifp, IFCAP_TXCSUM);
+                        if ((if_getcapenable(ifp) & IFCAP_TXCSUM) != 0)  {
                                 if ((sc->re_type == MACFG_24) || (sc->re_type == MACFG_25) || (sc->re_type == MACFG_26))
-                                        ifp->if_hwassist |= CSUM_TCP | CSUM_UDP;
+                                        if_sethwassistbits(ifp, CSUM_TCP | CSUM_UDP, 0);
                                 else
-                                        ifp->if_hwassist |= RE_CSUM_FEATURES_IPV4;
+                                        if_sethwassistbits(ifp, RE_CSUM_FEATURES_IPV4, 0);
                         } else
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES_IPV4;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES_IPV4);
                         reinit = 1;
                 }
 
-                if ((mask & IFCAP_TXCSUM_IPV6) != 0 && (ifp->if_capabilities & IFCAP_TXCSUM_IPV6) != 0) {
-                        ifp->if_capenable ^= IFCAP_TXCSUM_IPV6;
-                        if ((ifp->if_capenable & IFCAP_TXCSUM_IPV6) != 0)  {
-                                ifp->if_hwassist |= RE_CSUM_FEATURES_IPV6;
+                if ((mask & IFCAP_TXCSUM_IPV6) != 0 && (if_getcapabilities(ifp) & IFCAP_TXCSUM_IPV6) != 0) {
+                        if_togglecapenable(ifp, IFCAP_TXCSUM_IPV6);
+                        if ((if_getcapenable(ifp) & IFCAP_TXCSUM_IPV6) != 0)  {
+                                if_sethwassistbits(ifp, RE_CSUM_FEATURES_IPV6, 0);
                         } else
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES_IPV6;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES_IPV6);
 
                         if (!((sc->re_if_flags & RL_FLAG_DESCV2) &&
                               (sc->re_if_flags & RL_FLAG_8168G_PLUS)))
-                                ifp->if_hwassist &= ~RE_CSUM_FEATURES_IPV6;
+                                if_sethwassistbits(ifp, 0, RE_CSUM_FEATURES_IPV6);
                         reinit = 1;
                 }
 
                 if ((mask & IFCAP_RXCSUM) != 0 &&
-                    (ifp->if_capabilities & IFCAP_RXCSUM) != 0) {
-                        ifp->if_capenable ^= IFCAP_RXCSUM;
+                    (if_getcapabilities(ifp) & IFCAP_RXCSUM) != 0) {
+                        if_setcapabilitiesbit(ifp, 0, IFCAP_RXCSUM);
+                        if_togglecapenable(ifp, IFCAP_RXCSUM);
                         reinit = 1;
                 }
 
                 if ((mask & IFCAP_RXCSUM_IPV6) != 0 &&
-                    (ifp->if_capabilities & IFCAP_RXCSUM_IPV6) != 0) {
-                        ifp->if_capenable ^= IFCAP_RXCSUM_IPV6;
+                    (if_getcapabilities(ifp) & IFCAP_RXCSUM_IPV6) != 0) {
+                        if_setcapabilitiesbit(ifp, 0, IFCAP_RXCSUM_IPV6);
                         reinit = 1;
                 }
 
-                if ((ifp->if_mtu <= ETHERMTU) || ((sc->re_type>= MACFG_3) &&(sc->re_type <=MACFG_6)) || ((sc->re_type>= MACFG_21) && (sc->re_type <=MACFG_23))) {
-                        if (ifp->if_capenable & IFCAP_TXCSUM)
+                if ((if_getmtu(ifp) <= ETHERMTU) || ((sc->re_type>= MACFG_3) &&(sc->re_type <=MACFG_6)) || ((sc->re_type>= MACFG_21) && (sc->re_type <=MACFG_23))) {
+                        if (if_getcapabilities(ifp) & IFCAP_TXCSUM)
                                 sc->re_tx_cstag = 1;
                         else
                                 sc->re_tx_cstag = 0;
 
-                        if (ifp->if_capenable & (IFCAP_RXCSUM | IFCAP_RXCSUM_IPV6))
+                        if (if_getcapenable(ifp) & (IFCAP_RXCSUM | IFCAP_RXCSUM_IPV6))
                                 sc->re_rx_cstag = 1;
                         else
                                 sc->re_rx_cstag = 0;
                 }
 
                 if ((mask & IFCAP_TSO4) != 0 &&
-                    (ifp->if_capabilities & IFCAP_TSO4) != 0) {
-                        ifp->if_capenable ^= IFCAP_TSO4;
-                        if ((IFCAP_TSO4 & ifp->if_capenable) != 0)
-                                ifp->if_hwassist |= CSUM_IP_TSO;
+                    (if_getcapabilities(ifp) & IFCAP_TSO4) != 0) {
+                        if_setcapenablebit(ifp, 0, IFCAP_TSO4);
+                        if ((IFCAP_TSO4 & if_getcapenable(ifp)) != 0)
+                                if_sethwassistbits(ifp, CSUM_IP_TSO, 0);
                         else
-                                ifp->if_hwassist &= ~CSUM_IP_TSO;
-                        if (ifp->if_mtu > ETHERMTU) {
-                                ifp->if_capenable &= ~IFCAP_TSO4;
-                                ifp->if_hwassist &= ~CSUM_IP_TSO;
+                                if_sethwassistbits(ifp, 0, CSUM_IP_TSO);
+                        if (if_getmtu(ifp) > ETHERMTU) {
+                                if_setcapenablebit(ifp, 0, IFCAP_TSO4);
+                                if_sethwassistbits(ifp, 0, CSUM_IP_TSO);
                         }
                 }
                 /*
                 if ((mask & IFCAP_TSO6) != 0 &&
-                (ifp->if_capabilities & IFCAP_TSO6) != 0) {
-                ifp->if_capenable ^= IFCAP_TSO6;
-                if ((IFCAP_TSO6 & ifp->if_capenable) != 0)
-                ifp->if_hwassist |= CSUM_IP6_TSO;
-                else
-                ifp->if_hwassist &= ~CSUM_IP6_TSO;
-                if (ifp->if_mtu > ETHERMTU) {
-                ifp->if_capenable &= ~IFCAP_TSO6;
-                ifp->if_hwassist &= ~CSUM_IP6_TSO;
+                    (if_getcapabilities(ifp) & IFCAP_TSO6) != 0) {
+                        if_setcapenablebits(ifp, 0, IFCAP_TSO6);
+                        if ((IFCAP_TSO6 & if_getcapenable(ifp)) != 0)
+                                if_sethwassistbits(ifp, CSUM_IP6_TSO, 0);
+                        else
+                                if_sethwassistbits(ifp, 0, CSUM_IP6_TSO);
+                        if (if_getmtu(ifp) > ETHERMTU) {
+                                if_setcapenablebit(ifp, 0, IFCAP_TSO6);
+                                if_sethwassistbits(ifp, 0, CSUM_IP6_TSO);
+                        }
                 }
-                if (ifp->if_mtu > ETHERMTU) {
-                ifp->if_capenable &= ~IFCAP_TSO6;
-                ifp->if_hwassist &= ~CSUM_IP6_TSO;
+                if (if_getmtu(ifp) > ETHERMTU) {
+                        if_setcapenablebit(ifp, 0, IFCAP_TSO6);
+                        if_sethwassistbit(ifp, 0, CSUM_IP6_TSO);
                 }
                 }
                 */
                 if ((mask & IFCAP_VLAN_HWTSO) != 0 &&
-                    (ifp->if_capabilities & IFCAP_VLAN_HWTSO) != 0)
-                        ifp->if_capenable ^= IFCAP_VLAN_HWTSO;
+                    (if_getcapabilities(ifp) & IFCAP_VLAN_HWTSO) != 0)
+                        if_setcapenablebit(ifp, 0, IFCAP_VLAN_HWTSO);
                 if ((mask & IFCAP_VLAN_HWTAGGING) != 0 &&
-                    (ifp->if_capabilities & IFCAP_VLAN_HWTAGGING) != 0) {
-                        ifp->if_capenable ^= IFCAP_VLAN_HWTAGGING;
+                    (if_getcapabilities(ifp) & IFCAP_VLAN_HWTAGGING) != 0) {
+                        if_setcapenablebit(ifp, 0, IFCAP_VLAN_HWTAGGING);
                         /* TSO over VLAN requires VLAN hardware tagging. */
                         //if ((ifp->if_capenable & IFCAP_VLAN_HWTAGGING) == 0)
                         //	ifp->if_capenable &= ~IFCAP_VLAN_HWTSO;
                         reinit = 1;
                 }
                 if (mask & IFCAP_LRO)
-                        ifp->if_capenable ^= IFCAP_LRO;
+                        if_togglecapenable(ifp, IFCAP_LRO);
 
                 if ((mask & IFCAP_WOL) != 0 &&
-                    (ifp->if_capabilities & IFCAP_WOL) != 0) {
+                    (if_getcapabilities(ifp) & IFCAP_WOL) != 0) {
                         if ((mask & IFCAP_WOL_UCAST) != 0)
-                                ifp->if_capenable ^= IFCAP_WOL_UCAST;
+                                if_setcapenablebit(ifp, 0, IFCAP_WOL_UCAST);
                         if ((mask & IFCAP_WOL_MCAST) != 0)
-                                ifp->if_capenable ^= IFCAP_WOL_MCAST;
+                                if_setcapenablebit(ifp, 0, IFCAP_WOL_MCAST);
                         if ((mask & IFCAP_WOL_MAGIC) != 0)
-                                ifp->if_capenable ^= IFCAP_WOL_MAGIC;
+                                if_setcapenablebit(ifp, 0, IFCAP_WOL_MAGIC);
                 }
-                if (reinit && ifp->if_drv_flags & IFF_DRV_RUNNING) {
-                        ifp->if_drv_flags &= ~IFF_DRV_RUNNING;
+                if (reinit && if_getdrvflags(ifp) & IFF_DRV_RUNNING) {
+                        if_setdrvflagbits(ifp, 0, IFF_DRV_RUNNING);
                         re_init(sc);
                 }
                 VLAN_CAPABILITIES(ifp);
@@ -9987,7 +9988,7 @@ static void re_link_on_patch(struct re_softc *sc)
                         re_eri_write(sc, 0x1bc, 4, 0x0000001f, ERIAR_ExGMAC);
                         re_eri_write(sc, 0x1dc, 4, 0x0000002d, ERIAR_ExGMAC);
                 }
-        } else if ((sc->re_type == MACFG_38 || sc->re_type == MACFG_39) && (ifp->if_flags & IFF_UP)) {
+        } else if ((sc->re_type == MACFG_38 || sc->re_type == MACFG_39) && (if_getflags(ifp) & IFF_UP)) {
                 if (sc->re_type == MACFG_38 && (CSR_READ_1(sc, RE_PHY_STATUS) & RL_PHY_STATUS_10M)) {
                         CSR_WRITE_4(sc, RE_RXCFG, CSR_READ_4(sc, RE_RXCFG) | RE_RXCFG_RX_ALLPHYS);
                 } else if (sc->re_type == MACFG_39) {
@@ -10032,7 +10033,7 @@ static void re_link_on_patch(struct re_softc *sc)
                     sc->re_type == MACFG_85 || sc->re_type == MACFG_86 ||
                     sc->re_type == MACFG_87 || sc->re_type == MACFG_90 ||
                     sc->re_type == MACFG_91 || sc->re_type == MACFG_92) &&
-                   (ifp->if_flags & IFF_UP)) {
+                   (if_getflags(ifp) & IFF_UP)) {
                 if (CSR_READ_1(sc, RE_PHY_STATUS) & RL_PHY_STATUS_FULL_DUP)
                         CSR_WRITE_4(sc, RE_TXCFG, (CSR_READ_4(sc, RE_TXCFG) | (BIT_24 | BIT_25)) & ~BIT_19);
                 else
@@ -10170,7 +10171,7 @@ struct ifnet		*ifp;
 {
         struct re_softc		*sc;
 
-        sc = ifp->if_softc;
+        sc = if_getsoftc(ifp);
 
         printf("re%d: watchdog timeout\n", sc->re_unit);
 #if OS_VER < VERSION(11,0)
@@ -10192,7 +10193,7 @@ static int re_ifmedia_upd(struct ifnet *ifp)
  */
 static int re_ifmedia_upd(struct ifnet *ifp)
 {
-        struct re_softc	*sc = ifp->if_softc;
+        struct re_softc	*sc = if_getsoftc(ifp);
         struct ifmedia	*ifm = &sc->media;
         int anar;
         int gbcr;
@@ -10294,7 +10295,7 @@ static int re_ifmedia_upd_8125(struct ifnet *ifp)
 
 static int re_ifmedia_upd_8125(struct ifnet *ifp)
 {
-        struct re_softc	*sc = ifp->if_softc;
+        struct re_softc	*sc = if_getsoftc(ifp);
         struct ifmedia	*ifm = &sc->media;
         int anar;
         int gbcr;
@@ -10372,7 +10373,7 @@ static void re_ifmedia_sts(struct ifnet *ifp, struct i
 {
         struct re_softc		*sc;
 
-        sc = ifp->if_softc;
+        sc = if_getsoftc(ifp);
 
         RE_LOCK(sc);
 
@@ -10407,7 +10408,7 @@ static void re_ifmedia_sts_8125(struct ifnet *ifp, str
 {
         struct re_softc		*sc;
 
-        sc = ifp->if_softc;
+        sc = if_getsoftc(ifp);
 
         RE_LOCK(sc);
 
