--- chrome/browser/resources/new_tab_page/new_tab_page.js.orig	2022-05-11 07:16:48 UTC
+++ chrome/browser/resources/new_tab_page/new_tab_page.js
@@ -21,10 +21,6 @@ export {chromeCartDescriptor as chromeCartV2Descriptor
 export {DriveProxy} from './modules/drive/drive_module_proxy.js';
 export {driveDescriptor} from './modules/drive/module.js';
 export {driveDescriptor as driveV2Descriptor} from './modules/drive_v2/module.js';
-// <if expr="not is_official_build">
-export {FooProxy} from './modules/dummy/foo_proxy.js';
-export {dummyDescriptor} from './modules/dummy/module.js';
-// </if>
 export {InfoDialogElement} from './modules/info_dialog.js';
 export {InitializeModuleCallback, Module, ModuleDescriptor} from './modules/module_descriptor.js';
 export {ModuleHeaderElement} from './modules/module_header.js';
