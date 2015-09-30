<?php

if (file_exists("/usr/local/pkg/autoconfigbackup.inc")) {
	require_once("/usr/local/pkg/autoconfigbackup.inc");
	upload_config();
}
