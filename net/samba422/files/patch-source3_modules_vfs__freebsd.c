--- source3/modules/vfs_freebsd.c.orig	2025-07-11 10:55:17 UTC
+++ source3/modules/vfs_freebsd.c
@@ -0,0 +1,699 @@
+/*
+ * This module implements VFS calls specific to FreeBSD
+ *
+ * Copyright (C) Timur I. Bakeyev, 2018
+ *
+ * This program is free software; you can redistribute it and/or modify
+ * it under the terms of the GNU General Public License as published by
+ * the Free Software Foundation; either version 3 of the License, or
+ * (at your option) any later version.
+ *
+ * This program is distributed in the hope that it will be useful,
+ * but WITHOUT ANY WARRANTY; without even the implied warranty of
+ * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
+ * GNU General Public License for more details.
+ *
+ * You should have received a copy of the GNU General Public License
+ * along with this program; if not, see <http://www.gnu.org/licenses/>.
+ */
+
+#include "includes.h"
+
+#include "lib/util/tevent_unix.h"
+#include "lib/util/tevent_ntstatus.h"
+#include "system/filesys.h"
+#include "smbd/smbd.h"
+
+#include <sys/sysctl.h>
+
+static int vfs_freebsd_debug_level = DBGC_VFS;
+
+#undef DBGC_CLASS
+#define DBGC_CLASS vfs_freebsd_debug_level
+
+#ifndef EXTATTR_MAXNAMELEN
+#define EXTATTR_MAXNAMELEN		UINT8_MAX
+#endif
+
+#define EXTATTR_NAMESPACE(NS)		EXTATTR_NAMESPACE_ ## NS, \
+					EXTATTR_NAMESPACE_ ## NS ## _STRING ".", \
+					.data.len = (sizeof(EXTATTR_NAMESPACE_ ## NS ## _STRING ".") - 1)
+
+#define EXTATTR_EMPTY			0x00
+#define EXTATTR_USER			0x01
+#define EXTATTR_SYSTEM			0x02
+#define EXTATTR_SECURITY		0x03
+#define EXTATTR_TRUSTED			0x04
+
+enum extattr_mode {
+	FREEBSD_EXTATTR_SECURE,
+	FREEBSD_EXTATTR_COMPAT,
+	FREEBSD_EXTATTR_LEGACY
+};
+
+struct freebsd_handle_data {
+	enum extattr_mode extattr_mode;
+};
+
+typedef struct {
+	int namespace;
+	char name[EXTATTR_MAXNAMELEN+1];
+	union {
+		uint16_t len;
+		uint16_t flags;
+	} data;
+} extattr_attr;
+
+static const struct enum_list extattr_mode_param[] = {
+	{ FREEBSD_EXTATTR_SECURE, "secure" },		/*  */
+	{ FREEBSD_EXTATTR_COMPAT, "compat" },		/*  */
+	{ FREEBSD_EXTATTR_LEGACY, "legacy" },		/*  */
+	{ -1, NULL }
+};
+
+/* XXX: This order doesn't match namespace ids order! */
+static extattr_attr extattr[] = {
+	{ EXTATTR_NAMESPACE(EMPTY) },
+	{ EXTATTR_NAMESPACE(SYSTEM) },
+	{ EXTATTR_NAMESPACE(USER) },
+};
+
+
+static bool freebsd_in_jail(void) {
+	int val = 0;
+	size_t val_len = sizeof(val);
+
+	if((sysctlbyname("security.jail.jailed", &val, &val_len, NULL, 0) != -1) && val == 1) {
+		return true;
+	}
+	return false;
+}
+
+
+static uint16_t freebsd_map_attrname(const char *name)
+{
+	if(name == NULL || name[0] == '\0') {
+		return EXTATTR_EMPTY;
+	}
+
+	switch(name[0]) {
+		case 'u':
+			if(strncmp(name, "user.", 5) == 0)
+				return EXTATTR_USER;
+			break;
+		case 't':
+			if(strncmp(name, "trusted.", 8) == 0)
+				return EXTATTR_TRUSTED;
+			break;
+		case 's':
+			/* name[1] could be any character, including '\0' */
+			switch(name[1]) {
+				case 'e':
+					if(strncmp(name, "security.", 9) == 0)
+						return EXTATTR_SECURITY;
+					break;
+				case 'y':
+					if(strncmp(name, "system.", 7) == 0)
+						return EXTATTR_SYSTEM;
+					break;
+			}
+			break;
+	}
+	return EXTATTR_USER;
+}
+
+
+/* security, system, trusted or user */
+static extattr_attr* freebsd_map_xattr(enum extattr_mode extattr_mode, const char *name, extattr_attr *attr)
+{
+	int attrnamespace = EXTATTR_NAMESPACE_EMPTY;
+	const char *p, *attrname = name;
+
+	if(name == NULL || name[0] == '\0') {
+		return NULL;
+	}
+
+	if(attr == NULL) {
+		return NULL;
+	}
+
+	uint16_t flags = freebsd_map_attrname(name);
+
+	switch(flags) {
+		case EXTATTR_SECURITY:
+		case EXTATTR_TRUSTED:
+		case EXTATTR_SYSTEM:
+			attrnamespace = (extattr_mode == FREEBSD_EXTATTR_SECURE) ?
+					EXTATTR_NAMESPACE_SYSTEM :
+					EXTATTR_NAMESPACE_USER;
+			break;
+		case EXTATTR_USER:
+			attrnamespace = EXTATTR_NAMESPACE_USER;
+			break;
+		default:
+			/* Default to "user" namespace if nothing else was specified */
+			attrnamespace = EXTATTR_NAMESPACE_USER;
+			flags = EXTATTR_USER;
+			break;
+	}
+
+	if (extattr_mode == FREEBSD_EXTATTR_LEGACY) {
+		switch(flags) {
+			case EXTATTR_SECURITY:
+				attrname = name + 9;
+				break;
+			case EXTATTR_TRUSTED:
+				attrname = name + 8;
+				break;
+			case EXTATTR_SYSTEM:
+				attrname = name + 7;
+				break;
+			case EXTATTR_USER:
+				attrname = name + 5;
+				break;
+			default:
+				attrname = ((p=strchr(name, '.')) != NULL) ? p + 1 : name;
+				break;
+		}
+	}
+
+	attr->namespace = attrnamespace;
+	attr->data.flags = flags;
+	strlcpy(attr->name, attrname, EXTATTR_MAXNAMELEN + 1);
+
+	return attr;
+}
+
+
+static ssize_t extattr_size(struct files_struct *fsp, extattr_attr *attr)
+{
+	ssize_t result;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	int fd = fsp_get_pathref_fd(fsp);
+
+	if (fsp->fsp_flags.is_pathref) {
+		const char *path = fsp->fsp_name->base_name;
+		if (fsp->fsp_flags.have_proc_fds) {
+			char buf[PATH_MAX];
+			path = sys_proc_fd_path(fd, &buf);
+			if (path == NULL) {
+				return -1;
+			}
+		}
+		/*
+		 * This is no longer a handle based call.
+		 */
+		return extattr_get_file(path, attr->namespace, attr->name, NULL, 0);
+	}
+	else {
+		return extattr_get_fd(fd, attr->namespace, attr->name, NULL, 0);
+	}
+}
+
+/*
+ * The list of names is returned as an unordered array of NULL-terminated
+ * character strings (attribute names are separated by NULL characters),
+ * like this:
+ *      user.name1\0system.name1\0user.name2\0
+ *
+ * Filesystems like ext2, ext3 and XFS which implement POSIX ACLs using
+ * extended attributes, might return a list like this:
+ *      system.posix_acl_access\0system.posix_acl_default\0
+ */
+/*
+ * The extattr_list_file() returns a list of attributes present in the
+ * requested namespace. Each list entry consists of a single byte containing
+ * the length of the attribute name, followed by the attribute name. The
+ * attribute name is not terminated by ASCII 0 (nul).
+*/
+static ssize_t freebsd_extattr_list(struct files_struct *fsp, enum extattr_mode extattr_mode, char *list, size_t size)
+{
+	ssize_t list_size, total_size = 0;
+	char *p, *q, *list_end;
+	int len;
+	/*
+	 Ignore all but user namespace when we are not root or in jail
+	 See: https://bugzilla.samba.org/show_bug.cgi?id=10247
+	*/
+	bool as_root = (geteuid() == 0);
+
+	int ns = (extattr_mode == FREEBSD_EXTATTR_SECURE && as_root) ? 1 : 2;
+
+	int fd = fsp_get_pathref_fd(fsp);
+
+	/* Iterate through extattr(2) namespaces */
+	for(; ns < ARRAY_SIZE(extattr); ns++) {
+		list_size = -1;
+
+		if (fsp->fsp_flags.is_pathref) {
+			const char *path = fsp->fsp_name->base_name;
+			if (fsp->fsp_flags.have_proc_fds) {
+				char buf[PATH_MAX];
+				path = sys_proc_fd_path(fd, &buf);
+				if (path == NULL) {
+					return -1;
+				}
+			}
+			/*
+			 * This is no longer a handle based call.
+			 */
+			list_size = extattr_list_file(path, extattr[ns].namespace, list, size);
+		}
+		else {
+			list_size = extattr_list_fd(fd, extattr[ns].namespace, list, size);
+		}
+		/* Some error happend. Errno should be set by the previous call */
+		if(list_size < 0)
+			return -1;
+		/* No attributes in this namespace */
+		if(list_size == 0)
+			continue;
+		/*
+		 Call with an empty buffer may be used to calculate
+		 necessary buffer size.
+		*/
+		if(list == NULL) {
+			/*
+			 XXX: Unfortunately, we can't say, how many attributes were
+			 returned, so here is the potential problem with the emulation.
+			*/
+			if(extattr_mode == FREEBSD_EXTATTR_LEGACY) {
+				/*
+				 Take the worse case of one char attribute names -
+				 two bytes per name plus one more for sanity.
+				*/
+				total_size += list_size + (list_size/2 + 1)*extattr[ns].data.len;
+			}
+			else {
+				total_size += list_size;
+			}
+			continue;
+		}
+
+		if(extattr_mode == FREEBSD_EXTATTR_LEGACY) {
+			/* Count necessary offset to fit namespace prefixes */
+			int extra_len = 0;
+			uint16_t flags;
+			list_end = list + list_size;
+			for(list_size = 0, p = q = list; p < list_end; p += len) {
+				len = p[0] + 1;
+				(void)strlcpy(q, p + 1, len);
+				flags = freebsd_map_attrname(q);
+				/* Skip secure attributes for non-root user */
+				if(extattr_mode != FREEBSD_EXTATTR_SECURE && !as_root && flags > EXTATTR_USER) {
+					continue;
+				}
+				if(flags <= EXTATTR_USER) {
+					/* Don't count trailing '\0' */
+					extra_len += extattr[ns].data.len;
+				}
+				list_size += len;
+				q += len;
+			}
+			total_size += list_size + extra_len;
+			/* Buffer is too small to fit the results */
+			if(total_size > size) {
+				errno = ERANGE;
+				return -1;
+			}
+			/* Shift results backwards, so we can prepend prefixes */
+			list_end = list + extra_len;
+			p = (char*)memmove(list_end, list, list_size);
+			/*
+			 We enter the loop with `p` pointing to the shifted list and
+			 `extra_len` having the total margin between `list` and `p`
+			*/
+			for(list_end += list_size; p < list_end; p += len) {
+				len = strlen(p) + 1;
+				flags = freebsd_map_attrname(p);
+				if(flags <= EXTATTR_USER) {
+					/* Add namespace prefix */
+					(void)strncpy(list, extattr[ns].name, extattr[ns].data.len);
+					list += extattr[ns].data.len;
+				}
+				/* Append attribute name */
+				(void)strlcpy(list, p, len);
+				list += len;
+			}
+		}
+		else {
+			/* Convert UCSD strings into nul-terminated strings */
+			for(list_end = list + list_size; list < list_end; list += len) {
+				len = list[0] + 1;
+				(void)strlcpy(list, list + 1, len);
+			}
+			total_size += list_size;
+		}
+	}
+	return total_size;
+}
+
+/*
+static ssize_t freebsd_fgetxattr_size(struct vfs_handle_struct *handle,
+				     struct files_struct *fsp,
+				     const char *name)
+{
+	struct freebsd_handle_data *data;
+	extattr_attr attr;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	SMB_VFS_HANDLE_GET_DATA(handle, data,
+				struct freebsd_handle_data,
+				return -1);
+
+	if(!freebsd_map_xattr(data->extattr_mode, name, &attr)) {
+		errno = EINVAL;
+		return -1;
+	}
+
+	if(data->extattr_mode != FREEBSD_EXTATTR_SECURE && geteuid() != 0 && attr.data.flags > EXTATTR_USER) {
+		errno = ENOATTR;
+		return -1;
+	}
+
+	return extattr_size(fsp, &attr);
+}
+*/
+
+/* VFS entries */
+static ssize_t freebsd_fgetxattr(struct vfs_handle_struct *handle,
+				 struct files_struct *fsp,
+				 const char *name,
+				 void *value,
+				 size_t size)
+{
+#if defined(HAVE_XATTR_EXTATTR)
+	struct freebsd_handle_data *data;
+	extattr_attr attr;
+	ssize_t res;
+	int fd;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	SMB_VFS_HANDLE_GET_DATA(handle, data,
+				struct freebsd_handle_data,
+				return -1);
+
+	if(!freebsd_map_xattr(data->extattr_mode, name, &attr)) {
+		errno = EINVAL;
+		return -1;
+	}
+
+	/* Filter out 'secure' entries */
+	if(data->extattr_mode != FREEBSD_EXTATTR_SECURE && geteuid() != 0 && attr.data.flags > EXTATTR_USER) {
+		errno = ENOATTR;
+		return -1;
+	}
+
+	/*
+	 * The BSD implementation has a nasty habit of silently truncating
+	 * the returned value to the size of the buffer, so we have to check
+	 * that the buffer is large enough to fit the returned value.
+	 */
+	if((res=extattr_size(fsp, &attr)) < 0) {
+		return -1;
+	}
+
+	if (size == 0) {
+		return res;
+	}
+	else if (res > size) {
+		errno = ERANGE;
+		return -1;
+	}
+
+	fd = fsp_get_pathref_fd(fsp);
+
+	if (fsp->fsp_flags.is_pathref) {
+		const char *path = fsp->fsp_name->base_name;
+		if (fsp->fsp_flags.have_proc_fds) {
+			char buf[PATH_MAX];
+			path = sys_proc_fd_path(fd, &buf);
+			if (path == NULL) {
+				return -1;
+			}
+		}
+		/*
+		 * This is no longer a handle based call.
+		 */
+		return extattr_get_file(path, attr.namespace, attr.name, value, size);
+	}
+	else {
+		return extattr_get_fd(fd, attr.namespace, attr.name, value, size);
+	}
+	return -1;
+#else
+	errno = ENOSYS;
+	return -1;
+#endif
+}
+
+
+static ssize_t freebsd_flistxattr(struct vfs_handle_struct *handle,
+				  struct files_struct *fsp,
+				  char *list,
+				  size_t size)
+{
+#if defined(HAVE_XATTR_EXTATTR)
+	struct freebsd_handle_data *data;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	SMB_VFS_HANDLE_GET_DATA(handle, data,
+				struct freebsd_handle_data,
+				return -1);
+
+	return freebsd_extattr_list(fsp, data->extattr_mode, list, size);
+#else
+	errno = ENOSYS;
+	return -1;
+#endif
+}
+
+
+static int freebsd_fremovexattr(struct vfs_handle_struct *handle,
+				struct files_struct *fsp,
+				const char *name)
+{
+#if defined(HAVE_XATTR_EXTATTR)
+	struct freebsd_handle_data *data;
+	extattr_attr attr;
+	int fd;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	SMB_VFS_HANDLE_GET_DATA(handle, data,
+				struct freebsd_handle_data,
+				return -1);
+
+	if(!freebsd_map_xattr(data->extattr_mode, name, &attr)) {
+		errno = EINVAL;
+		return -1;
+	}
+
+	/* Filter out 'secure' entries */
+	if(data->extattr_mode != FREEBSD_EXTATTR_SECURE && geteuid() != 0 && attr.data.flags > EXTATTR_USER) {
+		errno = ENOATTR;
+		return -1;
+	}
+
+	fd = fsp_get_pathref_fd(fsp);
+
+	if (fsp->fsp_flags.is_pathref) {
+		const char *path = fsp->fsp_name->base_name;
+		if (fsp->fsp_flags.have_proc_fds) {
+			char buf[PATH_MAX];
+			path = sys_proc_fd_path(fd, &buf);
+			if (path == NULL) {
+				return -1;
+			}
+		}
+		/*
+		 * This is no longer a handle based call.
+		 */
+		return extattr_delete_file(path, attr.namespace, attr.name);
+	}
+	else {
+		return extattr_delete_fd(fd, attr.namespace, attr.name);
+	}
+	return -1;
+#else
+	errno = ENOSYS;
+	return -1;
+#endif
+}
+
+
+static int freebsd_fsetxattr(struct vfs_handle_struct *handle,
+			     struct files_struct *fsp,
+			     const char *name,
+			     const void *value,
+			     size_t size,
+			     int flags)
+{
+#if defined(HAVE_XATTR_EXTATTR)
+	struct freebsd_handle_data *data;
+	extattr_attr attr;
+	ssize_t res;
+	int fd;
+
+	SMB_ASSERT(!fsp_is_alternate_stream(fsp));
+
+	SMB_VFS_HANDLE_GET_DATA(handle, data,
+				struct freebsd_handle_data,
+				return -1);
+
+	if(!freebsd_map_xattr(data->extattr_mode, name, &attr)) {
+		errno = EINVAL;
+		return -1;
+	}
+
+	/* Filter out 'secure' entries */
+	if(data->extattr_mode != FREEBSD_EXTATTR_SECURE && geteuid() != 0 && attr.data.flags > EXTATTR_USER) {
+		errno = ENOATTR;
+		return -1;
+	}
+
+	if (flags) {
+		/* Check attribute existence */
+		res = extattr_size(fsp, &attr);
+		if (res < 0) {
+			/* REPLACE attribute, that doesn't exist */
+			if ((flags & XATTR_REPLACE) && errno == ENOATTR) {
+				errno = ENOATTR;
+				return -1;
+			}
+			/* Ignore other errors */
+		}
+		else {
+			/* CREATE attribute, that already exists */
+			if (flags & XATTR_CREATE) {
+				errno = EEXIST;
+				return -1;
+			}
+		}
+	}
+
+	fd = fsp_get_pathref_fd(fsp);
+
+	if (fsp->fsp_flags.is_pathref) {
+		const char *path = fsp->fsp_name->base_name;
+		if (fsp->fsp_flags.have_proc_fds) {
+			char buf[PATH_MAX];
+			path = sys_proc_fd_path(fd, &buf);
+			if (path == NULL) {
+				return -1;
+			}
+		}
+		/*
+		 * This is no longer a handle based call.
+		 */
+		res = extattr_set_file(path, attr.namespace, attr.name, value, size);
+	}
+	else {
+		res = extattr_set_fd(fd, attr.namespace, attr.name, value, size);
+	}
+	return (res >= 0) ? 0 : -1;
+#else
+	errno = ENOSYS;
+	return -1;
+#endif
+}
+
+
+static int freebsd_connect(struct vfs_handle_struct *handle,
+			   const char *service,
+			   const char *user)
+{
+	struct freebsd_handle_data *data;
+	int enumval, saved_errno;
+
+	int ret = SMB_VFS_NEXT_CONNECT(handle, service, user);
+
+	if (ret < 0) {
+		return ret;
+	}
+
+	data = talloc_zero(handle->conn, struct freebsd_handle_data);
+	if (!data) {
+		saved_errno = errno;
+		SMB_VFS_NEXT_DISCONNECT(handle);
+		DEBUG(0, ("talloc_zero() failed\n"));
+		errno = saved_errno;
+		return -1;
+	}
+
+	enumval = lp_parm_enum(SNUM(handle->conn), "freebsd",
+			       "extattr mode", extattr_mode_param, FREEBSD_EXTATTR_LEGACY);
+	if (enumval == -1) {
+		saved_errno = errno;
+		SMB_VFS_NEXT_DISCONNECT(handle);
+		DBG_DEBUG("value for freebsd: 'extattr mode' is unknown\n");
+		errno = saved_errno;
+		return -1;
+	}
+
+	if(freebsd_in_jail()) {
+		enumval = FREEBSD_EXTATTR_COMPAT;
+		DBG_WARNING("running in jail, enforcing 'compat' mode\n");
+	}
+
+	data->extattr_mode = (enum extattr_mode)enumval;
+
+	SMB_VFS_HANDLE_SET_DATA(handle, data, NULL,
+				struct freebsd_handle_data,
+				return -1);
+
+	DBG_DEBUG("connect to service[%s] with '%s' extattr mode\n",
+		  service, extattr_mode_param[data->extattr_mode].name);
+
+	return 0;
+}
+
+
+static void freebsd_disconnect(vfs_handle_struct *handle)
+{
+	SMB_VFS_NEXT_DISCONNECT(handle);
+}
+
+/* VFS operations structure */
+
+struct vfs_fn_pointers freebsd_fns = {
+	/* Disk operations */
+	.connect_fn = freebsd_connect,
+	.disconnect_fn = freebsd_disconnect,
+
+	/* EA operations. */
+	.getxattrat_send_fn = vfs_not_implemented_getxattrat_send,
+	.getxattrat_recv_fn = vfs_not_implemented_getxattrat_recv,
+	.fgetxattr_fn = freebsd_fgetxattr,
+	.flistxattr_fn = freebsd_flistxattr,
+	.fremovexattr_fn = freebsd_fremovexattr,
+	.fsetxattr_fn = freebsd_fsetxattr,
+};
+
+static_decl_vfs;
+NTSTATUS vfs_freebsd_init(TALLOC_CTX *ctx)
+{
+	NTSTATUS ret;
+
+	ret = smb_register_vfs(SMB_VFS_INTERFACE_VERSION, "freebsd",
+				&freebsd_fns);
+
+	if (!NT_STATUS_IS_OK(ret)) {
+		return ret;
+	}
+
+	vfs_freebsd_debug_level = debug_add_class("freebsd");
+	if (vfs_freebsd_debug_level == -1) {
+		vfs_freebsd_debug_level = DBGC_VFS;
+		DEBUG(0, ("vfs_freebsd: Couldn't register custom debugging class!\n"));
+	} else {
+		DEBUG(10, ("vfs_freebsd: Debug class number of 'fileid': %d\n", vfs_freebsd_debug_level));
+	}
+
+	return ret;
+}
