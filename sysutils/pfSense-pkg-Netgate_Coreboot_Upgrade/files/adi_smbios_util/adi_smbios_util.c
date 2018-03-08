/*
 * ADI Flash Utility for Broadwell platforms
 *
 * Copyright (C) 2017, ADI Engineering
 *
 * This utility uses a patched version of flashrom that supports reading and
 * writing to specific page ranges instead of the entire part to work around
 * locked regions (like ME).  That utility is assumed to be the same working
 * directory as this executable.
 */

#include <errno.h>
#include <fcntl.h>
#include <getopt.h>
#include <inttypes.h>
#include <stddef.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <uuid/uuid.h>

#include "adi_smbios_types.h"

#define FLASH_FILENAME	"/tmp/flash_backup.bin"
#define DMI_FILENAME	"/tmp/dmi.bin"
#define BIOS_LAYOUT 	"/tmp/bios_layout"
#define CMD_SIZE 	4096

int verbose = 0;

const char version[]="01.00.00.01";

const char flashrom_cmd[] = "flashrom -p internal:boardmismatch=force";
const char cbfstool_cmd[] = "cbfstool";
const char read_all_opts[] = "-r " FLASH_FILENAME;
const char read_4k_opts[] = "-l " BIOS_LAYOUT " -i ifd -r " FLASH_FILENAME;
const char read_coreboot_opts[] = "-l " BIOS_LAYOUT " -i coreboot -r " FLASH_FILENAME;
const char write_all_opts[] = "-w " FLASH_FILENAME;
const char write_4k_opts[] = "-l " BIOS_LAYOUT " -i ifd -w " FLASH_FILENAME;
const char write_coreboot_opts[] = "-l " BIOS_LAYOUT " -i coreboot -w " FLASH_FILENAME;
const char cbfs_smbios_rm_opts[] = FLASH_FILENAME " remove -n adi_smbios";
const char cbfs_smbios_add_opts[] = FLASH_FILENAME " add -f " DMI_FILENAME " -n adi_smbios -t struct -r COREBOOT -b ";

char* log_path = NULL;
FILE* log_file = NULL;

typedef enum {
	READ_DMI,
	PROGRAM_DMI,
	UPDATE_FLASH,
	UPDATE_FLASH_DMI,
	UNKNOWN_OP
} operations_e;

int create_bios_layout(void) ;
void check_system_retCode(int status);
int read_dmi(struct adi_dmi_oem *dmi);
int do_coreboot_read(void);
int do_4k_write(struct adi_dmi_oem* dmi);
int do_coreboot_write(char* coreboot_path);
int do_coreboot_region_write(char* coreboot_path);
int update_dmi(struct adi_dmi_oem *dmi);

typedef enum {
	T1_MFG,
	T1_PRODUCT_NAME,
	T1_VERSION,
	T1_SERIAL,
	T1_UUID,
	T1_SKU,
	T2_MFG,
	T2_PRODUCT_NAME,
	T2_VERSION,
	T2_SERIAL,
	T3_MFG,
	T3_VERSION,
	T3_SERIAL,
	T3_ASSET_TAG,
	T3_OEM
} long_opts_e;

struct option opt_list[] = {
	{
		.name = "system-manufacturer",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_MFG,
	},
	{
		.name = "system-product-name",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_PRODUCT_NAME,
	},
	{
		.name = "system-version",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_VERSION,
	},
	{
		.name = "system-serial-number",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_SERIAL,
	},
	{
		.name = "system-uuid",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_UUID,
	},
	{
		.name = "system-sku",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T1_SKU,
	},
	{
		.name = "board-manufacturer",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T2_MFG,
	},
	{
		.name = "board-product-name",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T2_PRODUCT_NAME,
	},
	{
		.name = "board-version",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T2_VERSION,
	},
	{
		.name = "board-serial-number",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T2_SERIAL,
	},
	{
		.name = "chassis-manufacturer",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T3_MFG,
	},
	{
		.name = "chassis-version",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T3_VERSION,
	},
	{
		.name = "chassis-serial-number",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T3_SERIAL,
	},
	{
		.name = "chassis-asset-tag",
		.has_arg = required_argument,
		.flag = NULL,
		.val = T3_ASSET_TAG,
	},
	{
		.name = NULL,
		.has_arg = 0,
		.flag = NULL,
		.val = 0,
	},
};

