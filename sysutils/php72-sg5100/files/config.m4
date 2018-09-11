PHP_ARG_ENABLE(sg5100, whether to enable SG-5100 support,
[ --enable-sg5100   Enable SG-5100 support])

if test "$PHP_SG5100" = "yes"; then
  AC_DEFINE(HAVE_SG5100, 1, [Whether you have SG-5100 support])
  PHP_NEW_EXTENSION(sg5100, sg5100.c, $ext_shared)
fi
