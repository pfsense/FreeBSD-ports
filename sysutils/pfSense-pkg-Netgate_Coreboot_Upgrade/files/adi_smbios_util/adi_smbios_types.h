#ifndef _ADI_SMBIOS_TYPES_H_
#define _ADI_SMBIOS_TYPES_H_

#include <stdint.h>

struct adi_smbios_type1 {
	uint8_t manufacturer;
	uint8_t product_name;
	uint8_t version;
	uint8_t serial_number;
	uint8_t uuid[16];
	uint8_t sku;
} __attribute__((packed));

struct adi_smbios_type2 {
	uint8_t manufacturer;
	uint8_t product_name;
	uint8_t version;
	uint8_t serial_number;
} __attribute__((packed));

struct adi_smbios_type3 {
	uint8_t manufacturer;
	uint8_t version;
	uint8_t serial_number;
	uint8_t asset_tag_number;
} __attribute__((packed));

struct adi_dmi_oem_header {
	uint16_t version;
	uint16_t eos;
	struct adi_smbios_type1 t1;
	struct adi_smbios_type2 t2;
	struct adi_smbios_type3 t3;
} __attribute__((packed));

struct adi_dmi_oem {
	struct adi_dmi_oem_header hdr;
	uint8_t mem[CONFIG_ADI_SMBIOS_SIZE - sizeof(struct adi_dmi_oem_header)];
} __attribute__((packed));

#endif /* #ifndef _ADI_SMBIOS_TYPES_H_ */