/* Helper macro to add a string to the OEM DMI table
 * does not check for overflows */
#define add_dmi_oem_string(dmi, field, str) \
do { \
	(dmi)->hdr.field = (dmi)->hdr.eos; \
	strcpy((char*)&(dmi)->mem[(dmi)->hdr.eos], str); \
	(dmi)->hdr.eos += strlen(str) + 1; \
} while (0)

#define merge_dmi_field(merge, old, new, field) \
do { \
	if ((new)->hdr.field) \
		add_dmi_oem_string((merge), field, \
				(char*)&(new)->mem[(new)->hdr.field]); \
	else if ((old)->hdr.field) \
		add_dmi_oem_string((merge), field, \
				(char*)&(old)->mem[(old)->hdr.field]); \
} while (0)

void merge_dmi_oem(struct adi_dmi_oem *merge,
		   const struct adi_dmi_oem *old,
		   const struct adi_dmi_oem *new)
{
	/* TODO - Check the version/validity of old before merge */

	memset(merge, 0, sizeof(struct adi_dmi_oem));
	merge->hdr.eos = 1;

	/* Merge type-1 fields */
	merge_dmi_field(merge, old, new, t1.manufacturer);
	merge_dmi_field(merge, old, new, t1.product_name);
	merge_dmi_field(merge, old, new, t1.version);
	merge_dmi_field(merge, old, new, t1.serial_number);

	if (!uuid_is_null(new->hdr.t1.uuid))
		uuid_copy(merge->hdr.t1.uuid, new->hdr.t1.uuid);
	else
		uuid_copy(merge->hdr.t1.uuid, old->hdr.t1.uuid);

	merge_dmi_field(merge, old, new, t1.sku);

	/* Merge type-2 fields */
	merge_dmi_field(merge, old, new, t2.manufacturer);
	merge_dmi_field(merge, old, new, t2.product_name);
	merge_dmi_field(merge, old, new, t2.version);
	merge_dmi_field(merge, old, new, t2.serial_number);

	/* Merge type-3 fields */
	merge_dmi_field(merge, old, new, t3.manufacturer);
	merge_dmi_field(merge, old, new, t3.version);
	merge_dmi_field(merge, old, new, t3.serial_number);
	merge_dmi_field(merge, old, new, t3.asset_tag_number);
}

