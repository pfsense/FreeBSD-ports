#ifndef PHP_SG5100_H
#define PHP_SG5100_H 1
#define PHP_SG5100_WORLD_VERSION "1.0"
#define PHP_SG5100_WORLD_EXTNAME "sg5100"

PHP_FUNCTION(sg5100_led);
PHP_FUNCTION(sg5100_switch);

extern zend_module_entry sg5100_module_entry;
#define phpext_sg5100_ptr &sg5100_module_entry

#endif
