--- Source/JavaScriptCore/offlineasm/arm64.rb.orig	2021-09-22 23:05:58 UTC
+++ Source/JavaScriptCore/offlineasm/arm64.rb
@@ -1127,7 +1127,7 @@ class Instruction
             $asm.puts "ldr #{operands[1].arm64Operand(:quad)}, [#{operands[1].arm64Operand(:quad)}, #{operands[0].asmLabel}@GOTPAGEOFF]"
 
             # On Linux, use ELF GOT relocation specifiers.
-            $asm.putStr("#elif OS(LINUX)")
+            $asm.putStr("#elif OS(LINUX) || OS(FREEBSD)")
             $asm.puts "adrp #{operands[1].arm64Operand(:quad)}, :got:#{operands[0].asmLabel}"
             $asm.puts "ldr #{operands[1].arm64Operand(:quad)}, [#{operands[1].arm64Operand(:quad)}, :got_lo12:#{operands[0].asmLabel}]"
 