void print_dmi_oem(FILE* f, const struct adi_dmi_oem* dmi)
{
	char uuid_str[37];

	if (f == NULL)
		return;

	fprintf(f, "Type 1 Fields:\n");
	fprintf(f, " system-manufacturer:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t1.manufacturer]);
	fprintf(f, " system-product-name:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t1.product_name]);
	fprintf(f, " system-version:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t1.version]);
	fprintf(f, " system-serial-number:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t1.serial_number]);

	uuid_unparse(dmi->hdr.t1.uuid, uuid_str);
	fprintf(f, " system-uuid:\t\t'%s'\n", uuid_str);

	fprintf(f, " system-sku:\t\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t1.sku]);

	fprintf(f, "Type 2 Fields:\n");
	fprintf(f, " board-manufacturer:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t2.manufacturer]);
	fprintf(f, " board-product-name:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t2.product_name]);
	fprintf(f, " board-version:\t\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t2.version]);
	fprintf(f, " board-serial-number:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t2.serial_number]);

	fprintf(f, "Type 3 Fields:\n");
	fprintf(f, " chassis-manufacturer:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t3.manufacturer]);
	fprintf(f, " chassis-version:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t3.version]);
	fprintf(f, " chassis-serial-number:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t3.serial_number]);
	fprintf(f, " chassis-asset-tag:\t'%s'\n",
		(char*)&dmi->mem[dmi->hdr.t3.asset_tag_number]);
}

int get_opt_val(char* name, struct option* opts)
{
	int i;

	for (i = 0; opt_list[i].name != NULL; i++) {
		if (strcmp(opt_list[i].name, name) == 0)
			break;
	}

	if (opt_list[i].name == NULL)
		return -1;

	return opt_list[i].val;
}

int parse_opt_file(struct adi_dmi_oem* dmi, FILE* stream)
{
	char *line, *field, *value;
	size_t n = 0;
	ssize_t bytes;
	int val, ln;

	line = NULL;
	for (ln = 1; (bytes = getline(&line, &n, stream)) != -1; ln++) {

		/* Ignore comment lines, and blank lines */
		if (line[0] == '#' || line[0] == '\n') {
			free(line);
			line = NULL;
			continue;
		}

		/* eat the terminating new line */
		line[strlen(line) - 1] = '\0';

		/* split line into field name and value */
		value = line;
		field = strsep(&value, "=");
		if (field == NULL) {
			fprintf(stderr, "Invalid field name line %d\n", ln);
			free(line);
			return -1;
		}

		/* Search the opt list for a keyword match */
		val = get_opt_val(field, opt_list);
		if (val == -1) {
			fprintf(stderr, "Invalid field name line %d\n", ln);
			free(line);
			return -1;
		}

		switch (val) {
		case T1_MFG:
			add_dmi_oem_string(dmi, t1.manufacturer, value);
			break;

		case T1_PRODUCT_NAME:
			add_dmi_oem_string(dmi, t1.product_name, value);
			break;

		case T1_VERSION:
			add_dmi_oem_string(dmi, t1.version, value);
			break;

		case T1_SERIAL:
			add_dmi_oem_string(dmi, t1.serial_number, value);
			break;

		case T1_UUID:
			uuid_parse(value, dmi->hdr.t1.uuid);
			break;

		case T1_SKU:
			add_dmi_oem_string(dmi, t1.sku, value);
			break;

		case T2_MFG:
			add_dmi_oem_string(dmi, t2.manufacturer, value);
			break;

		case T2_PRODUCT_NAME:
			add_dmi_oem_string(dmi, t2.product_name, value);
			break;

		case T2_VERSION:
			add_dmi_oem_string(dmi, t2.version, value);
			break;

		case T2_SERIAL:
			add_dmi_oem_string(dmi, t2.serial_number, value);
			break;

		case T3_MFG:
			add_dmi_oem_string(dmi, t3.manufacturer, value);
			break;

		case T3_VERSION:
			add_dmi_oem_string(dmi, t3.version, value);
			break;

		case T3_SERIAL:
			add_dmi_oem_string(dmi, t3.serial_number, value);
			break;

		case T3_ASSET_TAG:
			add_dmi_oem_string(dmi, t3.asset_tag_number, value);
			break;

		default:
			fprintf(stderr, "File parser died\n");
			break;
		};

		free(line);
		line = NULL;
	}

	if (bytes == -1) {
		if (!feof(stream))
			fprintf(stderr, "config parsing failed: %s\n",
				strerror(errno));
	}

	return 0;
}

void print_usage() {
	int i;

	printf("adi_smbios_util  -- version:%s \n", version);
	printf(" Usage: adi_smbios_util -r -w -u <bios file> -c <config file> -l <log file> -b <bin file> -h -v\n");
	printf(" -r read the DMI info from flash\n");
	printf(" -w write the DMI info to flash\n");
	printf(" -u update the coreboot region\n");
	printf(" -c read DMI fields from config file\n");
	printf(" -l log detailed information to log file\n");
	printf(" -b save a binary copy of the updated DMI region to file\n");
	printf(" -h help\n");
	printf(" -v verbose\n");

	printf(" dmi_fields:\n");
	for (i = 0; opt_list[i].name != NULL; i++)
		printf("\t%s\n", opt_list[i].name);

	return;
}

int main(int argc, char *argv[]) {
	struct adi_dmi_oem base_dmi;
	struct adi_dmi_oem over_dmi;
	struct adi_dmi_oem merge_dmi;
	char *opts_path = NULL;
	char *bin_path = NULL;
	FILE *opts_file = NULL;
	char *coreboot_path = NULL;
	char *cmd = malloc(CMD_SIZE);
	operations_e my_op = UNKNOWN_OP;
	int option, ret, bin_fd;
	mode_t mode = S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH;
	uint8_t dmi_updated = 0;

	/* clear the dmi_fields */
	memset(&base_dmi, 0, sizeof(struct adi_dmi_oem));
	memset(&over_dmi, 0, sizeof(struct adi_dmi_oem));
	memset(&merge_dmi, 0, sizeof(struct adi_dmi_oem));

	/* point eos offset to 1, 0 reserved for NULL strings */
	base_dmi.hdr.eos = 1;
	over_dmi.hdr.eos = 1;
	merge_dmi.hdr.eos = 1;

	/* parse commandline args */
	while ((option = getopt_long(argc, argv, "vrwhu:c:l:b:", opt_list, NULL)) != -1) {
		switch (option) {
		case 'v':
			verbose = 1;
			break;

		case 'r':
			my_op = READ_DMI;
			break;

		case 'w':
			my_op =  PROGRAM_DMI;
			break;

		case 'u':
			my_op = UPDATE_FLASH;
			coreboot_path = optarg;
			break;

		case 'c':
			opts_path = malloc(strlen(optarg));
			strcpy(opts_path, optarg);
			break;


		case 'l':
			log_path = malloc(strlen(optarg));
			strcpy(log_path, optarg);
			break;

		case 'b':
			bin_path = malloc(strlen(optarg));
			strcpy(bin_path, optarg);
			break;

		case T1_MFG:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t1.manufacturer, optarg);
			break;

		case T1_PRODUCT_NAME:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t1.product_name, optarg);
			break;

		case T1_VERSION:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t1.version, optarg);
			break;

			case T1_SERIAL:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t1.serial_number, optarg);
			break;

		case T1_UUID:
			dmi_updated = 1;
			uuid_parse(optarg, over_dmi.hdr.t1.uuid);
			break;

		case T1_SKU:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t1.sku, optarg);
			break;

		case T2_MFG:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t2.manufacturer, optarg);
			break;

		case T2_PRODUCT_NAME:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t2.product_name, optarg);
			break;

		case T2_VERSION:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t2.version, optarg);
			break;

		case T2_SERIAL:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t2.serial_number, optarg);
			break;

		case T3_MFG:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t3.manufacturer, optarg);
			break;

		case T3_VERSION:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t3.version, optarg);
			break;

		case T3_SERIAL:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t3.serial_number, optarg);
			break;

		case T3_ASSET_TAG:
			dmi_updated = 1;
			add_dmi_oem_string(&over_dmi, t3.asset_tag_number, optarg);
			break;

		case 'h' :
			print_usage();
			exit(0);

		case '?' :
		default:
			print_usage();
			exit(-1);
		};
	}

	if (log_path != NULL) {
		log_file = fopen(log_path, "w");
		if (log_file == NULL) {
			fprintf(stderr, "Unable to open log file %s: %s\n",
				log_path, strerror(errno));
			exit(-1);
		}
	}

	/* Parse the default DMI options file */
	if (opts_path != NULL) {
		opts_file = fopen(opts_path, "r");
		if (opts_file == NULL) {
			fprintf(stderr, "Unable to open config file %s: %s\n",
				opts_path, strerror(errno));
			exit(-1);
		}

		if (parse_opt_file(&base_dmi, opts_file) != 0 ) {
			fprintf(stderr, "Invalid config file entry\n");
			exit(-1);
		};

		if (log_file != NULL) {
			fprintf(log_file, "\nDMI fields from %s:\n-----\n",
				opts_path);
			print_dmi_oem(log_file, &base_dmi);
		}

		fclose(opts_file);
		opts_file = NULL;
	}

	if (log_file != NULL) {
		fprintf(log_file, "\nDMI fields from commandline:\n-----\n");
		print_dmi_oem(log_file, &over_dmi);
	}

	/* Merge the command line values over the config file values */
	merge_dmi_oem(&merge_dmi, &base_dmi, &over_dmi);
	/* This becomes the new overwrite data */
	memcpy(&over_dmi, &merge_dmi, sizeof(over_dmi));

	if (log_file != NULL) {
		fprintf(log_file,
			"\nMerged DMI values to write to flash:\n-----\n");
		print_dmi_oem(log_file, &merge_dmi);
	}

	/* Write a binary copy of the DMI region to a file if requested */
	if (bin_path != NULL) {
		bin_fd = open(bin_path, O_WRONLY | O_CREAT | O_TRUNC, mode);
		if (bin_fd == -1) {
			fprintf(stderr, "Unable to open %s for writing: %s\n",
				bin_path, strerror(errno));
			exit(-1);
		}

		write(bin_fd, &merge_dmi, sizeof(merge_dmi));
		close(bin_fd);
	}

	if (my_op == UPDATE_FLASH && dmi_updated)
		my_op =  UPDATE_FLASH_DMI;

	/* create BIOS layout file */

	ret = create_bios_layout();
	if (ret != 0) {
		printf("Failed to create BIOS layout file, exiting ...\n");
		return -1;
	}

	switch(my_op) {
		case READ_DMI:
			do_coreboot_read();
			read_dmi(&base_dmi);
			print_dmi_oem(stdout, &base_dmi);
			break;

		case PROGRAM_DMI:
			printf("Programming DMI...\n");

			/* use flashrom read the descriptor page from flash */
			do_coreboot_read();
			read_dmi(&base_dmi);

			/* merge the config file & command line opts */
			merge_dmi_oem(&merge_dmi, &base_dmi, &over_dmi);

			/* TODO: Do something smarter with the version here */
			merge_dmi.hdr.version = 0x8001;

			/* Update the bios file with the new DMI region */
			update_dmi(&merge_dmi);

			/* Write the updated region back to the flash part */
			do_coreboot_write(FLASH_FILENAME);
			break;

		case UPDATE_FLASH:
			printf("Updating coreboot region  ...\n");

			/* read current flash and extract DMI */
			do_coreboot_read();
			read_dmi(&base_dmi);
	
			/* copy the update file overtop of the temp file we
			 * write later
			 */
			snprintf(cmd, CMD_SIZE, "cp %s %s", coreboot_path, FLASH_FILENAME);
			check_system_retCode(system(cmd));

			if(opts_path == NULL) {
			/* update coreboot , retain DMI from the current flash */
				/* copy DMI to new coreboot*/
				update_dmi(&base_dmi);

			}
			else {
			/* config file provided as part of the update command, merge it with base  */
					
				/* merge the config file & command line opts */
				merge_dmi_oem(&merge_dmi, &base_dmi, &over_dmi);

				/* TODO: Do something smarter with the version here */
				merge_dmi.hdr.version = 0x8001;

				/* Update the bios file with the new DMI region */
				update_dmi(&merge_dmi);
			
			}
			/* write the entire new coreboot region to the flash part */
			do_coreboot_region_write(FLASH_FILENAME);
			

			

			break;

		case UPDATE_FLASH_DMI:
			printf("Programming DMI...\n");

			/* use flashrom read the descriptor page from flash */
			do_coreboot_read();
			read_dmi(&base_dmi);

			/* copy the update file overtop of the temp file we
			 * write later
			 */
			snprintf(cmd, CMD_SIZE, "cp %s %s",
				coreboot_path, FLASH_FILENAME);
			check_system_retCode(system(cmd));

			/* merge the config file & command line opts */
			merge_dmi_oem(&merge_dmi, &base_dmi, &over_dmi);

			/* TODO: Do something smarter with the version here */
			merge_dmi.hdr.version = 0x8001;

			/* Update the bios file with the new DMI region */
			update_dmi(&merge_dmi);

			printf("Updating coreboot region of flash ...\n");
			do_coreboot_region_write(FLASH_FILENAME);
			break;

		default:
			printf("Unkown Operation, Aborting...\n");
			exit (-1);

	}

	return 0;
}

