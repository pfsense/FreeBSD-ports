<?php

/** @generate-class-entries */

/**
 * @strict-properties
 * @not-serializable
 */
final class RadiusHandle {}

function radius_auth_open(): RadiusHandle|false {}
function radius_acct_open(): RadiusHandle|false {}
function radius_close(RadiusHandle &$h): bool {}
function radius_strerror(RadiusHandle $h): string {}
function radius_config(RadiusHandle $h, string $file): bool {}
function radius_add_server(RadiusHandle $h, string $host, int $port, string $secret, int $timeout = 30, int $max_tries = 5): bool {}
function radius_create_request(RadiusHandle $h, int $code, bool $msg_auth = false): bool {}
function radius_put_string(RadiusHandle $h, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_put_int(RadiusHandle $h, int $type, int $value, int $options = 0, int $tag = 0): bool {}
function radius_put_attr(RadiusHandle $h, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_put_addr(RadiusHandle $h, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_put_vendor_string(RadiusHandle $h, int $vendor, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_put_vendor_int(RadiusHandle $h, int $vendor, int $type, int $value, int $options = 0, int $tag = 0): bool {}
function radius_put_vendor_attr(RadiusHandle $h, int $vendor, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_put_vendor_addr(RadiusHandle $h, int $vendor, int $type, string $value, int $options = 0, int $tag = 0): bool {}
function radius_send_request(RadiusHandle $h): int|false {}
function radius_get_attr(RadiusHandle $h): array|int|false {}
function radius_get_tagged_attr_data(string $value): string|false {}
function radius_get_tagged_attr_tag(string $value): int|false {}
function radius_get_vendor_attr(string $raw): array|int|false {}
function radius_cvt_addr(string $data): string {}
function radius_cvt_int(string $data): int {}
function radius_cvt_string(string $data): string|false {}
function radius_salt_encrypt_attr(RadiusHandle $h, string $data): string|false {}
function radius_request_authenticator(RadiusHandle $h): string|false {}
function radius_server_secret(RadiusHandle $h): string|false {}
function radius_demangle(RadiusHandle $h, string $mangled): string|false {}
function radius_demangle_mppe_key(RadiusHandle $h, string $mangled): string|false {}

