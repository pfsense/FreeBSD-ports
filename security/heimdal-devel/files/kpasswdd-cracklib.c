#include <stdlib.h>
#include <string.h>
#include <krb5.h>
#include <packer.h>

int version = 0;

const char *
passwd_check(krb5_context context, krb5_principal principal,
    krb5_data *password)
{
		char *p, *result;

		p = malloc(password->length + 1);
		if (p == NULL)
				return "out of memory";
		memcpy(p, password->data, password->length);
		p[password->length] = '\0';
		result = FascistCheck(p, LOCALBASE "/libdata/cracklib/cracklib-words");
		free(p);
		return result;
}