/* Read the DMI table from the OEM region in the 4k descriptor block */
int do_coreboot_read(void)
{
	char cmd[CMD_SIZE];

	printf("Reading coreboot region...\n");
	snprintf(cmd, CMD_SIZE, "%s %s", flashrom_cmd,
		 read_coreboot_opts);
	printf("Reading coreboot region...%s \n", cmd);
	if (log_path != NULL) {
		strncat(cmd, " 2>&1 >> ", CMD_SIZE);
		strncat(cmd, log_path, CMD_SIZE);
		fprintf(log_file, "%s\n", cmd);
	}
	check_system_retCode(system(cmd));

#if 0
	read_dmi(dmi);
	print_dmi_oem(stdout, dmi);
	if (log_path != NULL)
		print_dmi_oem(log_file, dmi);
#endif

	return 0;
}

/* Read the DMI table from the OEM region in the 4k descriptor block */
int do_4k_write(struct adi_dmi_oem* dmi)
{
	char* cmd = malloc(CMD_SIZE);

	printf("Writing flash descriptor region...\n");
	snprintf(cmd, CMD_SIZE, "%s %s", flashrom_cmd,
		 write_4k_opts);
	if (log_path != NULL) {
		strncat(cmd, " 2>&1 >> ", CMD_SIZE);
		strncat(cmd, log_path, CMD_SIZE);
		fprintf(log_file, "%s\n", cmd);
	}
	check_system_retCode(system(cmd));

	return 0;
}

