--- v8/src/codegen/x64/macro-assembler-x64.cc.orig	2023-07-16 15:47:57 UTC
+++ v8/src/codegen/x64/macro-assembler-x64.cc
@@ -50,6 +50,8 @@ Operand StackArgumentsAccessor::GetArgumentOperand(int
   return Operand(rsp, kPCOnStackSize + index * kSystemPointerSize);
 }
 
+void MacroAssembler::CodeEntry() { endbr64(); }
+
 void MacroAssembler::Load(Register destination, ExternalReference source) {
   if (root_array_available_ && options().enable_root_relative_access) {
     intptr_t delta = RootRegisterOffsetForExternalReference(isolate(), source);
@@ -1874,7 +1876,7 @@ void MacroAssembler::Switch(Register scratch, Register
   cmpq(reg, Immediate(num_labels));
   j(above_equal, &fallthrough);
   leaq(table, MemOperand(&jump_table));
-  jmp(MemOperand(table, reg, times_8, 0));
+  jmp(MemOperand(table, reg, times_8, 0), /*notrack=*/true);
   // Emit the jump table inline, under the assumption that it's not too big.
   Align(kSystemPointerSize);
   bind(&jump_table);
