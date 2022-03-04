--- src/ruby/ext/grpc/extconf.rb.orig	2021-04-24 13:33:17 UTC
+++ src/ruby/ext/grpc/extconf.rb
@@ -42,9 +42,9 @@ if RUBY_PLATFORM =~ /darwin/
   ENV['ARFLAGS'] = '-o'
  end
 
-ENV['EMBED_OPENSSL'] = 'true'
-ENV['EMBED_ZLIB'] = 'true'
-ENV['EMBED_CARES'] = 'true'
+ENV['EMBED_OPENSSL'] = 'false'
+ENV['EMBED_ZLIB'] = 'false'
+ENV['EMBED_CARES'] = 'false'
 
 ENV['ARCH_FLAGS'] = RbConfig::CONFIG['ARCH_FLAG']
 if RUBY_PLATFORM =~ /darwin/
@@ -61,22 +61,23 @@ output_dir = File.expand_path(RbConfig::CONFIG['topdir
 grpc_lib_dir = File.join(output_dir, 'libs', grpc_config)
 ENV['BUILDDIR'] = output_dir
 
-unless windows
-  puts 'Building internal gRPC into ' + grpc_lib_dir
-  nproc = 4
-  nproc = Etc.nprocessors * 2 if Etc.respond_to? :nprocessors
-  make = bsd ? 'gmake' : 'make'
-  system("#{make} -j#{nproc} -C #{grpc_root} #{grpc_lib_dir}/libgrpc.a CONFIG=#{grpc_config} Q=")
-  exit 1 unless $? == 0
-end
+#unless windows
+#  puts 'Building internal gRPC into ' + grpc_lib_dir
+#  nproc = 4
+#  nproc = Etc.nprocessors * 2 if Etc.respond_to? :nprocessors
+#  make = bsd ? 'gmake' : 'make'
+#  system("#{make} -j#{nproc} -C #{grpc_root} #{grpc_lib_dir}/libgrpc.a CONFIG=#{grpc_config} Q=")
+#  exit 1 unless $? == 0
+#end
 
-$CFLAGS << ' -I' + File.join(grpc_root, 'include')
+#$CFLAGS << ' -I' + File.join(grpc_root, 'include')
 
 ext_export_file = File.join(grpc_root, 'src', 'ruby', 'ext', 'grpc', 'ext-export')
-$LDFLAGS << ' -Wl,--version-script="' + ext_export_file + '.gcc"' if RUBY_PLATFORM =~ /linux/
-$LDFLAGS << ' -Wl,-exported_symbols_list,"' + ext_export_file + '.clang"' if RUBY_PLATFORM =~ /darwin/
+#$LDFLAGS << ' -Wl,--version-script="' + ext_export_file + '.gcc"' if RUBY_PLATFORM =~ /linux/
+#$LDFLAGS << ' -Wl,-exported_symbols_list,"' + ext_export_file + '.clang"' if RUBY_PLATFORM =~ /darwin/
+$LDFLAGS << ' -lgrpc' unless windows
 
-$LDFLAGS << ' ' + File.join(grpc_lib_dir, 'libgrpc.a') unless windows
+#$LDFLAGS << ' ' + File.join(grpc_lib_dir, 'libgrpc.a') unless windows
 if grpc_config == 'gcov'
   $CFLAGS << ' -O0 -fprofile-arcs -ftest-coverage'
   $LDFLAGS << ' -fprofile-arcs -ftest-coverage -rdynamic'