/*write entire coreboot region from the file provided to flash */
int do_coreboot_region_write(char* coreboot_path)
{
	char* cmd = malloc(CMD_SIZE);


	/* write to flash */
	printf("Writing coreboot region...\n");
	snprintf(cmd, CMD_SIZE, "%s %s", flashrom_cmd,
		 write_coreboot_opts);
	if (log_path != NULL) {
		strncat(cmd, " 2>&1 >> ", CMD_SIZE);
		strncat(cmd, log_path, CMD_SIZE);
		fprintf(log_file, "%s\n", cmd);
	}
	check_system_retCode(system(cmd));

	free(cmd);

	return 0;
}



/* Read the DMI table from the OEM region in the 4k descriptor block */
int do_coreboot_write(char* coreboot_path)
{
	char* cmd = malloc(CMD_SIZE);
	mode_t mode = S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH;
	int coreboot_fd = -1, flash_fd = -1;
	uint32_t offset = CONFIG_ADI_SMBIOS_BASE + CONFIG_ROM_SIZE;
	void *buf = NULL;
	int rc = 0;

	/* get descriptors for the coreboot.rom and flash files */
	coreboot_fd = open(coreboot_path, O_RDONLY);
	if (coreboot_fd == -1) {
		fprintf(stderr, "Unable to read %s: %s\n",
			coreboot_path, strerror(errno));
		rc = -1;
		goto out;
	}
	flash_fd = open(FLASH_FILENAME, O_WRONLY | O_CREAT, mode);
	if (flash_fd == -1) {
		fprintf(stderr, "Unable to read %s: %s\n",
			FLASH_FILENAME, strerror(errno));
		rc = -1;
		goto out;
	}

	if ((buf = malloc(CONFIG_ADI_SMBIOS_SIZE)) == NULL) {
		fprintf(stderr, "malloc error");
		rc = -1;
		goto out;
	}
	/* copy coreboot contents to coreboot region of flash file*/
	printf("Writing coreboot SMBIOS region 0x%x bytes @ 0x%x...\n",
		CONFIG_ADI_SMBIOS_SIZE, offset);
	lseek(flash_fd, offset, SEEK_SET);
	lseek(coreboot_fd, offset, SEEK_SET);
	if (read(flash_fd, &buf, CONFIG_ADI_SMBIOS_SIZE) <= 0) {
		fprintf(stderr, "Error reading");
		rc = -1;
		goto out;
	}
	if (write(flash_fd, &buf, CONFIG_ADI_SMBIOS_SIZE) == -1) {
		fprintf(stderr, "Error writing");
		rc = -1;
		goto out;
	}

	/* write to flash */
	printf("Writing coreboot region...\n");
	snprintf(cmd, CMD_SIZE, "%s %s", flashrom_cmd,
		 write_coreboot_opts);
	if (log_path != NULL) {
		strncat(cmd, " 2>&1 >> ", CMD_SIZE);
		strncat(cmd, log_path, CMD_SIZE);
		fprintf(log_file, "%s\n", cmd);
	}
	check_system_retCode(system(cmd));

out:
	if (coreboot_fd)
		close(coreboot_fd);
	if (flash_fd)
		close(flash_fd);
	if (buf)
		free(buf);
	if (rc == 0)
		return 0;
	else
		exit(rc);
}


