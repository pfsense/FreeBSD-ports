--- src/ucm/util/reloc.c.orig	2026-02-04 09:52:46 UTC
+++ src/ucm/util/reloc.c
@@ -673,10 +673,13 @@ static int ucm_dlclose(void *handle)
          * cached information anyway, and it may be re-added on the next call to
          * ucm_reloc_apply_patch().
          */
-        dl_name = ucm_reloc_get_dl_name(lm_entry->l_name, lm_entry->l_addr,
+        ElfW(Addr) dlpi_addr;
+        dlpi_addr = (ElfW(Addr))(uintptr_t)lm_entry->l_addr;
+
+        dl_name = ucm_reloc_get_dl_name(lm_entry->l_name, dlpi_addr,
                                         dl_name_buffer, sizeof(dl_name_buffer));
         pthread_mutex_lock(&ucm_reloc_patch_list_lock);
-        ucm_reloc_dl_info_cleanup(lm_entry->l_addr, dl_name);
+        ucm_reloc_dl_info_cleanup(dlpi_addr, dl_name);
         pthread_mutex_unlock(&ucm_reloc_patch_list_lock);
     }
 