int read_dmi(struct adi_dmi_oem *dmi)
{
	struct stat fileStat;
	int fd = open(FLASH_FILENAME, O_RDONLY);
	uint32_t offset = CONFIG_ADI_SMBIOS_BASE + CONFIG_ROM_SIZE;

	if (fd == -1) {
		fprintf(stderr, "Failed open flash file %s: %s\n",
			FLASH_FILENAME, strerror(errno));
		return -1;
	}

	fstat(fd, &fileStat);
	if (fileStat.st_size != CONFIG_ROM_SIZE  ) {
		fprintf(stderr, "Incorrect flash image file size.\n");
		close(fd);
		return -1;
	}

	lseek(fd, offset, SEEK_SET);
	read(fd, dmi, sizeof(struct adi_dmi_oem));
	close(fd);

	return 0;
}



/* create oem section file with DMI information updated */
int update_dmi (struct adi_dmi_oem *dmi)
{
	char* cmd = malloc(CMD_SIZE);
	mode_t mode = S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH;
	int fd = open (DMI_FILENAME, O_WRONLY | O_CREAT, mode);
	uint32_t offset = CONFIG_ADI_SMBIOS_BASE;

	if (fd == -1) {
		fprintf(stderr, "Unable to open %s for read: %s\n",
			FLASH_FILENAME, strerror(errno));
		return -1;
	}

	write (fd, dmi, sizeof(struct adi_dmi_oem));
	close (fd);

	/* cbfstool won't overwrite files in place, so delete the old region
	 * if it exists, then recreate it with the new info
	 */
	printf("Removing old SMBIOS information from CBFS...\n");
	snprintf(cmd, CMD_SIZE, "%s %s", cbfstool_cmd,
		cbfs_smbios_rm_opts);
	check_system_retCode(system(cmd));

	printf("Adding new SMBIOS information to CBFS...\n");
	snprintf(cmd, CMD_SIZE, "%s %s 0x%0x", cbfstool_cmd,
		cbfs_smbios_add_opts, offset);
	check_system_retCode(system(cmd));

	return 0;
}

/* check system() return code, bail out if there is an error */
void check_system_retCode(int status)
{
	int ret = 0;

	printf("system() exit with return_code=%d", status);
	if(status != -1 || status != 0) {
		if (WIFEXITED(status)) {
			ret = WEXITSTATUS(status);
			printf(", child exit status=%d\n", ret);
		}
		else {
			printf(", child exited abnormally\n");
			exit(-1);
		}
	}
	else {
		fprintf(stderr, "Unable to launch command\n");
		fprintf(stderr, "Unable to launch command\n");
		exit(-1);
	}

	if (ret)
		exit(ret);

	return;
}

int create_bios_layout(void)
{
	FILE *ptr_file = fopen(BIOS_LAYOUT, "w");
	uint32_t cbfs_start = CONFIG_ROM_SIZE - CONFIG_CBFS_SIZE;

	if (!ptr_file)
		return -1;

	/* intel flash descriptor */
	fprintf(ptr_file, "0x00000000:0x00000fff ifd\n");

	/* ME/IE/NVM/unused */
	fprintf(ptr_file, "0x00001000:0x%08X midrom\n", cbfs_start - 1);

	/* coreboot */
	fprintf(ptr_file, "0x%08X:0x%08X coreboot\n",
		cbfs_start, CONFIG_ROM_SIZE - 1);
	fclose(ptr_file);

	return  0;
}
